<?php
declare(strict_types=1);
require_once __DIR__ . '/images.php';

function load_products(): array
{
    return db()->query('SELECT * FROM products ORDER BY name COLLATE NOCASE')->fetchAll();
}

function process_product_request(): void
{
    $action = $_POST['action'] ?? '';
    $admin = require_admin();

    if ($action === 'save_product') {
        $id    = (int) ($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $price = parse_price_to_cents((string) ($_POST['price'] ?? ''));
        $image = trim($_POST['image'] ?? '');
        $descr = trim($_POST['description'] ?? '');

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

        if ($id > 0) {
            $stmt = db()->prepare('UPDATE products SET name = ?, price_cents = ?, image = ?, description = ?, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$name, $price, $image, $descr, $id]);
            flash_set('success', 'Produto atualizado.');
        } else {
            $stmt = db()->prepare('INSERT INTO products (name, price_cents, image, description) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $price, $image, $descr]);
            flash_set('success', 'Produto cadastrado.');
        }
    } elseif ($action === 'delete_product') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('success', 'Produto excluído.');
    }
}