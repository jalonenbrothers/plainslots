<?php
/**
 * api/admin/close.php — POST {edit_slug} → toggle voting open/closed.
 * Payload may carry status:"open"|"closed" (from the no-JS form); absent = toggle.
 */
require_once __DIR__ . '/_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only.'], 405);

[$poll, $in] = admin_guard();

$wanted = (string)($in['status'] ?? '');
if ($wanted === 'open' || $wanted === 'closed') {
    $status = $wanted;
} else {
    $status = ($poll['status'] === 'open') ? 'closed' : 'open';
}
PollService::setStatus((int)$poll['id'], $status);
json_out(['ok' => true, 'status' => $status]);