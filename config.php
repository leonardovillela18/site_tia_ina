<?php
declare(strict_types=1);

date_default_timezone_set('UTC');

define('DB_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'site.sqlite');

define('BASE_URL', 'http://localhost:8888');

define('APP_NAME', 'Produtos Artesanais Tia Iná');

define('PASSWORD_RESET_TTL', 30);

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'coloque-seu-email@gmail.com');
define('SMTP_PASS', 'coloque-a-senha-de-app');
define('MAIL_FROM', 'coloque-seu-email@gmail.com');
define('MAIL_FROM_NAME', 'Site Tia Iná');

define('CONTACT_PHONE', '(19) 98987-3151');
define('CONTACT_INSTAGRAM', 'https://www.instagram.com/artesanais.tia.ina/');