<?php
declare(strict_types=1);

const IMAGE_VARIANT_WIDTHS = [480, 960, 1600];

function img_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img';
}

function list_images(): array
{
    $images = glob(img_dir() . DIRECTORY_SEPARATOR . '*.{png,jpg,jpeg,webp}', GLOB_BRACE);
    return array_map('basename', $images ?: []);
}

function image_variant_name(string $filename, int $width): string
{
    $info = pathinfo($filename);
    return $info['filename'] . '-' . $width . '.webp';
}

function image_variant_path(string $filename, int $width): ?string
{
    $path = img_dir() . DIRECTORY_SEPARATOR . image_variant_name($filename, $width);
    return is_file($path) ? $path : null;
}

/**
 * Gera (se necessario) as versoes WebP redimensionadas de uma imagem.
 * Retorna a lista de larguras realmente disponiveis.
 */
function ensure_image_variants(string $filename, ?array $widths = null): array
{
    $filename = basename($filename);
    $source = img_dir() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($source) || !function_exists('imagewebp')) {
        return [];
    }

    $info = @getimagesize($source);
    if ($info === false) {
        return [];
    }
    $originalWidth = (int) $info[0];
    $widths = $widths ?? IMAGE_VARIANT_WIDTHS;

    $available = [];
    foreach ($widths as $width) {
        $width = (int) $width;
        if ($width <= 0 || $width > $originalWidth) {
            continue;
        }
        $target = img_dir() . DIRECTORY_SEPARATOR . image_variant_name($filename, $width);
        if (!is_file($target)) {
            if (!make_webp_variant($source, $target, $width)) {
                continue;
            }
        }
        $available[] = $width;
    }
    return $available;
}

function make_webp_variant(string $source, string $target, int $maxWidth): bool
{
    $info = @getimagesize($source);
    if ($info === false) {
        return false;
    }
    $mime = (string) ($info['mime'] ?? '');
    $image = match ($mime) {
        'image/png'  => @imagecreatefrompng($source),
        'image/jpeg' => @imagecreatefromjpeg($source),
        'image/webp' => @imagecreatefromwebp($source),
        default      => false,
    };
    if ($image === false) {
        return false;
    }

    $width = (int) imagesx($image);
    $height = (int) imagesy($image);
    $newWidth = min($maxWidth, $width);
    $newHeight = max(1, (int) round($height * ($newWidth / $width)));

    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    if ($canvas === false) {
        imagedestroy($image);
        return false;
    }
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
    imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $ok = imagewebp($canvas, $target, 82);
    imagedestroy($canvas);
    imagedestroy($image);

    if (!$ok && is_file($target)) {
        @unlink($target);
    }
    return (bool) $ok;
}

function image_dimensions(string $filename): array
{
    $path = img_dir() . DIRECTORY_SEPARATOR . basename($filename);
    $info = is_file($path) ? @getimagesize($path) : false;
    if ($info === false) {
        return [0, 0];
    }
    return [(int) $info[0], (int) $info[1]];
}

/**
 * Monta um <picture> responsivo. Se as variantes WebP nao existirem, usa o original.
 */
function render_picture(string $filename, string $alt, array $options = []): string
{
    $filename = basename($filename);
    if ($filename === '' || !is_file(img_dir() . DIRECTORY_SEPARATOR . $filename)) {
        return '';
    }

    $sizes = (string) ($options['sizes'] ?? '(max-width: 640px) 46vw, 300px');
    $class = (string) ($options['class'] ?? '');
    $loading = (string) ($options['loading'] ?? 'lazy');
    $fetchPriority = (string) ($options['fetchpriority'] ?? '');
    $widths = $options['widths'] ?? IMAGE_VARIANT_WIDTHS;

    [$originalWidth, $originalHeight] = image_dimensions($filename);
    $width = (int) ($options['width'] ?? $originalWidth);
    $height = (int) ($options['height'] ?? $originalHeight);

    $available = ensure_image_variants($filename, is_array($widths) ? $widths : null);
    $attributes = 'alt="' . e($alt) . '"';
    if ($width > 0 && $height > 0) {
        $attributes .= ' width="' . $width . '" height="' . $height . '"';
    }
    $attributes .= ' loading="' . e($loading) . '" decoding="async"';
    if ($class !== '') {
        $attributes .= ' class="' . e($class) . '"';
    }
    if ($fetchPriority !== '') {
        $attributes .= ' fetchpriority="' . e($fetchPriority) . '"';
    }

    if (!$available) {
        return '<img src="img/' . e($filename) . '" ' . $attributes . '>';
    }

    $srcset = [];
    foreach ($available as $variantWidth) {
        $srcset[] = 'img/' . e(image_variant_name($filename, $variantWidth)) . ' ' . $variantWidth . 'w';
    }

    return '<picture><source type="image/webp" srcset="' . implode(', ', $srcset) . '" sizes="' . e($sizes) . '">'
        . '<img src="img/' . e($filename) . '" ' . $attributes . '></picture>';
}

/**
 * URL otimizada para uso em CSS/atributos simples (usa WebP quando existir).
 */
function optimized_image_url(string $filename, int $width = 1600): string
{
    $filename = basename($filename);
    $variant = image_variant_path($filename, $width);
    if ($variant !== null) {
        return 'img/' . image_variant_name($filename, $width);
    }
    return 'img/' . $filename;
}

function handle_image_upload(array $file, string $prefix = 'produto_'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload da imagem (código ' . (int) $file['error'] . ').');
    }
    if ((int) $file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no máximo 8 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Formato de imagem não permitido. Use PNG, JPG ou WEBP.');
    }
    $name = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $destination = img_dir() . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Não foi possível salvar a imagem na pasta img/.');
    }
    @chmod($destination, 0644);
    ensure_image_variants($name);
    return $name;
}

function render_image_options(?string $current): string
{
    $html = '<option value="">Sem imagem</option>';
    foreach (list_images() as $image) {
        if (str_contains($image, '-') && preg_match('/-\d+\.webp$/', $image)) {
            continue; // esconde as variantes geradas
        }
        $selected = $current === $image ? ' selected' : '';
        $html .= '<option value="' . e($image) . '"' . $selected . '>' . e($image) . '</option>';
    }
    return $html;
}

/**
 * Remove uma imagem e suas variantes somente se nao estiver em uso.
 */
function cleanup_image(string $filename): void
{
    $filename = basename($filename);
    if ($filename === '') {
        return;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE image = ?');
    $stmt->execute([$filename]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM site_settings WHERE value = ?');
    $stmt->execute([$filename]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }
    if (in_array($filename, ['logo.png', 'tela_inicio.png', 'tela_login.png'], true)) {
        return;
    }

    @unlink(img_dir() . DIRECTORY_SEPARATOR . $filename);
    foreach (IMAGE_VARIANT_WIDTHS as $width) {
        @unlink(img_dir() . DIRECTORY_SEPARATOR . image_variant_name($filename, $width));
    }
}
