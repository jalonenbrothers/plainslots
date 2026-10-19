<?php
/**
 * router.php — `php -S localhost:8080 router.php` dev router.
 * Mirrors .htaccess pretty URLs. NOTE: dev router LIES about .htaccess
 * blocking rules — verify those under real Apache (stack-standard §3).
 */
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// direct files only when they exist and are not internals
$internals = '#^/(lib|src|tests|migrations|views|data)(/|$)#';
if (preg_match($internals, $uri) || $uri === '/config.example.php') {
    http_response_code(404);
    require __DIR__ . '/views/404.php';
    return true;
}

if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false; // serve assets etc.
}

if (preg_match('#^/p/([0-9A-Za-z]{1,16})$#', $uri, $m)) {
    $_GET['slug'] = $m[1];
    $_SERVER['PHP_SELF'] = '/poll.php';
    require __DIR__ . '/poll.php';
    return true;
}
if (preg_match('#^/admin/([0-9A-Za-z]{1,32})$#', $uri, $m)) {
    $_GET['edit_slug'] = $m[1];
    $_SERVER['PHP_SELF'] = '/admin.php';
    require __DIR__ . '/admin.php';
    return true;
}

$_SERVER['PHP_SELF'] = '/index.php';
require __DIR__ . '/index.php';
return true;