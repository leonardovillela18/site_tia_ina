<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

// Banco: pode ser sobrescrito por variavel de ambiente (testes/staging).
define('DB_PATH', (string) (getenv('DB_PATH') ?: __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'site.sqlite'));

// Pode ser sobrescrito por variavel de ambiente (usado no Docker).
define('BASE_URL', rtrim((string) (getenv('BASE_URL') ?: 'http://localhost:8888'), '/'));

define('APP_NAME', 'Produtos Artesanais Tia Iná');

define('PASSWORD_RESET_TTL', 30);

define('SMTP_HOST', (string) (getenv('SMTP_HOST') ?: 'smtp.gmail.com'));
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', (string) (getenv('SMTP_USER') ?: 'coloque-seu-email@gmail.com'));
define('SMTP_PASS', (string) (getenv('SMTP_PASS') ?: 'coloque-a-senha-de-app'));
define('MAIL_FROM', (string) (getenv('MAIL_FROM') ?: (getenv('SMTP_USER') ?: 'coloque-seu-email@gmail.com')));
define('MAIL_FROM_NAME', (string) (getenv('MAIL_FROM_NAME') ?: 'Site Tia Iná'));

define('CONTACT_PHONE', '(19) 98987-3151');
define('CONTACT_INSTAGRAM', 'https://www.instagram.com/artesanais.tia.ina/');

// Somente digitos, com DDI. Usado no checkout via WhatsApp.
define('WHATSAPP_NUMBER', (string) (getenv('WHATSAPP_NUMBER') ?: '5519989873151'));

// Versao dos assets (cache busting). Troque ao alterar CSS/JS.
define('ASSET_VERSION', '20261111d');

// Limites de tentativas (janela em segundos).
define('RATE_LIMIT_LOGIN_MAX', 5);
define('RATE_LIMIT_LOGIN_WINDOW', 900);
define('RATE_LIMIT_RECOVER_MAX', 3);
define('RATE_LIMIT_RECOVER_WINDOW', 3600);
