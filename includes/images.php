<?php
declare(strict_types=1);

function list_images(): array
{
    $images = glob(__DIR__ . '/../img/*.{png,jpg,jpeg,webp}', GLOB_BRACE);
    return array_map('basename', $images ?: []);
}

function handle_image_upload(array $file, string $prefix = 'produto_'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload da imagem (código ' . $file['error'] . ').');
    }
    if ((int) $file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no máximo 3 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Formato de imagem não permitido. Use PNG, JPG ou WEBP.');
    }
    $name = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $destination = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Não foi possível salvar a imagem na pasta img/.');
    }
    return $name;
}

function render_image_options(?string $current): string
{
    $html = '<option value="">Sem imagem</option>';
    foreach (list_images() as $image) {
        $selected = $current === $image ? ' selected' : '';
        $html .= '<option value="' . e($image) . '"' . $selected . '>' . e($image) . '</option>';
    }
    return $html;
}