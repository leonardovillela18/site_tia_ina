<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return $path . '?v=' . ASSET_VERSION;
}

/**
 * Converte um telefone/link antigo (tel:) em link do WhatsApp.
 * Links http(s) já configurados são mantidos como estão.
 */
function whatsapp_url(?string $value = null): string
{
    $value = trim((string) $value);
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits === '') {
        $digits = preg_replace('/\D+/', '', WHATSAPP_NUMBER) ?? WHATSAPP_NUMBER;
    }
    // Número local (DDD + número) recebe o DDI do Brasil.
    if (strlen($digits) <= 11) {
        $digits = '55' . $digits;
    }

    return 'https://wa.me/' . $digits;
}

/**
 * Ícone do WhatsApp em SVG (usa currentColor para acompanhar o texto).
 */
function whatsapp_icon(string $class = 'whatsapp-icon'): string
{
    $path = 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z';

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" width="40" height="40" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $path . '"/></svg>';
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Nonce usado no CSP para permitir somente os scripts JSON-LD gerados pelo servidor.
 */
function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

/**
 * Envia cabecalhos de seguranca. A CSP tambem pode ser reforcada no nginx.
 */
function security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'nonce-" . csp_nonce() . "'; frame-src https://maps.google.com https://www.google.com; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
}

function csrf_token(): string
{
    app_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $token): void
{
    app_session_start();
    if (empty($_SESSION['csrf_token']) || !is_string($token) || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Sessão expirada: recarregue a página e tente novamente.');
    }
}

function flash_set(string $type, string $message): void
{
    app_session_start();
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
    return '<div class="flash ' . $class . '" role="status">' . e((string) $flash['message']) . '</div>';
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
    app_session_start();
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
    $raw = mb_ereg_replace('R\$', '', $raw) ?? $raw;
    $lastComma = strrpos($raw, ',');
    $lastDot = strrpos($raw, '.');
    if ($lastComma !== false && $lastComma > $lastDot) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    } else {
        $raw = str_replace(',', '', $raw);
    }
    $raw = mb_ereg_replace('[^0-9.]', '', $raw) ?? '0';
    return (int) round(((float) $raw) * 100);
}

function require_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function slugify(string $value): string
{
    $value = trim($value);
    // Transliteracao explicita: o iconv de alguns sistemas (Windows) nao
    // converte acentos corretamente e acabaria gerando hifens no lugar.
    $map = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ç' => 'c', 'Ç' => 'C', 'ñ' => 'n', 'Ñ' => 'N', 'ý' => 'y', 'Ý' => 'Y',
    ];
    $value = strtr($value, $map);
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }
    }
    $value = mb_strtolower($value, 'UTF-8');
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

/**
 * Gera um slug unico. $used evita colisoes dentro de uma mesma migracao/importacao.
 */
function unique_slug(string $name, int $id, array &$used = []): string
{
    $base = slugify($name);
    if ($base === '') {
        $base = 'produto';
    }
    $slug = $base;
    if (isset($used[$slug])) {
        $slug = $base . '-' . max(1, $id);
    }
    while (isset($used[$slug])) {
        $slug = $base . '-' . max(1, $id) . '-' . bin2hex(random_bytes(2));
    }
    $used[$slug] = true;
    return $slug;
}

/**
 * Permite apenas uma lista curta de tags no conteudo editavel (evita XSS armazenado).
 */
function sanitize_html(?string $html): string
{
    $raw = (string) $html;
    // Remove o conteudo de tags perigosas (strip_tags sozinho manteria o texto).
    $raw = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $raw) ?? '';
    $raw = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*/?>#is', '', $raw) ?? '';
    $allowed = '<br><br/><em><strong><b><i><span>';
    $clean = strip_tags($raw, $allowed);
    return preg_replace('/<(br|em|strong|b|i|span)\b[^>]*>/i', '<$1>', $clean) ?? '';
}

function client_ip(): string
{
    $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwarded !== '') {
        $first = trim(explode(',', $forwarded)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

function rate_limit_count(string $action, string $ip, int $window): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE action = ? AND ip = ? AND created_at >= ?');
    $stmt->execute([$action, $ip, time() - $window]);
    return (int) $stmt->fetchColumn();
}

function rate_limit_exceeded(string $action, string $ip, int $max, int $window): bool
{
    return rate_limit_count($action, $ip, $window) >= $max;
}

function rate_limit_hit(string $action, string $ip, string $identifier = ''): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (action, identifier, ip, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$action, mb_substr($identifier, 0, 190), $ip, time()]);

    // Limpeza oportunista: mantem a tabela pequena.
    if (random_int(1, 20) === 1) {
        db()->exec('DELETE FROM login_attempts WHERE created_at < ' . (time() - 7 * 86400));
    }
}

function rate_limit_clear(string $action, string $ip): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE action = ? AND ip = ?');
    $stmt->execute([$action, $ip]);
}

function rate_limit_wait_minutes(string $action, string $ip, int $max, int $window): int
{
    $stmt = db()->prepare('SELECT MIN(created_at) FROM login_attempts WHERE action = ? AND ip = ? AND created_at >= ?');
    $stmt->execute([$action, $ip, time() - $window]);
    $oldest = (int) ($stmt->fetchColumn() ?: time());
    return max(1, (int) ceil(($oldest + $window - time()) / 60));
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function order_statuses(): array
{
    return [
        'novo'      => 'Novo',
        'preparo'   => 'Em preparo',
        'entregue'  => 'Entregue',
        'cancelado' => 'Cancelado',
    ];
}

function order_status_label(string $status): string
{
    return order_statuses()[$status] ?? $status;
}

/**
 * Converte o horario UTC gravado pelo SQLite para o fuso de Sao Paulo.
 */
function format_datetime_local(string $utc): string
{
    try {
        $date = new DateTime($utc, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $date->format('d/m/Y H:i');
    } catch (Throwable $exception) {
        return $utc;
    }
}
