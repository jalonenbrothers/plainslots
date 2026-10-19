<?php
/**
 * admin.php — secret admin page (/admin/{edit_slug}). Server-rendered editors + forms.
 * Core no-JS path: plain POST forms to /api/admin/*. endpoints.
 * JS (app.js) enhances delete with a confirm() two-step.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
boot();

require_once __DIR__ . '/src/Validator.php';
require_once __DIR__ . '/src/PollService.php';

$editSlug = trim((string)($_GET['edit_slug'] ?? ($_SERVER['PATH_INFO'] ?? '')));
// router.php passes edit_slug via query; .htaccess rewrites /admin/{edit_slug} → admin.php?edit_slug=…
if ($editSlug === '' && isset($_SERVER['REQUEST_URI'])) {
    $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    if (preg_match('#^/admin/([0-9A-Za-z]{1,32})$#', $p, $m)) $editSlug = $m[1];
}
$poll = ($editSlug !== '' && strlen($editSlug) <= 32) ? PollService::byEditSlug($editSlug) : null;
if (!$poll) { http_response_code(404); require __DIR__ . '/views/404.php'; exit; }

send_noindex();

$slots = PollService::slots((int)$poll['id']);
$closed = ($poll['status'] !== 'open');
$ERR = (string)($_GET['err'] ?? '');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — <?= e($poll['title']) ?> — PlainSlots</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header"><a class="wordmark" href="/">PlainSlots</a></header>
<main>
<p class="crumb"><a href="/p/<?= e($poll['slug']) ?>">← view poll</a></p>
<h1>Admin</h1>
<p class="tagline"><?= e($poll['title']) ?></p>
<?php if ($ERR): ?><p class="error" role="alert"><?= e($ERR) ?></p><?php endif; ?>

<section class="card">
  <h2>Edit poll</h2>
  <form method="post" action="/api/admin/edit.php">
    <input type="hidden" name="edit_slug" value="<?= e($editSlug) ?>">
    <label for="a-title">Title</label>
    <input id="a-title" name="title" type="text" required maxlength="200" value="<?= e($poll['title']) ?>">
    <label for="a-desc">Description</label>
    <textarea id="a-desc" name="description" rows="3" maxlength="1000"><?= e($poll['description'] ?? '') ?></textarea>
    <fieldset>
      <legend>Slots</legend>
      <div id="admin-slot-rows">
        <?php foreach ($slots as $s): ?>
        <div class="slot-row">
          <input class="slot-input" name="slots[]" type="text" maxlength="80" value="<?= e($s['label']) ?>">
          <button type="button" class="linklike remove-slot" title="Remove this slot">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="linklike" id="add-admin-slot">+ Add slot</button>
      <p class="fineprint">Removing a slot deletes its votes. Votes follow slots by exact label.</p>
    </fieldset>
    <button class="primary" type="submit">Save changes</button>
  </form>
</section>

<section class="card">
  <h2>Voting status</h2>
  <p><span class="chip <?= $closed ? 'chip-closed' : 'chip-open' ?>"><?= $closed ? 'closed' : 'open' ?></span></p>
  <form method="post" action="/api/admin/close.php">
    <input type="hidden" name="edit_slug" value="<?= e($editSlug) ?>">
    <input type="hidden" name="status" value="<?= $closed ? 'open' : 'closed' ?>">
    <button type="submit"><?= $closed ? 'Re-open voting' : 'Close voting' ?></button>
  </form>
</section>

<section class="card danger">
  <h2>Delete poll</h2>
  <form method="post" action="/api/admin/delete.php" id="delete-form">
    <input type="hidden" name="edit_slug" value="<?= e($editSlug) ?>">
    <label for="a-confirm">Type DELETE to confirm</label>
    <input id="a-confirm" name="confirm" type="text" placeholder="DELETE" autocomplete="off">
    <button type="submit" class="danger-btn">Delete this poll forever</button>
  </form>
</section>
</main>
<footer>MVP Lab · no accounts · results never expire</footer>
<script src="/assets/app.js" defer></script>
</body>
</html>