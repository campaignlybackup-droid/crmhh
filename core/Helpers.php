<?php
/**
 * Generic helper functions used throughout the app.
 */

$GLOBALS['__CONFIG'] = null;

function config(?string $key = null)
{
    if ($GLOBALS['__CONFIG'] === null) {
        $path = __DIR__ . '/../config/config.php';
        if (!file_exists($path)) {
            fatal_error('The application is not configured yet. Please run install.php first.');
        }
        $GLOBALS['__CONFIG'] = require $path;
    }
    if ($key === null) {
        return $GLOBALS['__CONFIG'];
    }
    return $GLOBALS['__CONFIG'][$key] ?? null;
}

function app_log(string $message): void
{
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($dir . '/app.log', $line, FILE_APPEND);
}

function fatal_error(string $userMessage): void
{
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Error</title>';
    echo '<style>body{font-family:system-ui,Arial,sans-serif;background:#f4f5f7;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;color:#333}
    .box{background:#fff;padding:32px 40px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);max-width:480px;text-align:center}
    h1{font-size:20px;margin:0 0 12px}</style></head><body><div class="box"><h1>Something went wrong</h1><p>' . e($userMessage) . '</p></div></body></html>';
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function old(string $key, $default = '')
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function url(string $page, array $params = []): string
{
    $params = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($params);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function format_date(?string $date, string $fmt = 'd M Y'): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '';
}

function format_datetime(?string $date, string $fmt = 'd M Y, h:i A'): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '';
}

function time_ago(?string $date): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return format_date($date);
}

function is_overdue(?string $deadline, string $status): bool
{
    if (!$deadline) return false;
    if (in_array($status, ['completed', 'cancelled'], true)) return false;
    return strtotime($deadline) < time();
}

function status_badge_class(string $status): string
{
    $map = [
        'not_started'    => 'secondary',
        'in_progress'    => 'primary',
        'pending_review' => 'warning',
        'completed'      => 'success',
        'blocked'        => 'danger',
        'cancelled'      => 'dark',
        'overdue'        => 'danger',
        'active'         => 'success',
        'inactive'       => 'secondary',
        'on_hold'        => 'warning',
        'pending'        => 'warning',
        'approved'       => 'success',
        'rejected'       => 'danger',
    ];
    return $map[$status] ?? 'secondary';
}

function humanize(string $value): string
{
    return ucwords(str_replace(['_', '-'], ' ', $value));
}

function paginate_params(int $totalRows, int $page, int $perPage): array
{
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return compact('totalRows', 'totalPages', 'page', 'perPage', 'offset');
}

function current_page_int(): int
{
    return isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
}

function query_string_without(array $keys): string
{
    $q = $_GET;
    foreach ($keys as $k) unset($q[$k]);
    return http_build_query($q);
}

function generate_code(string $prefix, int $number, int $pad = 6): string
{
    return $prefix . '-' . str_pad((string)$number, $pad, '0', STR_PAD_LEFT);
}

function next_code(string $table, string $column, string $prefix, int $pad = 6): string
{
    $max = Database::scalar("SELECT MAX(CAST(SUBSTRING($column, " . (strlen($prefix) + 2) . ") AS UNSIGNED)) FROM $table");
    $next = ((int)$max) + 1;
    return generate_code($prefix, $next, $pad);
}

function valid_email(?string $email): bool
{
    return $email !== null && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function normalize_phone(?string $phone): string
{
    if (!$phone) return '';
    $digits = preg_replace('/\D+/', '', $phone);
    // Keep last 10 digits for comparison (Indian numbers commonly carry +91/0 prefix)
    if (strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }
    return $digits;
}
