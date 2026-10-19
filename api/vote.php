<?php
/**
 * api/vote.php — POST {slug, name, votes:{slot_id: yes|maybe|no}}.
 * - JSON body → JSON {ok} (JS path).
 * - HTML form post (name + votes[slot_id] radios) → 303 back to /p/{slug} (no-JS).
 * Upsert by trimmed case-insensitive name. Blank slot = no (omitted).
 */
require_once __DIR__ . '/_boot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only.'], 405);

$in = json_body();
$isForm = false;
if ($in === null && !empty($_POST)) { $in = $_POST; $isForm = true; }
if ($in === null) json_out(['ok' => false, 'error' => 'Invalid JSON body.'], 400);

$slug = trim((string)($in['slug'] ?? ''));
$poll = $slug !== '' ? PollService::bySlug($slug) : null;
if (!$poll) json_out(['ok' => false, 'error' => 'Poll not found.'], 404);
if ($poll['status'] !== 'open') {
    if ($isForm) { header('Location: /p/' . rawurlencode($slug) . '?closed=1', true, 303); exit; }
    json_out(['ok' => false, 'error' => 'Voting is closed for this poll.'], 403);
}

$v = Validator::votePayload($in);
if (!$v['ok']) json_out(['ok' => false, 'error' => $v['error']], 400);

try {
    PollService::castVote($poll, $v['name'], $v['votes']);
} catch (RuntimeException $e) {
    json_out(['ok' => false, 'error' => 'Vote contains a slot that is not part of this poll.'], 400);
}

if ($isForm) {
    header('Location: /p/' . rawurlencode($slug) . '?voted=' . rawurlencode($v['name']), true, 303);
    exit;
}
json_out(['ok' => true]);