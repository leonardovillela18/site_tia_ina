<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

function migrate_and_seed(): void
{
    $pdo = db();
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