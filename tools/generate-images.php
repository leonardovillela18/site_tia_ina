<?php
declare(strict_types=1);

/**
 * Gera as variantes WebP usadas pelo <picture> responsivo.
 * Uso: php tools/generate-images.php [--force]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/images.php';

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "GD com suporte a WebP nao esta habilitado neste PHP.\n");
    exit(1);
}

$force = in_array('--force', $argv ?? [], true);

// Larguras por tipo de imagem (as maiores so existem se a original comportar).
$plan = [
    'logo.png'         => [160, 320, 480],
    'tela_inicio.png'  => [960, 1600],
    'tela_login.png'   => [960, 1600],
];
$defaultWidths = [480, 960];

$images = list_images();
if (!$images) {
    fwrite(STDERR, "Nenhuma imagem encontrada em img/.\n");
    exit(1);
}

$generated = 0;
$skipped = 0;
$failed = 0;

foreach ($images as $filename) {
    // Ignora as proprias variantes.
    if (preg_match('/-\d+\.webp$/', $filename)) {
        continue;
    }

    $widths = $plan[$filename] ?? $defaultWidths;
    $source = img_dir() . DIRECTORY_SEPARATOR . $filename;
    [$width, $height] = image_dimensions($filename);
    if ($width === 0) {
        fwrite(STDERR, "Ignorando {$filename}: formato nao suportado.\n");
        $failed++;
        continue;
    }

    foreach ($widths as $targetWidth) {
        if ($targetWidth > $width) {
            continue;
        }
        $target = img_dir() . DIRECTORY_SEPARATOR . image_variant_name($filename, $targetWidth);
        if (is_file($target) && !$force) {
            $skipped++;
            continue;
        }
        if (make_webp_variant($source, $target, $targetWidth)) {
            $generated++;
            $size = filesize($target);
            printf("  %-40s %5dpx  %6.1f KB\n", basename($target), $targetWidth, $size / 1024);
        } else {
            $failed++;
            fwrite(STDERR, "  Falhou: {$filename} -> {$targetWidth}px\n");
        }
    }
}

printf("\nConcluido: %d geradas, %d ja existiam, %d falhas.\n", $generated, $skipped, $failed);
exit($failed > 0 ? 1 : 0);
