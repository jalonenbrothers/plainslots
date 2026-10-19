<?php
/**
 * api/admin/delete.php — POST {edit_slug} → {ok}. Removes poll + slots + votes.
 */
require_once __DIR__ . '/_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only.'], 405);

[$poll, $in] = admin_guard();

// No-JS two-step: require confirm=DELETE typed in the form.
$confirm = trim((string)($in['confirm'] ?? ''));
if ($confirm !== '' && strtoupper($confirm) !== 'DELETE') {
    json_out(['ok' => false, 'error' => 'Type DELETE to confirm.'], 400);
}

PollService::delete((int)$poll['id']);
json_out(['ok' => true]);