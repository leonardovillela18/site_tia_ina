<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/mailer.php';
app_session_start();

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? null);
    $email = trim($_POST['email'] ?? '');
    $ip = client_ip();

    if (rate_limit_exceeded('recover', $ip, RATE_LIMIT_RECOVER_MAX, RATE_LIMIT_RECOVER_WINDOW)) {
        $minutes = rate_limit_wait_minutes('recover', $ip, RATE_LIMIT_RECOVER_MAX, RATE_LIMIT_RECOVER_WINDOW);
        $error = 'Muitos pedidos de recuperação. Tente novamente em ' . $minutes . ' minuto(s).';
    } elseif (!require_email($email)) {
        $error = 'Informe um e-mail válido.';
    } else {
        rate_limit_hit('recover', $ip, $email);

        $stmt = db()->prepare('SELECT * FROM users WHERE lower(email) = lower(?)');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = gmdate('Y-m-d H:i:s', time() + PASSWORD_RESET_TTL * 60);

            $prepare = db()->prepare('DELETE FROM password_resets WHERE user_id = ? AND used = 0');
            $prepare->execute([(int) $user['id']]);

            $insert = db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
            $insert->execute([(int) $user['id'], hash('sha256', $token), $expires]);

            $link = BASE_URL . '/reset.php?token=' . $token;
            try {
                send_recovery_email($email, $link);
            } catch (Throwable $exception) {
                error_log('Recuperação de senha (' . $email . '): ' . $exception->getMessage());
            }
        }
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Recuperar senha | Tia Iná</title>
<link rel="icon" href="img/logo.png"><link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="login-page">
<a class="back-link" href="index.php">← Voltar ao site</a>
<main class="login-main">
<a href="index.php" aria-label="Voltar ao início"><?= render_picture('logo.png', 'Produtos Artesanais Tia Iná', ['class' => 'login-logo', 'widths' => [160, 320, 480], 'sizes' => '250px', 'loading' => 'eager']) ?></a>
<h1 class="login-title">Recuperar senha</h1>

<?php if ($sent): ?>
    <div class="flash flash-success" role="status">Se o e-mail informado estiver cadastrado, você receberá o link de recuperação em instantes.</div>
    <p class="login-help"><a href="login.php">Voltar ao login</a></p>
<?php else: ?>
    <?php if ($error !== ''): ?><div class="flash flash-error" role="status"><?= e($error) ?></div><?php endif; ?>
    <form class="login-form" method="post" action="forgot.php">
        <?= csrf_field() ?>
        <label class="sr-only" for="email">E-mail</label>
        <input id="email" name="email" type="email" placeholder="SEU E-MAIL CADASTRADO" autocomplete="email" required>
        <button class="login-submit" type="submit">Enviar link de recuperação</button>
    </form>
    <p class="login-help"><a href="login.php">← Voltar ao login</a></p>
<?php endif; ?>
</main>
</body></html>
