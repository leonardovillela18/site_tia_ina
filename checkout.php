<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
app_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
        json_response(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página e tente novamente.'], 419);
    }

    $ip = client_ip();
    if (rate_limit_exceeded('order', $ip, 10, 3600)) {
        json_response(['ok' => false, 'error' => 'Muitos pedidos em pouco tempo. Tente novamente mais tarde.'], 429);
    }

    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    try {
        $result = create_order($data, $data['items'] ?? []);
    } catch (RuntimeException $exception) {
        json_response(['ok' => false, 'error' => $exception->getMessage()], 422);
    } catch (Throwable $exception) {
        error_log('Erro ao criar pedido: ' . $exception->getMessage());
        json_response(['ok' => false, 'error' => 'Não foi possível registrar o pedido. Tente novamente.'], 500);
    }

    rate_limit_hit('order', $ip);
    json_response([
        'ok'       => true,
        'order_id' => (int) $result['order']['id'],
        'total'    => format_price_cents((int) $result['order']['total_cents']),
        'whatsapp' => order_whatsapp_url($result['order'], $result['items']),
    ]);
}

$content = content_all();
$admin = current_admin();
$meta = [
    'title'       => 'Finalizar pedido | ' . APP_NAME,
    'description' => 'Revise seu carrinho, informe seus dados e envie o pedido para a Tia Iná pelo WhatsApp.',
    'canonical'   => url('checkout.php'),
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
<script src="<?= e(asset('js/checkout.js')) ?>" defer></script>
</head>
<body data-page="checkout" data-csrf="<?= e(csrf_token()) ?>">
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

<main id="conteudo" class="checkout-page">
<nav class="breadcrumb" aria-label="Você está aqui">
<a href="index.php">Início</a><span aria-hidden="true">›</span>
<span aria-current="page">Finalizar pedido</span>
</nav>

<h1 class="checkout-title">Finalizar pedido</h1>
<p class="checkout-lead">Confira os itens, informe seus dados e envie o pedido. A confirmação e a entrega são combinadas pelo WhatsApp.</p>

<div class="checkout-layout" id="checkout-layout">
<section class="checkout-form-card" aria-labelledby="dados-title">
<h2 id="dados-title">Seus dados</h2>
<form id="checkout-form" novalidate>
<div class="form-grid">
<label class="field-full">Nome completo
<input type="text" name="name" id="co-name" autocomplete="name" required placeholder="Como podemos te chamar?">
</label>
<label>WhatsApp / telefone
<input type="tel" name="phone" id="co-phone" autocomplete="tel" inputmode="tel" required placeholder="(19) 99999-9999">
</label>
<label>Como prefere receber?
<select name="delivery" id="co-delivery">
<option value="entrega">Entrega</option>
<option value="retirada">Retirar na fazenda</option>
</select>
</label>
<label class="field-full">Endereço de entrega
<textarea name="address" id="co-address" rows="3" autocomplete="street-address" placeholder="Rua, número, bairro e cidade"></textarea>
</label>
<label class="field-full">Observações (opcional)
<textarea name="note" id="co-note" rows="2" placeholder="Ex.: entregar no período da tarde"></textarea>
</label>
</div>
<p class="form-error" id="checkout-error" role="alert" hidden></p>
<button class="button yellow checkout-submit" type="submit" id="checkout-submit">Enviar pedido pelo WhatsApp</button>
<p class="checkout-privacy">Seus dados são usados apenas para preparar e entregar o pedido.</p>
</form>
</section>

<aside class="checkout-summary" aria-labelledby="resumo-title">
<h2 id="resumo-title">Resumo</h2>
<div id="checkout-items" class="checkout-items"><p class="cart-empty">Carregando...</p></div>
<div class="checkout-total">Total <strong id="checkout-total">R$ 0,00</strong></div>
<p class="cart-note">O valor do frete (se houver) é combinado no WhatsApp.</p>
<a class="back-to-products" href="index.php#produtos">← Continuar comprando</a>
</aside>
</div>

<section class="checkout-success" id="checkout-success" hidden aria-live="polite">
<span class="success-badge">Pedido registrado</span>
<h2>Obrigado, <span id="success-name">cliente</span>!</h2>
<p>Seu pedido <strong id="success-order">#0</strong> foi salvo com o total de <strong id="success-total">R$ 0,00</strong>.</p>
<p>Agora envie a mensagem no WhatsApp para confirmar a disponibilidade e combinar a entrega.</p>
<a class="button yellow" id="success-whatsapp" href="#" target="_blank" rel="noopener noreferrer">Abrir WhatsApp e confirmar</a>
<a class="back-to-products" href="index.php">Voltar ao site</a>
</section>
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
