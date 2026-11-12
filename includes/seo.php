<?php
declare(strict_types=1);

function seo_head(array $meta): string
{
    $title = (string) ($meta['title'] ?? APP_NAME);
    $description = trim((string) ($meta['description'] ?? ''));
    if ($description === '') {
        $description = 'Queijos, mussarelas, requeijão e doces artesanais feitos com carinho na fazenda da Tia Iná.';
    }
    $description = mb_strimwidth(preg_replace('/\s+/', ' ', strip_tags($description)) ?? $description, 0, 160, '…');
    $canonical = (string) ($meta['canonical'] ?? url());
    $image = (string) ($meta['image'] ?? url('img/logo.png'));
    $type = (string) ($meta['type'] ?? 'website');
    $noindex = (bool) ($meta['noindex'] ?? false);

    $html = '<title>' . e($title) . '</title>';
    $html .= '<meta name="description" content="' . e($description) . '">';
    $html .= '<link rel="canonical" href="' . e($canonical) . '">';
    if ($noindex) {
        $html .= '<meta name="robots" content="noindex, nofollow">';
    } else {
        $html .= '<meta name="robots" content="index, follow, max-image-preview:large">';
    }
    $html .= '<meta property="og:locale" content="pt_BR">';
    $html .= '<meta property="og:type" content="' . e($type) . '">';
    $html .= '<meta property="og:site_name" content="' . e(APP_NAME) . '">';
    $html .= '<meta property="og:title" content="' . e($title) . '">';
    $html .= '<meta property="og:description" content="' . e($description) . '">';
    $html .= '<meta property="og:url" content="' . e($canonical) . '">';
    $html .= '<meta property="og:image" content="' . e($image) . '">';
    $html .= '<meta name="twitter:card" content="summary_large_image">';
    $html .= '<meta name="twitter:title" content="' . e($title) . '">';
    $html .= '<meta name="twitter:description" content="' . e($description) . '">';
    $html .= '<meta name="twitter:image" content="' . e($image) . '">';

    if (isset($meta['jsonld']) && is_array($meta['jsonld'])) {
        $json = json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            $html .= '<script type="application/ld+json" nonce="' . e(csp_nonce()) . '">' . $json . '</script>';
        }
    }

    return $html;
}

function seo_product_jsonld(array $product): array
{
    $image = url('img/' . (string) $product['image']);
    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => (string) $product['name'],
        'description' => (string) ($product['description'] !== '' ? $product['description'] : $product['name']),
        'image'       => [$image],
        'sku'         => 'TI-' . (int) $product['id'],
        'brand'       => ['@type' => 'Brand', 'name' => 'Tia Iná'],
        'offers'      => [
            '@type'         => 'Offer',
            'url'           => url('produto.php?slug=' . rawurlencode((string) $product['slug'])),
            'priceCurrency' => 'BRL',
            'price'         => number_format(((int) $product['price_cents']) / 100, 2, '.', ''),
            'availability'  => (int) $product['active'] === 1
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ],
    ];
}

function seo_localbusiness_jsonld(array $content): array
{
    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'LocalBusiness',
        'name'        => (string) $content['brand_name'],
        'description' => (string) $content['hero_description'],
        'image'       => url('img/logo.png'),
        'telephone'   => (string) $content['phone_display'],
        'priceRange'  => 'R$',
        'address'     => [
            '@type'           => 'PostalAddress',
            'addressLocality' => 'Santa Rita do Passa Quatro',
            'addressRegion'   => 'SP',
            'addressCountry'  => 'BR',
        ],
        'sameAs'      => [(string) $content['instagram_url']],
        'url'         => url(),
    ];
}

function seo_breadcrumb_jsonld(array $items): array
{
    $elements = [];
    foreach ($items as $position => $item) {
        $elements[] = [
            '@type'    => 'ListItem',
            'position' => $position + 1,
            'name'     => (string) $item['name'],
            'item'     => (string) $item['url'],
        ];
    }
    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $elements,
    ];
}
