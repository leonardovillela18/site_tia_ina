<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli' && http_response_code() === 200) {
    http_response_code(404);
}

$content = content_all();
$admin = current_admin();
$meta = [
    'title'       => 'Página não encontrada | ' . APP_NAME,
    'description' => 'A página que você procura não existe ou foi movida. Veja os produtos artesanais da Tia Iná.',
    'canonical'   => url('404.php'),
    'noindex'     => true,
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
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
</head>
<body data-page="404">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
<a class="brand" href="index.php"><?= render_picture('logo.png', 'Tia Iná', ['widths' => [160, 320], 'sizes' => '74px', 'width' => 74, 'height' => 74]) ?><span><strong><?= e($content['brand_name']) ?></strong><small><?= e($content['brand_tagline']) ?></small></span></a>
<button class="menu-toggle" type="button" aria-label="Abrir menu" aria-controls="navigation" aria-expanded="false">☰</button>
<nav id="navigation" aria-label="Navegação principal">
<a href="index.php">Início</a>
<a href="index.php#produtos">Produtos</a>
<a href="index.php#como-comprar">Como comprar</a>
<a href="index.php#contato">Contato</a>
<?php if ($admin): ?><a class="nav-admin" href="admin.php">Painel</a><?php endif; ?>
</nav>
<a class="login-link" href="login.php" aria-label="Acessar área administrativa" title="Entrar"><span class="user-icon" aria-hidden="true"></span></a>
</header>

<main id="conteudo" class="notfound-page">
<p class="notfound-code" aria-hidden="true">404</p>
<h1>Ops! Não encontramos esta página</h1>
<p class="notfound-text">O endereço pode ter mudado ou o produto não está mais disponível. Que tal conhecer nossas delícias artesanais?</p>
<div class="notfound-actions">
<a class="button yellow" href="index.php#produtos"><span class="bag-icon" aria-hidden="true"></span>Ver produtos</a>
<a class="button glass dark" href="index.php">Voltar ao início</a>
</div>
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
</body>
</html>
