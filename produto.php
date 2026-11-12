<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$product = $slug !== '' ? load_product_by_slug($slug) : null;

if (!$product) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$content = content_all();
$admin = current_admin();
$related = [];
$stmt = db()->prepare('SELECT * FROM products WHERE active = 1 AND category = ? AND id != ? ORDER BY name COLLATE NOCASE LIMIT 3');
$stmt->execute([(string) $product['category'], (int) $product['id']]);
$related = $stmt->fetchAll();

$description = (string) ($product['description'] !== '' ? $product['description'] : $product['name']);
$meta = [
    'title'       => $product['name'] . ' | ' . APP_NAME,
    'description' => $description . ' — ' . format_price_cents((int) $product['price_cents']) . '. Peça pelo site e combine a entrega pelo WhatsApp.',
    'canonical'   => url('produto.php?slug=' . rawurlencode((string) $product['slug'])),
    'image'       => url('img/' . $product['image']),
    'type'        => 'product',
    'jsonld'      => seo_product_jsonld($product),
];
$breadcrumb = seo_breadcrumb_jsonld([
    ['name' => 'Início', 'url' => url()],
    ['name' => 'Produtos', 'url' => url('#produtos')],
    ['name' => (string) $product['name'], 'url' => url('produto.php?slug=' . rawurlencode((string) $product['slug']))],
]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#48250e">
<?= seo_head($meta) ?>
<script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>"><?= json_encode($breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<link rel="icon" href="img/logo.png" sizes="any">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<script src="<?= e(asset('js/cart.js')) ?>" defer></script>
</head>
<body data-page="product">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
<a class="brand" href="index.php"><?= render_picture('logo.png', 'Tia Iná', ['widths' => [160, 320], 'sizes' => '74px', 'width' => 74, 'height' => 74]) ?><span><strong><?= e($content['brand_name']) ?></strong><small><?= e($content['brand_tagline']) ?></small></span></a>
<button class="menu-toggle" type="button" aria-label="Abrir menu" aria-controls="navigation" aria-expanded="false">☰</button>
<nav id="navigation" aria-label="Navegação principal">
<a href="index.php">Início</a>
<a class="active" href="index.php#produtos" aria-current="page">Produtos</a>
<a href="index.php#como-comprar">Como comprar</a>
<a href="index.php#fazenda">A fazenda</a>
<a href="index.php#contato">Contato</a>
<?php if ($admin): ?><a class="nav-admin" href="admin.php">Painel</a><?php endif; ?>
</nav>
<button class="cart-toggle" type="button" id="cart-open" aria-label="Abrir carrinho" aria-controls="cart-drawer" aria-expanded="false"><span class="cart-icon" aria-hidden="true"></span><span class="cart-count" id="cart-count" hidden>0</span></button>
<a class="login-link" href="login.php" aria-label="Acessar área administrativa" title="Entrar"><span class="user-icon" aria-hidden="true"></span></a>
</header>

<main id="conteudo" class="product-page">
<nav class="breadcrumb" aria-label="Você está aqui">
<a href="index.php">Início</a><span aria-hidden="true">›</span>
<a href="index.php#produtos">Produtos</a><span aria-hidden="true">›</span>
<span aria-current="page"><?= e($product['name']) ?></span>
</nav>

<article class="product-detail">
<div class="product-gallery">
<?= render_picture((string) $product['image'], $description, ['sizes' => '(max-width: 900px) 92vw, 520px', 'loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'product-photo']) ?>
</div>
<div class="product-summary">
<p class="product-category"><?= e((string) $product['category']) ?></p>
<h1><?= e($product['name']) ?></h1>
<p class="product-price"><?= format_price_cents((int) $product['price_cents']) ?></p>
<p class="product-description"><?= nl2br(e($description)) ?></p>
<div class="product-buy">
<label class="qty-field">Quantidade
<input type="number" id="product-qty" min="1" value="1" inputmode="numeric">
</label>
<button class="button yellow add-to-cart" type="button" id="product-add"
    data-id="<?= (int) $product['id'] ?>"
    data-name="<?= e($product['name']) ?>"
    data-price="<?= (int) $product['price_cents'] ?>"
    data-image="img/<?= e($product['image']) ?>"
    data-url="produto.php?slug=<?= e((string) $product['slug']) ?>"
    data-qty-source="#product-qty">Adicionar ao carrinho</button>
</div>
<ul class="product-perks">
<li>Feito artesanalmente na fazenda</li>
<li>Entrega em Santa Rita do Passa Quatro e região</li>
<li>Pagamento combinado no WhatsApp (PIX, dinheiro ou cartão)</li>
</ul>
<a class="back-to-products" href="index.php#produtos">← Ver todos os produtos</a>
</div>
</article>

<?php if ($related): ?>
<section class="section related" aria-labelledby="related-title">
<h2 id="related-title">Você também pode gostar</h2>
<div class="product-grid related-grid">
<?php foreach ($related as $item): ?>
<article class="product-card">
<a class="product-link" href="produto.php?slug=<?= e((string) $item['slug']) ?>">
<?= render_picture((string) $item['image'], (string) $item['name'], ['sizes' => '(max-width: 640px) 44vw, 260px']) ?>
</a>
<div class="product-info">
<h3><a href="produto.php?slug=<?= e((string) $item['slug']) ?>"><?= e($item['name']) ?></a></h3>
<p class="price"><?= format_price_cents((int) $item['price_cents']) ?></p>
</div>
<button class="add-to-cart" type="button"
    data-id="<?= (int) $item['id'] ?>"
    data-name="<?= e($item['name']) ?>"
    data-price="<?= (int) $item['price_cents'] ?>"
    data-image="img/<?= e($item['image']) ?>"
    data-url="produto.php?slug=<?= e((string) $item['slug']) ?>">Adicionar</button>
</article>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>
</main>

<footer id="contato" class="site-footer">
<div class="footer-inner">
<h2><?= e($content['contact_title']) ?></h2>
<div class="contact-links">
<a href="<?= e($content['instagram_url']) ?>" target="_blank" rel="noopener noreferrer"><span class="instagram-icon" aria-hidden="true"></span><?= e($content['instagram_user']) ?></a>
<a href="<?= e(whatsapp_url($content['phone_url'])) ?>" target="_blank" rel="noopener noreferrer"><?= whatsapp_icon() ?><?= e($content['phone_display']) ?></a>
</div>
<p class="footer-legal">© <?= date('Y') ?> <?= e($content['brand_name']) ?> · Feito com carinho na fazenda.</p>
</div>
</footer>

<div class="cart-overlay" id="cart-overlay" hidden></div>
<aside class="cart-drawer" id="cart-drawer" aria-labelledby="cart-title" aria-hidden="true">
<header class="cart-head">
<h2 id="cart-title">Seu carrinho</h2>
<button class="cart-close" type="button" id="cart-close" aria-label="Fechar carrinho">×</button>
</header>
<div class="cart-items" id="cart-items"><p class="cart-empty">Seu carrinho está vazio. Escolha seus produtos favoritos.</p></div>
<footer class="cart-foot">
<div class="cart-total">Total <strong id="cart-total">R$ 0,00</strong></div>
<a class="button yellow cart-checkout" href="checkout.php" id="cart-checkout">Finalizar pedido</a>
<p class="cart-note">Frete e forma de pagamento combinados no WhatsApp.</p>
</footer>
</aside>
<button class="cart-fab" type="button" id="cart-fab" aria-label="Abrir carrinho"><span class="cart-icon" aria-hidden="true"></span><span class="cart-count" id="cart-count-fab" hidden>0</span></button>
<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
