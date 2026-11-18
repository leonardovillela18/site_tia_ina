<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

migrate_and_seed();

$token = trim($_GET['token'] ?? '');
$error = '';
$tokenValid = false;

if ($token !== '') {
    $stmt = db()->prepare('
        SELECT pr.*, u.username FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.used = 0
    ');
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch();
    if ($reset && strtotime($reset['expires_at']) > time()) {
        $tokenValid = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? null);

    $token = trim($_POST['token'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $stmt = db()->prepare('
        SELECT pr.*, u.id AS user_id FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.used = 0
    ');
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch();

    if (!$reset || strtotime($reset['expires_at']) <= time()) {
        $error = 'Este link de recuperação é inválido ou expirou. Solicite um novo.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha precisa ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'As senhas não conferem.';
    } else {
        $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);
        $mark = db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
        $mark->execute([(int) $reset['id']]);
        flash_set('success', 'Senha alterada com sucesso. Faça login com a nova senha.');
        redirect('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nova senha | Tia Iná</title>
<link rel="icon" href="img/logo.png"><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/admin.css">
</head>
<body class="login-page">
<a class="back-link" href="index.php">← Voltar ao site</a>
<main class="login-main">
<a href="index.php" aria-label="Voltar ao início"><img class="login-logo" src="img/logo.png" alt="Produtos Artesanais Tia Iná" width="250" height="250"></a>

<?php if ($tokenValid !== true): ?>
    <h1 class="login-title">Link inválido</h1>
    <div class="flash flash-error" role="status">Este link de recuperação é inválido ou expirou.</div>
    <p class="login-help"><a href="forgot.php">Solicitar novo link de recuperação</a></p>
<?php else: ?>
    <h1 class="login-title">Definir nova senha</h1>
    <?php if ($error !== ''): ?><div class="flash flash-error" role="status"><?= e($error) ?></div><?php endif; ?>
    <form class="login-form" method="post" action="reset.php">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="sr-only" for="password">Nova senha</label>
        <input id="password" name="password" type="password" placeholder="NOVA SENHA" autocomplete="new-password" minlength="6" required>
        <label class="sr-only" for="confirm_password">Confirmar nova senha</label>
        <input id="confirm_password" name="confirm_password" type="password" placeholder="CONFIRMAR NOVA SENHA" autocomplete="new-password" minlength="6" required>
        <button class="login-submit" type="submit">Salvar nova senha</button>
    </form>
    <p class="login-help"><a href="login.php">← Voltar ao login</a></p>
<?php endif; ?>
</main>
</body></html>