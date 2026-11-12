<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
app_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? null);
    $admin = require_admin();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_admin') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        try {
            if ($username === '' || $email === '' || strlen($password) < 6) {
                throw new RuntimeException('Preencha todos os campos (senha com pelo menos 6 caracteres).');
            }
            if (!require_email($email)) {
                throw new RuntimeException('E-mail inválido.');
            }
            $stmt = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            flash_set('success', 'Administrador cadastrado.');
        } catch (PDOException) {
            flash_set('error', 'Já existe um administrador com esse usuário ou e-mail.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }
    } elseif ($action === 'update_user') {
        $id       = (int) ($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm_password'] ?? '');

        if ($username === '') {
            flash_set('error', 'Informe o usuário.');
        } elseif (!require_email($email)) {
            flash_set('error', 'Informe um e-mail válido.');
        } elseif ($password !== '' && (strlen($password) < 6 || $password !== $confirm)) {
            flash_set('error', 'Para trocar a senha, informe pelo menos 6 caracteres nos dois campos.');
        } else {
            $check = db()->prepare('SELECT id FROM users WHERE lower(username) = lower(?) AND id != ?');
            $check->execute([$username, $id]);
            $duplicate = $check->fetch();
            if (!$duplicate) {
                $check = db()->prepare('SELECT id FROM users WHERE lower(email) = lower(?) AND id != ?');
                $check->execute([$email, $id]);
                $duplicate = $check->fetch();
            }
            if ($duplicate) {
                flash_set('error', 'Já existe um administrador com esse usuário ou e-mail.');
            } else {
                if ($password !== '') {
                    $stmt = db()->prepare('UPDATE users SET username = ?, email = ?, password_hash = ? WHERE id = ?');
                    $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = db()->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
                    $stmt->execute([$username, $email, $id]);
                }
                if ($id === (int) $admin['id']) {
                    $_SESSION['username'] = $username;
                }
                flash_set('success', 'Administrador atualizado.');
            }
        }
    } elseif ($action === 'delete_admin') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $admin['id']) {
            flash_set('error', 'Você não pode excluir a própria conta.');
        } else {
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            flash_set('success', 'Administrador removido.');
        }
    } elseif ($action === 'update_product_quick') {
        $id = (int) ($_POST['id'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $category = trim((string) ($_POST['category'] ?? ''));
        if ($id > 0 && $category !== '') {
            $stmt = db()->prepare('UPDATE products SET active = ?, category = ?, updated_at = datetime("now") WHERE id = ?');
            $stmt->execute([$active, $category, $id]);
            flash_set('success', 'Produto atualizado.');
        } else {
            flash_set('error', 'Produto inválido.');
        }
    }

    redirect('admin.php');
}

$admin = require_admin();
$editUser = null;

$stmt = db()->query('SELECT * FROM users ORDER BY username COLLATE NOCASE');
$admins = $stmt->fetchAll();

if (isset($_GET['edit_user'])) {
    $id = (int) $_GET['edit_user'];
    foreach ($admins as $user) {
        if ((int) $user['id'] === $id) {
            $editUser = $user;
            break;
        }
    }
}

$products = load_products(false);
$categories = product_categories();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Painel administrativo | Tia Iná</title>
<link rel="icon" href="img/logo.png"><link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="admin-page">
<header class="admin-header">
    <a class="admin-brand" href="admin.php"><?= render_picture('logo.png', 'Tia Iná', ['widths' => [160], 'sizes' => '40px', 'width' => 40, 'height' => 40]) ?><span><strong>Painel</strong><small>Saudações, <?= e($admin['username']) ?></small></span></a>
    <nav class="admin-nav">
        <a href="index.php">Ver site</a>
        <a href="admin.php#produtos">Produtos</a>
        <a href="logout.php">Sair</a>
    </nav>
</header>
<main class="admin-main">
    <?= flash_render() ?>

    <!-- ============ PRODUTOS ============ -->
    <section class="admin-section" id="produtos">
        <div class="admin-row">
            <h2>Produtos</h2>
            <a class="admin-link-button" href="index.php#produtos">Gerenciar no site</a>
        </div>
        <p class="admin-hint">Ajuste rapidamente a categoria e a visibilidade. Para foto, descrição e preço, use “Gerenciar no site”.</p>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Produto</th><th>Preço</th><th>Categoria e visibilidade</th></tr></thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <strong><?= e($product['name']) ?></strong><br>
                            <small><?= e((string) $product['category']) ?> · <?= e((string) $product['slug']) ?></small>
                        </td>
                        <td><?= format_price_cents((int) $product['price_cents']) ?></td>
                        <td>
                            <form method="post" action="admin.php" class="quick-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_product_quick">
                                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                <label>Categoria
                                    <input type="text" name="category" list="admin-category-list" value="<?= e((string) $product['category']) ?>">
                                </label>
                                <label class="check-field"><input type="checkbox" name="active" value="1" <?= (int) $product['active'] === 1 ? 'checked' : '' ?>> Visível</label>
                                <button class="table-link" type="submit">Salvar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <datalist id="admin-category-list">
                <?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?php endforeach; ?>
            </datalist>
        </div>
    </section>

    <!-- ============ ADMINISTRADORES ============ -->
    <section class="admin-section" id="usuarios">
        <h1>Usuários administrativos</h1>

        <h3 class="admin-form-title">Cadastrar usuário</h3>
        <form class="product-form" method="post" action="admin.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_admin">
            <div class="form-grid">
                <label>Usuário <input type="text" name="username" placeholder="Ex.: tia.ina" required></label>
                <label>E-mail <input type="email" name="email" placeholder="email@exemplo.com" required></label>
                <label>Senha <input type="password" name="password" placeholder="Mínimo 6 caracteres" minlength="6" required></label>
            </div>
            <div class="form-actions"><button class="login-submit" type="submit">Cadastrar usuário</button></div>
        </form>

        <h3 class="admin-form-title">Editar usuário</h3>
        <form class="product-form" method="get" action="admin.php" id="user-edit-select">
            <div class="form-grid">
                <label>Escolha o usuário</label>
                <select name="edit_user" data-auto-submit>
                    <option value="">Selecione...</option>
                    <?php foreach ($admins as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= $editUser && (int) $editUser['id'] === (int) $user['id'] ? 'selected' : '' ?>>
                            <?= e($user['username']) ?><?= (int) $user['id'] === (int) $admin['id'] ? ' (você)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($editUser): ?>
            <form class="product-form" method="post" action="admin.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>">
                <div class="form-grid">
                    <label>Usuário (login) <input type="text" name="username" value="<?= e($editUser['username']) ?>" required></label>
                    <label>E-mail (para recuperação de senha) <input type="email" name="email" value="<?= e($editUser['email']) ?>" required></label>
                    <label>Nova senha (opcional) <input type="password" name="password" placeholder="Deixe em branco para manter" minlength="6"></label>
                    <label>Confirmar nova senha <input type="password" name="confirm_password" placeholder="Repita a nova senha"></label>
                </div>
                <div class="form-actions">
                    <button class="login-submit" type="submit">Salvar alterações</button>
                    <a class="admin-cancel" href="admin.php">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>

        <h3>Lista de usuários</h3>
        <ul class="admin-userlist">
            <?php foreach ($admins as $user): ?>
                <li>
                    <span>
                        <strong><?= e($user['username']) ?></strong><?= (int) $user['id'] === (int) $admin['id'] ? ' <small class="admin-self">(você)</small>' : '' ?>
                        <br><small><?= e($user['email']) ?></small>
                    </span>
                    <span class="userlist-actions">
                        <form method="get" action="admin.php" class="inline-form">
                            <input type="hidden" name="edit_user" value="<?= (int) $user['id'] ?>">
                            <button class="table-link" type="submit">Editar</button>
                        </form>
                        <?php if ((int) $user['id'] !== (int) $admin['id']): ?>
                            <form method="post" action="admin.php" class="inline-form inline-confirm">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_admin">
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <button class="table-link danger" type="submit" data-confirm="Remover o administrador &quot;<?= e($user['username']) ?>&quot;?">Remover</button>
                            </form>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
</body>
</html>
