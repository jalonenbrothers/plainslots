<?php
/**
 * api/create.php — POST {title, description?, creator?, slots:[string]}.
 * - JSON body → JSON {ok, slug, edit_slug} (JS path).
 * - HTML form post → 303 redirect to /p/{slug}?new=1 (no-JS path, core flow).
 * Validates per UX plan: title 1..200, slots 1..50 non-empty ≤80 chars.
 */
require_once __DIR__ . '/_boot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only.'], 405);

$in = json_body();
$isForm = false;
if ($in === null && !empty($_POST)) { $in = $_POST; $isForm = true; }
if ($in === null) json_out(['ok' => false, 'error' => 'Invalid JSON body.'], 400);

$v = Validator::createPayload($in);
if (!$v['ok']) {
    if ($isForm) { json_out(['ok' => false, 'error' => $v['error']], 400); } // JS shows inline; form fallback keeps JSON error contract for fetch
    json_out(['ok' => false, 'error' => $v['error']], 400);
}

[$slug, $editSlug] = PollService::create($v['title'], $v['description'], $v['creator'], $v['slots']);

// one-time admin box: edit_slug travels via session, never via URL (shown once)
if (session_status() !== PHP_SESSION_ACTIVE) session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$_SESSION['new_edit_slug'] = $editSlug;
$_SESSION['new_edit_poll'] = $slug;

if ($isForm) {
    header('Location: /p/' . rawurlencode($slug) . '?new=1', true, 303);
    exit;
}
json_out(['ok' => true, 'slug' => $slug, 'edit_slug' => $editSlug], 201);