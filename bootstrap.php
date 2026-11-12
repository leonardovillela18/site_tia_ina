<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/images.php';
require_once __DIR__ . '/includes/content.php';
require_once __DIR__ . '/includes/product_actions.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/seo.php';

if (PHP_SAPI !== 'cli') {
    security_headers();

    // So cria sessao quando o visitante ja tem uma (admin/carrinho), evitando
    // cookie desnecessario para quem apenas navega no catalogo.
    if (isset($_COOKIE[session_name()])) {
        app_session_start();
    }
}

migrate();
