<?php
declare(strict_types=1);
require_once __DIR__ . '/images.php';

function load_products(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM products';
    if ($onlyActive) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY category COLLATE NOCASE, name COLLATE NOCASE';
    return db()->query($sql)->fetchAll();
}

function load_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function load_product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE slug = ? AND active = 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function product_categories(): array
{
    $categories = [];
    foreach (db()->query('SELECT DISTINCT category FROM products ORDER BY category COLLATE NOCASE') as $row) {
        $category = trim((string) $row['category']);
        if ($category !== '') {
            $categories[] = $category;
        }
    }
    if (!$categories) {
        $categories = ['Queijos', 'Mussarelas', 'Requeijões', 'Doces'];
    }
    return $categories;
}

function product_slug_is_free(string $slug, int $ignoreId = 0): bool
{
    $stmt = db()->prepare('SELECT id FROM products WHERE slug = ? AND id != ? LIMIT 1');
    $stmt->execute([$slug, $ignoreId]);
    return !$stmt->fetch();
}

function make_product_slug(string $name, int $id = 0): string
{
    $base = slugify($name) ?: 'produto';
    $slug = $base;
    $suffix = 2;
    while (!product_slug_is_free($slug, $id)) {
        $slug = $base . '-' . $suffix;
        $suffix++;
        if ($suffix > 50) {
            $slug = $base . '-' . bin2hex(random_bytes(2));
            break;
        }
    }
    return $slug;
}

function process_product_request(): void
{
    $action = $_POST['action'] ?? '';
    require_admin();

    if ($action === 'save_product') {
        $id       = (int) ($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $price    = parse_price_to_cents((string) ($_POST['price'] ?? ''));
        $image    = trim($_POST['image'] ?? '');
        $descr    = trim($_POST['description'] ?? '');
        $category = trim((string) ($_POST['category'] ?? 'Queijos'));
        $active   = isset($_POST['active']) ? 1 : 0;
        $oldImage = '';

        if ($id > 0) {
            $existing = load_product($id);
            if ($existing) {
                $oldImage = (string) $existing['image'];
            }
        }

        try {
            $uploaded = handle_image_upload($_FILES['image_file'] ?? []);
        } catch (RuntimeException $exception) {
            flash_set('error', $exception->getMessage());
            redirect('index.php');
        }

        if ($uploaded !== null) {
            $image = $uploaded;
        } elseif ($id > 0 && $image === '') {
            $stmt = db()->prepare('SELECT image FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $image = (string) ($stmt->fetchColumn() ?: '');
        }

        if ($name === '' || $price <= 0) {
            flash_set('error', 'Informe o nome do produto e um preço maior que zero.');
            redirect('index.php');
        }
        if ($image !== '' && !in_array($image, list_images(), true)) {
            flash_set('error', 'Imagem inválida.');
            redirect('index.php');
        }
        if ($category === '') {
            $category = 'Queijos';
        }

        if ($id > 0) {
            $slug = make_product_slug($name, $id);
            $stmt = db()->prepare('UPDATE products SET name = ?, price_cents = ?, image = ?, description = ?, category = ?, active = ?, slug = ?, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$name, $price, $image, $descr, $category, $active, $slug, $id]);
            flash_set('success', 'Produto atualizado.');
        } else {
            $slug = make_product_slug($name);
            $stmt = db()->prepare('INSERT INTO products (name, price_cents, image, description, category, active, slug) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $price, $image, $descr, $category, $active, $slug]);
            flash_set('success', 'Produto cadastrado.');
        }

        if ($oldImage !== '' && $oldImage !== $image) {
            cleanup_image($oldImage);
        }
    } elseif ($action === 'delete_product') {
        $id = (int) ($_POST['id'] ?? 0);
        $product = load_product($id);
        $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        if ($product && (string) $product['image'] !== '') {
            cleanup_image((string) $product['image']);
        }
        flash_set('success', 'Produto excluído.');
    }
}
