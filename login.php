<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

migrate_and_seed();

if (isset($_SESSION['user_id'])) {
    redirect('admin.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? null);

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE lower(username) = lower(?)');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        redirect('admin.php');
    }
    $error = 'Login ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | Tia Iná</title>
<link rel="icon" href="img/logo.png"><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/admin.css">
</head>
<body class="login-page">
<a class="back-link" href="index.php">← Voltar ao site</a>
<main class="login-main">
<a href="index.php" aria-label="Voltar ao início"><img class="login-logo" src="img/logo.png" alt="Produtos Artesanais Tia Iná" width="250" height="250"></a>
<h1 class="login-title">Área administrativa</h1>
<?= flash_render() ?>
<?php if ($error !== ''): ?><div class="flash flash-error" role="status"><?= e($error) ?></div><?php endif; ?>
<form class="login-form" method="post" action="login.php">
    <?= csrf_field() ?>
    <label class="sr-only" for="username">Usuário (login)</label>
    <input id="username" name="username" type="text" placeholder="LOGIN" autocomplete="username" autofocus required>
    <label class="sr-only" for="password">Senha</label>
    <input id="password" name="password" type="password" placeholder="SENHA" autocomplete="current-password" required>
    <button class="login-submit" type="submit">Entrar</button>
</form>
<p class="login-help"><a href="forgot.php">Esqueci minha senha</a></p>
</main>
</body></html>