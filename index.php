<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? null);
    if (($_POST['action'] ?? '') === 'save_content') {
        handle_content_save();
    }
    process_product_request();
    redirect('index.php');
}

$admin = current_admin();
$products = load_products(!$admin);
$content = content_all();
$categories = product_categories();
$flash = flash_render();

$meta = [
    'title'       => APP_NAME . ' | Queijos, doces e produtos artesanais',
    'description' => $content['hero_description'],
    'canonical'   => url(),
    'image'       => url('img/logo.png'),
    'jsonld'      => seo_localbusiness_jsonld($content),
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#48250e">
<?= seo_head($meta) ?>
<link rel="icon" href="img/logo.png" sizes="any">
<link rel="apple-touch-icon" href="img/logo.png">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<script src="<?= e(asset('js/cart.js')) ?>" defer></script>
</head>
<body data-page="home">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
<a class="brand" href="index.php"><?= render_picture('logo.png', 'Tia Iná', ['widths' => [160, 320], 'sizes' => '74px', 'width' => 74, 'height' => 74]) ?><span><strong><?= e($content['brand_name']) ?></strong><small><?= e($content['brand_tagline']) ?></small></span></a>
<button class="menu-toggle" type="button" aria-label="Abrir menu" aria-controls="navigation" aria-expanded="false">☰</button>
<nav id="navigation" aria-label="Navegação principal">
<a href="#inicio">Início</a>
<a href="#produtos">Produtos</a>
<a href="#como-comprar">Como comprar</a>
<a href="#fazenda">A fazenda</a>
<a href="#contato">Contato</a>
<?php if ($admin): ?><a class="nav-admin" href="admin.php">Painel</a><?php endif; ?>
</nav>
<button class="cart-toggle" type="button" id="cart-open" aria-label="Abrir carrinho" aria-controls="cart-drawer" aria-expanded="false"><span class="cart-icon" aria-hidden="true"></span><span class="cart-count" id="cart-count" hidden>0</span></button>
<a class="login-link" href="login.php" aria-label="Acessar área administrativa" title="Entrar"><span class="user-icon" aria-hidden="true"></span></a>
</header>

<main id="conteudo">
<section id="inicio" class="hero" aria-labelledby="hero-title">
<?php if ($admin): ?><?= content_edit_button('content-editor-inicio') ?><?php endif; ?>
<div class="hero-inner">
<h1 id="hero-title"><?= sanitize_html($content['hero_title']) ?></h1>
<p class="hero-description"><?= e($content['hero_description']) ?></p>
<div class="hero-actions">
<a class="button yellow" href="#produtos"><span class="bag-icon" aria-hidden="true"></span>Ver produtos</a>
<a class="button glass" href="#contato"><span class="chat-icon" aria-hidden="true"></span>Fale conosco</a>
</div>
</div>
</section>

<div class="highlights">
<div><strong><?= e($content['highlight_1_title']) ?></strong><span><?= e($content['highlight_1_text']) ?></span></div>
<div><strong><?= e($content['highlight_2_title']) ?></strong><span><?= e($content['highlight_2_text']) ?></span></div>
<div><strong><?= e($content['highlight_3_title']) ?></strong><span><?= e($content['highlight_3_text']) ?></span></div>
</div>

<?php if ($admin): ?>
<?= $flash ?>
<?= content_editor('content-editor-inicio', 'Início e destaques', [
    'hero_title'       => ['label' => 'Título do herói', 'hint' => 'Pode usar <br> e <em>.'],
    'hero_description' => ['label' => 'Descrição do herói', 'type' => 'textarea', 'rows' => 3],
    'brand_name'       => ['label' => 'Nome da marca (cabeçalho)'],
    'brand_tagline'    => ['label' => 'Slogan (cabeçalho)'],
    'highlight_1_title' => ['label' => 'Destaque 1 — título'],
    'highlight_1_text' => ['label' => 'Destaque 1 — texto'],
    'highlight_2_title' => ['label' => 'Destaque 2 — título'],
    'highlight_2_text' => ['label' => 'Destaque 2 — texto'],
    'highlight_3_title' => ['label' => 'Destaque 3 — título'],
    'highlight_3_text' => ['label' => 'Destaque 3 — texto'],
], $content) ?>
<?php endif; ?>

<section id="produtos" class="section products" aria-labelledby="products-title">
<div class="products-head">
<h2 id="products-title">Produtos</h2>
<?php if ($admin): ?><button class="button yellow" id="btn-add-product" type="button">+ Adicionar produto</button><?php endif; ?>
</div>

<?php if ($admin): ?>
<div class="card-editor" id="product-editor" hidden>
<h3 id="product-editor-title">Adicionar produto</h3>
<form method="post" action="index.php" enctype="multipart/form-data">
<?= csrf_field() ?>
<input type="hidden" name="action" id="ped-action" value="save_product">
<input type="hidden" name="id" id="ped-id" value="0">
<div class="card-editor-grid">
<label>Nome do produto<input type="text" name="name" id="ped-name" placeholder="Ex.: Queijo fresco - 500g" required></label>
<label>Preço (R$)<input type="text" name="price" id="ped-price" inputmode="decimal" placeholder="Ex.: 20,00" required></label>
<label>Categoria<input type="text" name="category" id="ped-category" list="category-list" placeholder="Ex.: Queijos"><datalist id="category-list"><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?php endforeach; ?></datalist></label>
<label>Descrição (opcional)<input type="text" name="description" id="ped-description" placeholder="Breve descrição"></label>
<label>Enviar nova foto<input type="file" name="image_file" accept="image/png,image/jpeg,image/webp"></label>
<label>Ou usar imagem existente<select name="image" id="ped-image"><?= render_image_options('') ?></select></label>
<label class="check-field"><input type="checkbox" name="active" id="ped-active" value="1" checked> Produto visível no site</label>
</div>
<p class="field-hint" id="ped-slug-hint">O endereço da página do produto é gerado automaticamente a partir do nome.</p>
<div class="card-editor-actions">
<button class="login-submit" type="submit">Salvar</button>
<button class="login-submit danger" type="button" id="ped-delete" hidden>Excluir produto</button>
<button class="admin-cancel" type="button" id="ped-cancel">Cancelar</button>
</div>
</form>
</div>
<?php endif; ?>

<div class="product-tools">
<div class="category-filter" role="group" aria-label="Filtrar por categoria">
<button class="chip is-active" type="button" data-category="todos">Todos</button>
<?php foreach ($categories as $category): ?>
<button class="chip" type="button" data-category="<?= e($category) ?>"><?= e($category) ?></button>
<?php endforeach; ?>
</div>
<label class="search-field"><span class="sr-only">Buscar produto</span><input type="search" id="product-search" placeholder="Buscar produto..." autocomplete="off"></label>
</div>
<p class="empty-filter" id="empty-filter" hidden>Nenhum produto encontrado. Tente outra busca.</p>

<div class="product-grid" id="product-grid">
<?php foreach ($products as $product): ?>
<?php $isActive = (int) $product['active'] === 1; ?>
<article class="product-card<?= $isActive ? '' : ' is-inactive' ?>" data-category="<?= e((string) $product['category']) ?>" data-search="<?= e(mb_strtolower($product['name'] . ' ' . $product['description'] . ' ' . $product['category'], 'UTF-8')) ?>"<?php if ($admin): ?> data-id="<?= (int) $product['id'] ?>" data-name="<?= e($product['name']) ?>" data-price="<?= number_format((int) $product['price_cents'] / 100, 2, ',', '') ?>" data-description="<?= e($product['description']) ?>" data-image="<?= e($product['image']) ?>" data-category-edit="<?= e((string) $product['category']) ?>" data-active="<?= $isActive ? '1' : '0' ?>"<?php endif; ?>>
<?php if ($admin): ?><button class="card-edit" type="button" aria-label="Editar <?= e($product['name']) ?>">Editar</button><?php endif; ?>
<?php if (!$isActive): ?><span class="badge badge-off">Oculto</span><?php endif; ?>
<a class="product-link" href="produto.php?slug=<?= e((string) $product['slug']) ?>">
<?= render_picture((string) $product['image'], $product['description'] !== '' ? (string) $product['description'] : (string) $product['name'], ['sizes' => '(max-width: 640px) 44vw, (max-width: 1024px) 30vw, 260px']) ?>
</a>
<div class="product-info">
<h3><a href="produto.php?slug=<?= e((string) $product['slug']) ?>"><?= e($product['name']) ?></a></h3>
<p class="price"><?= format_price_cents((int) $product['price_cents']) ?></p>
</div>
<button class="add-to-cart" type="button"
    data-id="<?= (int) $product['id'] ?>"
    data-name="<?= e($product['name']) ?>"
    data-price="<?= (int) $product['price_cents'] ?>"
    data-image="img/<?= e($product['image']) ?>"
    data-url="produto.php?slug=<?= e((string) $product['slug']) ?>"
    <?= $isActive ? '' : 'disabled' ?>><?= $isActive ? 'Adicionar' : 'Indisponível' ?></button>
</article>
<?php endforeach; if (!$products): ?>
<article class="product-card"><div class="product-info"><h3>Produtos em breve</h3><p class="price">Em breve</p></div></article>
<?php endif; ?>
</div>
</section>

<section class="section about" aria-labelledby="about-title">
<?php if ($admin): ?><?= content_edit_button('content-editor-historia') ?><?php endif; ?>
<h2 id="about-title"><?= e($content['about_title']) ?></h2>
<div class="about-layout">
<div class="about-copy"><h3><?= e($content['about_heading']) ?></h3><?= content_paragraphs($content['about_text']) ?></div>
<?= render_picture((string) $content['about_image'], (string) $content['about_heading'], ['class' => 'family-photo', 'sizes' => '(max-width: 900px) 90vw, 480px']) ?>
</div>
</section>
<?php if ($admin): ?>
<?= content_editor('content-editor-historia', 'História', [
    'about_title'   => ['label' => 'Título da seção'],
    'about_heading' => ['label' => 'Manchete da história'],
    'about_text'    => ['label' => 'Texto da história', 'type' => 'textarea', 'rows' => 8, 'hint' => 'Separe os parágrafos com uma linha em branco.'],
    'about_image'   => ['label' => 'Foto da família', 'type' => 'image'],
], $content) ?>
<?php endif; ?>

<section id="como-comprar" class="section howto" aria-labelledby="howto-title">
<?php if ($admin): ?><?= content_edit_button('content-editor-comprar') ?><?php endif; ?>
<h2 id="howto-title"><?= e($content['orders_title']) ?></h2>
<ol class="steps">
<?php foreach (preg_split('/\r?\n/', $content['orders_steps']) ?: [] as $step): ?>
<?php $step = trim($step); if ($step === '') { continue; } ?>
<li><?= e($step) ?></li>
<?php endforeach; ?>
</ol>
<p class="delivery-note"><?= e($content['delivery_info']) ?></p>
<div class="howto-actions">
<a class="button yellow" href="checkout.php"><span class="bag-icon" aria-hidden="true"></span>Ir para o carrinho</a>
<a class="button glass dark" href="<?= e(whatsapp_url($content['phone_url'])) ?>" target="_blank" rel="noopener noreferrer"><?= whatsapp_icon('whatsapp-icon is-small') ?>Chamar no WhatsApp</a>
</div>
</section>
<?php if ($admin): ?>
<?= content_editor('content-editor-comprar', 'Como comprar', [
    'orders_title'  => ['label' => 'Título da seção'],
    'orders_steps'  => ['label' => 'Passos', 'type' => 'textarea', 'rows' => 5, 'hint' => 'Um passo por linha.'],
    'delivery_info' => ['label' => 'Aviso de entrega', 'type' => 'textarea', 'rows' => 3],
], $content) ?>
<?php endif; ?>

<?php $testimonials = content_testimonials($content['testimonials_items']); ?>
<?php if ($testimonials): ?>
<section class="section testimonials" aria-labelledby="testimonials-title">
<?php if ($admin): ?><?= content_edit_button('content-editor-depoimentos') ?><?php endif; ?>
<h2 id="testimonials-title"><?= e($content['testimonials_title']) ?></h2>
<div class="testimonial-grid">
<?php foreach ($testimonials as $testimonial): ?>
<blockquote class="testimonial">
<p>“<?= e($testimonial['text']) ?>”</p>
<footer><strong><?= e($testimonial['name']) ?></strong><?php if ($testimonial['city'] !== ''): ?><span><?= e($testimonial['city']) ?></span><?php endif; ?></footer>
</blockquote>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>
<?php if ($admin): ?>
<?= content_editor('content-editor-depoimentos', 'Depoimentos', [
    'testimonials_title' => ['label' => 'Título da seção'],
    'testimonials_items' => ['label' => 'Depoimentos', 'type' => 'textarea', 'rows' => 5, 'hint' => 'Um por linha no formato: Nome|Depoimento|Cidade.'],
], $content) ?>
<?php endif; ?>

<?php $faq = content_faq_items($content['faq_items']); ?>
<?php if ($faq): ?>
<section class="section faq" aria-labelledby="faq-title">
<?php if ($admin): ?><?= content_edit_button('content-editor-faq') ?><?php endif; ?>
<h2 id="faq-title"><?= e($content['faq_title']) ?></h2>
<div class="faq-list">
<?php foreach ($faq as $item): ?>
<details class="faq-item">
<summary><?= e($item['question']) ?></summary>
<p><?= e($item['answer']) ?></p>
</details>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>
<?php if ($admin): ?>
<?= content_editor('content-editor-faq', 'Perguntas frequentes', [
    'faq_title' => ['label' => 'Título da seção'],
    'faq_items' => ['label' => 'Perguntas', 'type' => 'textarea', 'rows' => 8, 'hint' => 'Uma por linha no formato: Pergunta|Resposta.'],
], $content) ?>
<?php endif; ?>

<section id="fazenda" class="section farm" aria-labelledby="farm-title">
<?php if ($admin): ?><?= content_edit_button('content-editor-fazenda') ?><?php endif; ?>
<h2 id="farm-title"><?= e($content['farm_title']) ?></h2>
<div class="farm-visual">
<?= render_picture((string) $content['farm_image'], (string) $content['farm_title'], ['sizes' => '(max-width: 900px) 90vw, 500px']) ?>
<div class="map-wrap"><iframe title="Mapa da região de <?= e($content['map_query']) ?>" src="<?= e(content_map_embed($content['map_query'])) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe><a href="<?= e(content_map_link($content['map_query'])) ?>" target="_blank" rel="noopener noreferrer">Ver região no mapa ↗</a></div>
</div>
<h3><?= sanitize_html($content['delivery_heading']) ?></h3>
<ul class="delivery-cities"><?php foreach (preg_split('/\r?\n/', $content['delivery_cities']) ?: [] as $city): ?><?php $city = trim($city); if ($city !== ''): ?><li><?= e($city) ?></li><?php endif; ?><?php endforeach; ?></ul>
</section>
<?php if ($admin): ?>
<?= content_editor('content-editor-fazenda', 'Fazenda, localização e entrega', [
    'farm_title'       => ['label' => 'Título da seção'],
    'farm_image'       => ['label' => 'Foto da fazenda', 'type' => 'image'],
    'map_query'        => ['label' => 'Localização no mapa', 'hint' => 'Cidade, endereço ou coordenadas usados no mapa e no link do Google Maps.'],
    'delivery_heading' => ['label' => 'Título da região de entrega', 'hint' => 'Pode usar <br>.'],
    'delivery_cities'  => ['label' => 'Cidades atendidas', 'type' => 'textarea', 'rows' => 6, 'hint' => 'Uma cidade por linha.'],
], $content) ?>
<?php endif; ?>
</main>

<footer id="contato" class="site-footer">
<?php if ($admin): ?><?= content_edit_button('content-editor-contato') ?><?php endif; ?>
<div class="footer-inner">
<h2><?= e($content['contact_title']) ?></h2>
<div class="contact-links">
<a href="<?= e($content['instagram_url']) ?>" target="_blank" rel="noopener noreferrer"><span class="instagram-icon" aria-hidden="true"></span><?= e($content['instagram_user']) ?></a>
<a href="<?= e(whatsapp_url($content['phone_url'])) ?>" target="_blank" rel="noopener noreferrer"><?= whatsapp_icon() ?><?= e($content['phone_display']) ?></a>
</div>
<p class="footer-legal">© <?= date('Y') ?> <?= e($content['brand_name']) ?> · Feito com carinho na fazenda.</p>
</div>
</footer>
<?php if ($admin): ?>
<?= content_editor('content-editor-contato', 'Contatos', [
    'contact_title'  => ['label' => 'Título da seção'],
    'instagram_user' => ['label' => 'Usuário do Instagram'],
    'instagram_url'  => ['label' => 'Link do Instagram'],
    'phone_display'  => ['label' => 'Telefone (texto exibido)'],
    'phone_url'      => ['label' => 'WhatsApp (link, ex.: https://wa.me/5519989873151)'],
], $content) ?>
<?php endif; ?>

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
