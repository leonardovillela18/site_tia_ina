<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $token): void
{
    if (empty($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Sessão expirada: recarregue a página e tente novamente.');
    }
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flash_render(): string
{
    $flash = flash_get();
    if (!$flash) {
        return '';
    }
    $class = $flash['type'] === 'error' ? 'flash-error' : 'flash-success';
    return '<div class="flash ' . $class . '" role="status">' . e($flash['message']) . '</div>';
}

function current_admin(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('login.php');
    }
    return $admin;
}

function format_price_cents(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function parse_price_to_cents(string $raw): int
{
    $raw = mb_ereg_replace('R\$', '', $raw);
    $lastComma = strrpos($raw, ',');
    $lastDot = strrpos($raw, '.');
    if ($lastComma !== false && $lastComma > $lastDot) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    } else {
        $raw = str_replace(',', '', $raw);
    }
    $raw = mb_ereg_replace('[^0-9.]', '', $raw);
    return (int) round(((float) $raw) * 100);
}

function require_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}