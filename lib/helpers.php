<?php
/**
 * lib/helpers.php — shared helpers: escaping, slugs, requests.
 */
declare(strict_types=1);

/** htmlspecialchars wrapper (UTF-8,ENT_QUOTES). */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Random opaque base62 string of length $n (random_int — CSPRNG). */
function random_base62(int $n): string
{
    $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $out = '';
    for ($i = 0; $i < $n; $i++) {
        $out .= $alphabet[random_int(0, 61)];
    }
    return $out;
}

/** Absolute app URL for share boxes (falls back to derived scheme://host). */
function app_url(): string
{
    $cfg = config();
    $base = $cfg['app_url'] ?? null;
    if (is_string($base) && $base !== '') {
        return rtrim($base, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/** Read a JSON request body (POST). Returns null on invalid. */
function json_body(): ?array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/** Emit a JSON response and exit. */
function json_out(array $payload, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Send X-Robots-Tag: noindex (poll + admin pages). */
function send_noindex(): void
{
    header('X-Robots-Tag: noindex, nofollow');
}

/** Render a template in views/. $vars are extracted. */
function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require __DIR__ . '/../views/' . $view;
}

/**
 * Bootstrap for page entry points: error handling (debug-aware),
 * config load check. Returns nothing; exits on missing config.
 */
function boot(): void
{
    $debug = false;
    try {
        $debug = (bool)(config()['debug'] ?? false);
    } catch (Throwable $e) {
        $debug = false;
    }
    error_reporting(E_ALL);
    ini_set('display_errors', $debug ? '1' : '0');
}