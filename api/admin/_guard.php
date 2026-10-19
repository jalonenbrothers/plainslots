<?php
/**
 * api/admin/_guard.php — resolve the poll by edit_slug or 404.
 * Never confirms whether an edit_slug exists (generic 404).
 */
declare(strict_types=1);

require_once __DIR__ . '/../_boot.php';

function admin_guard(): array
{
    $in = json_body();
    if ($in === null) $in = $_POST; // no-JS fallback: plain form post
    $editSlug = trim((string)($in['edit_slug'] ?? ($_GET['edit_slug'] ?? '')));
    if ($editSlug === '' || strlen($editSlug) > 32) json_out(['ok' => false, 'error' => 'Not found.'], 404);
    $poll = PollService::byEditSlug($editSlug);
    if (!$poll) json_out(['ok' => false, 'error' => 'Not found.'], 404);
    return [$poll, $in];
}