<?php
/**
 * api/admin/edit.php — POST {edit_slug, title, description?, slots:[string]} → {ok}
 */
require_once __DIR__ . '/_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only.'], 405);

[$poll, $in] = admin_guard();

$v = Validator::adminEditPayload($in);
if (!$v['ok']) json_out(['ok' => false, 'error' => $v['error']], 400);

PollService::adminEdit((int)$poll['id'], $v['title'], $v['description'], $v['slots']);
json_out(['ok' => true]);