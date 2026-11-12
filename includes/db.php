<?php
declare(strict_types=1);

const SCHEMA_VERSION = 3;

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        try {
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
        } catch (Throwable $exception) {
            // Alguns sistemas de arquivos nao suportam WAL; segue no modo padrao.
            error_log('SQLite WAL indisponivel: ' . $exception->getMessage());
        }
    }
    return $pdo;
}

/**
 * Roda as migracoes apenas quando o schema muda (controlado por PRAGMA user_version).
 */
function migrate(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = db();
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version >= SCHEMA_VERSION) {
        $done = true;
        return;
    }

    if ($version < 1) {
        migration_1_initial($pdo);
    }
    if ($version < 2) {
        migration_2_catalog_orders($pdo);
    }
    if ($version < 3) {
        migration_3_whatsapp_contact($pdo);
    }

    $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
    $done = true;
}

/**
 * Compatibilidade: chamadas antigas continuam funcionando.
 */
function migrate_and_seed(): void
{
    migrate();
}

function migration_1_initial(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT "",
            price_cents INTEGER NOT NULL DEFAULT 0,
            image TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            used INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS site_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
    ');

    // O seed roda uma unica vez (dentro da migracao), nunca mais em requests seguintes.
    $count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($count === 0) {
        $insert = $pdo->prepare('INSERT INTO products (name, price_cents, image, description) VALUES (?, ?, ?, ?)');
        $seed = [
            ['Queijo fresco - 500g', 2000, 'queijo_01.png', 'Queijo fresco de 500 gramas'],
            ['Queijo fresco - 1kg', 3500, 'queijo_02.png', 'Queijo fresco de 1 quilo'],
            ['Mussarela palitinho - 250g', 2000, 'palito.png', 'Mussarela palitinho'],
            ['Mussarela p. temperada - 250g', 2000, 'palito_temp.png', 'Mussarela palitinho temperada'],
            ['Requeijão de corte - 250g', 2000, 'requeijao.png', 'Requeijão de corte'],
            ['Queijo meia cura', 3500, 'queijo_03.png', 'Queijo meia cura'],
            ['Doce de leite - 250g', 1600, 'doce_de_leite.png', 'Doce de leite artesanal'],
            ['Doce de leite c/ amendoim - 250g', 1800, 'doce_de_leite_recheado.png', 'Doce de leite com amendoim'],
        ];
        foreach ($seed as $product) {
            $insert->execute($product);
        }
    }

    seed_default_admin();
}

function migration_2_catalog_orders(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info(products)') as $column) {
        $columns[(string) $column['name']] = true;
    }

    $additions = [
        'category' => "ALTER TABLE products ADD COLUMN category TEXT NOT NULL DEFAULT 'Queijos'",
        'slug'     => "ALTER TABLE products ADD COLUMN slug TEXT NOT NULL DEFAULT ''",
        'stock'    => 'ALTER TABLE products ADD COLUMN stock INTEGER NOT NULL DEFAULT 10',
        'active'   => 'ALTER TABLE products ADD COLUMN active INTEGER NOT NULL DEFAULT 1',
    ];
    foreach ($additions as $name => $sql) {
        if (!isset($columns[$name])) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_name TEXT NOT NULL,
            customer_phone TEXT NOT NULL,
            customer_address TEXT NOT NULL DEFAULT "",
            note TEXT NOT NULL DEFAULT "",
            delivery_method TEXT NOT NULL DEFAULT "entrega",
            total_cents INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "novo",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
            product_id INTEGER NOT NULL DEFAULT 0,
            product_name TEXT NOT NULL,
            unit_price_cents INTEGER NOT NULL DEFAULT 0,
            quantity INTEGER NOT NULL DEFAULT 1,
            line_total_cents INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL,
            identifier TEXT NOT NULL DEFAULT "",
            ip TEXT NOT NULL DEFAULT "",
            created_at INTEGER NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
        CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
        CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
        CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup ON login_attempts(action, ip, created_at);
        CREATE INDEX IF NOT EXISTS idx_products_slug ON products(slug);
        CREATE INDEX IF NOT EXISTS idx_products_category ON products(category);
    ');

    // Preenche slug e categoria dos produtos que ja existiam.
    $used = [];
    foreach ($pdo->query('SELECT id, name, slug, category FROM products ORDER BY id') as $row) {
        $id = (int) $row['id'];
        $slug = trim((string) $row['slug']);
        if ($slug === '') {
            $slug = unique_slug((string) $row['name'], $id, $used);
        }
        $used[$slug] = true;

        $category = trim((string) $row['category']);
        if ($category === '' || $category === 'Queijos') {
            $category = guess_category((string) $row['name']);
        }

        $stmt = $pdo->prepare('UPDATE products SET slug = ?, category = ? WHERE id = ?');
        $stmt->execute([$slug, $category, $id]);
    }
}

function migration_3_whatsapp_contact(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT value FROM site_settings WHERE key = ?');
    $stmt->execute(['phone_url']);
    $value = trim((string) ($stmt->fetchColumn() ?: ''));

    // Só converte links antigos de telefone (tel:). Links já ajustados ficam intactos.
    if ($value !== '' && !preg_match('#^tel:#i', $value)) {
        return;
    }

    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits === '') {
        $digits = preg_replace('/\D+/', '', WHATSAPP_NUMBER) ?? WHATSAPP_NUMBER;
    }

    $pdo->prepare('
        INSERT INTO site_settings (key, value, updated_at) VALUES (?, ?, datetime("now"))
        ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at
    ')->execute(['phone_url', 'https://wa.me/' . $digits]);
}

function guess_category(string $name): string
{
    $normalized = mb_strtolower($name, 'UTF-8');
    if (str_contains($normalized, 'doce') || str_contains($normalized, 'goiabada') || str_contains($normalized, 'geleia')) {
        return 'Doces';
    }
    if (str_contains($normalized, 'mussarela') || str_contains($normalized, 'palito')) {
        return 'Mussarelas';
    }
    if (str_contains($normalized, 'requeij')) {
        return 'Requeijões';
    }
    return 'Queijos';
}

function admin_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function seed_default_admin(): void
{
    if (admin_count() > 0) {
        return;
    }
    $stmt = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute(['admin', 'admin@exemplo.com', password_hash('admin123', PASSWORD_DEFAULT)]);
}
