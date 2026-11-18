<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/product_actions.php';
require __DIR__ . '/includes/content.php';

migrate_and_seed();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? null);
    if (($_POST['action'] ?? '') === 'save_content') {
        handle_content_save();
    }
    process_product_request();
    redirect('index.php');
}

$admin = current_admin();
$products = load_products();
$content = content_all();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e(mb_strimwidth(strip_tags($content['hero_description']), 0, 150, '…')) ?>">
<title>Produtos Artesanais Tia Iná</title><link rel="icon" href="img/logo.png"><link rel="stylesheet" href="css/style.css"><script src="js/main.js" defer></script>
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
<a class="brand" href="#inicio"><img src="img/logo.png" alt="Tia Iná" width="74" height="74"><span><strong><?= e($content['brand_name']) ?></strong><small><?= e($content['brand_tagline']) ?></small></span></a>
<button class="menu-toggle" type="button" aria-label="Abrir menu" aria-controls="navigation" aria-expanded="false">☰</button>
<nav id="navigation" aria-label="Navegação principal"><a class="active" href="#inicio" aria-current="location">Início</a><a href="#produtos">Produtos</a><a href="#fazenda">A fazenda</a><a href="#contato">Contato</a></nav>
<a class="login-link" href="login.php" aria-label="Acessar tela de login" title="Entrar"><span class="user-icon" aria-hidden="true"></span></a>
</header>
<main id="conteudo">
<section id="inicio" class="hero" aria-labelledby="hero-title"><?php if ($admin): ?><?= content_edit_button('content-editor-inicio') ?><?php endif; ?><div class="hero-inner"><h1 id="hero-title"><?= $content['hero_title'] ?></h1><p class="hero-description"><?= $content['hero_description'] ?></p><div class="hero-actions"><a class="button yellow" href="#produtos"><span class="bag-icon" aria-hidden="true"></span>Ver produtos</a><a class="button glass" href="#contato"><span class="chat-icon" aria-hidden="true"></span>Fale conosco</a></div></div></section>
<div class="highlights"><div><strong><?= e($content['highlight_1_title']) ?></strong><span><?= e($content['highlight_1_text']) ?></span></div><div><strong><?= e($content['highlight_2_title']) ?></strong><span><?= e($content['highlight_2_text']) ?></span></div><div><strong><?= e($content['highlight_3_title']) ?></strong><span><?= e($content['highlight_3_text']) ?></span></div></div>
<?php if ($admin): ?>
<?= flash_render() ?>
<?= content_editor('content-editor-inicio', 'Início e destaques', [
    'hero_title'       => ['label' => 'Título do herói', 'hint' => 'Pode usar tags como <br> e <em>.'],
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
<section class="section about" aria-labelledby="about-title"><?php if ($admin): ?><?= content_edit_button('content-editor-historia') ?><?php endif; ?><h2 id="about-title"><?= e($content['about_title']) ?></h2><div class="about-layout"><div class="about-copy"><h3><?= e($content['about_heading']) ?></h3><?= content_paragraphs($content['about_text']) ?></div><img class="family-photo" src="img/<?= e($content['about_image']) ?>" alt="<?= e($content['about_heading']) ?>" width="1254" height="1254" loading="lazy"></div></section>
<?php if ($admin): ?>
<?= content_editor('content-editor-historia', 'História', [
    'about_title'   => ['label' => 'Título da seção'],
    'about_heading' => ['label' => 'Manchete da história'],
    'about_text'    => ['label' => 'Texto da história', 'type' => 'textarea', 'rows' => 8, 'hint' => 'Separe os parágrafos com uma linha em branco.'],
    'about_image'   => ['label' => 'Foto da família', 'type' => 'image'],
], $content) ?>
<?php endif; ?>
<section id="produtos" class="section products" aria-labelledby="products-title">
<div class="products-head"><h2 id="products-title">Produtos</h2>
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
<label>Descrição (opcional)<input type="text" name="description" id="ped-description" placeholder="Breve descrição"></label>
<label>Enviar nova foto<input type="file" name="image_file" accept="image/png,image/jpeg,image/webp"></label>
<label>Ou usar imagem existente<select name="image" id="ped-image"><?= render_image_options('') ?></select></label>
</div>
<div class="card-editor-actions">
<button class="login-submit" type="submit">Salvar</button>
<button class="login-submit danger" type="button" id="ped-delete" hidden>Excluir produto</button>
<button class="admin-cancel" type="button" id="ped-cancel">Cancelar</button>
</div>
</form>
</div>
<?php endif; ?>
<div class="product-grid">
<?php foreach ($products as $product): ?>
<article class="product-card"<?php if ($admin): ?> data-id="<?= (int) $product['id'] ?>" data-name="<?= e($product['name']) ?>" data-price="<?= number_format((int) $product['price_cents'] / 100, 2, ',', '') ?>" data-description="<?= e($product['description']) ?>" data-image="<?= e($product['image']) ?>"<?php endif; ?>><?php if ($admin): ?><button class="card-edit" type="button" aria-label="Editar <?= e($product['name']) ?>">Editar</button><?php endif; ?><img src="img/<?= e($product['image']) ?>" alt="<?= e($product['description'] !== '' ? $product['description'] : $product['name']) ?>" width="1254" height="1254" loading="lazy"><button class="cart-button" data-product="<?= e($product['name']) ?>" aria-label="Consultar <?= e($product['name']) ?>"><span class="cart-icon" aria-hidden="true"></span></button><div class="product-info"><h3><?= e($product['name']) ?></h3><p><?= format_price_cents((int) $product['price_cents']) ?></p></div></article>
<?php endforeach; if (!$products): ?>
<article class="product-card"><div class="product-info"><h3>Produtos em breve</h3><p>Em breve</p></div></article>
<?php endif; ?>
</div></section>
<section id="fazenda" class="section farm" aria-labelledby="farm-title"><?php if ($admin): ?><?= content_edit_button('content-editor-fazenda') ?><?php endif; ?><h2 id="farm-title"><?= e($content['farm_title']) ?></h2><div class="farm-visual"><img src="img/<?= e($content['farm_image']) ?>" alt="<?= e($content['farm_title']) ?>" width="1254" height="1254" loading="lazy"><div class="map-wrap"><iframe title="Mapa da região de <?= e($content['map_query']) ?>" src="<?= e(content_map_embed($content['map_query'])) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe><a href="<?= e(content_map_link($content['map_query'])) ?>" target="_blank" rel="noopener noreferrer">Ver região no mapa ↗</a></div></div><h3><?= $content['delivery_heading'] ?></h3><ul class="delivery-cities"><?php foreach (preg_split('/\r?\n/', $content['delivery_cities']) ?: [] as $city): ?><?php $city = trim($city); if ($city !== ''): ?><li><?= e($city) ?></li><?php endif; ?><?php endforeach; ?></ul></section>
<?php if ($admin): ?>
<?= content_editor('content-editor-fazenda', 'Fazenda, localização e entrega', [
    'farm_title'       => ['label' => 'Título da seção'],
    'farm_image'       => ['label' => 'Foto da fazenda', 'type' => 'image'],
    'map_query'        => ['label' => 'Localização no mapa', 'hint' => 'Cidade, endereço ou coordenadas usados no mapa e no link do Google Maps.'],
    'delivery_heading' => ['label' => 'Título da região de entrega', 'hint' => 'Pode usar HTML leve, como <br>.'],
    'delivery_cities'  => ['label' => 'Cidades atendidas', 'type' => 'textarea', 'rows' => 6, 'hint' => 'Uma cidade por linha.'],
], $content) ?>
<?php endif; ?>
</main>
<footer id="contato" class="site-footer"><?php if ($admin): ?><?= content_edit_button('content-editor-contato') ?><?php endif; ?><div class="footer-inner"><h2><?= e($content['contact_title']) ?></h2><div class="contact-links"><a href="<?= e($content['instagram_url']) ?>" target="_blank" rel="noopener noreferrer"><span class="instagram-icon" aria-hidden="true"></span><?= e($content['instagram_user']) ?></a><a href="<?= e($content['phone_url']) ?>"><span class="phone-icon" aria-hidden="true">♧</span><?= e($content['phone_display']) ?></a></div></div></footer>
<?php if ($admin): ?>
<?= content_editor('content-editor-contato', 'Contatos', [
    'contact_title'  => ['label' => 'Título da seção'],
    'instagram_user' => ['label' => 'Usuário do Instagram'],
    'instagram_url'  => ['label' => 'Link do Instagram'],
    'phone_display'  => ['label' => 'Telefone (texto exibido)'],
    'phone_url'      => ['label' => 'Telefone (link, ex.: tel:+5519989873151)'],
], $content) ?>
<?php endif; ?>
<dialog id="product-dialog" aria-labelledby="selected-product"><button class="dialog-close" type="button" aria-label="Fechar">×</button><h2 id="selected-product">Produto</h2><p>Gostou desse sabor da fazenda? Fale com a gente para consultar a disponibilidade e combinar seu pedido.</p><a class="button yellow" id="product-contact" href="#contato">Ver contatos</a></dialog>
</body></html>
