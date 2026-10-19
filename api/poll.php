<?php
/**
 * api/poll.php — GET ?slug=… → poll JSON (UX-plan contract).
 * {ok, poll:{title,description,status}, slots:[{id,label}], participants:[{name,votes}], best_slot_ids}
 */
require_once __DIR__ . '/_boot.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '' || strlen($slug) > 16) json_out(['ok' => false, 'error' => 'Missing or invalid slug.'], 400);

$poll = PollService::bySlug($slug);
if (!$poll) json_out(['ok' => false, 'error' => 'Poll not found.'], 404);

json_out(PollService::pollJson($poll));