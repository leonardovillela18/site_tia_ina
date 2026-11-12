<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$products = load_products(true);
$content = content_all();
$now = gmdate('c');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <url>
        <loc><?= e(url()) ?></loc>
        <lastmod><?= e($now) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
        <image:image>
            <image:loc><?= e(url('img/logo.png')) ?></image:loc>
            <image:title><?= e($content['brand_name']) ?></image:title>
        </image:image>
    </url>
    <url>
        <loc><?= e(url('index.php#produtos')) ?></loc>
        <lastmod><?= e($now) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>
<?php foreach ($products as $product): ?>
    <url>
        <loc><?= e(url('produto.php?slug=' . rawurlencode((string) $product['slug']))) ?></loc>
        <lastmod><?= e(date('c', strtotime((string) ($product['updated_at'] ?: $product['created_at'] ?: 'now')) ?: time())) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
        <image:image>
            <image:loc><?= e(url('img/' . $product['image'])) ?></image:loc>
            <image:title><?= e($product['name']) ?></image:title>
        </image:image>
    </url>
<?php endforeach; ?>
</urlset>
