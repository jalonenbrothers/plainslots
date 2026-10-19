<?php
/**
 * index.php — landing page = create form (scope-lock #1). No signup, no hero scroll.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
boot();
$ERROR = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // no-JS core path: form posts straight here (JS path posts via /api/create.php)
    $v = ['ok' => false];
    require_once __DIR__ . '/src/Validator.php';
    $in = $_POST;
    $v = Validator::createPayload($in);
    if ($v['ok']) {
        require_once __DIR__ . '/src/PollService.php';
        [$slug] = PollService::create($v['title'], $v['description'], $v['creator'], $v['slots']);
        header('Location: /p/' . rawurlencode($slug) . '?new=1', true, 303);
        exit;
    }
    $ERROR = $v['error'];
}
$FORM_ACTION = '/api/create.php'; // JS path; no-JS form posts to same URL (JSON error contract)
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PlainSlots — group time-slot polls. No login, no ads, ever.</title>
<meta name="description" content="Group time-slot polls. No login. No ads. Results never expire.">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header"><a class="wordmark" href="/">PlainSlots</a></header>
<main>
<h1>PlainSlots</h1>
<p class="tagline">Group time-slot polls. <strong>No login, no ads, ever.</strong></p>
<form class="card create-form" method="post" action="<?= $FORM_ACTION ?>" id="create-form">
  <?php if ($ERROR): ?><p class="error" role="alert"><?= e($ERROR) ?></p><?php endif; ?>
  <label for="f-title">Title <span class="req">*</span></label>
  <input id="f-title" name="title" type="text" required maxlength="200" value="<?= e($_POST['title'] ?? '') ?>">
  <label for="f-desc">Description (optional)</label>
  <textarea id="f-desc" name="description" rows="3" maxlength="1000"><?= e($_POST['description'] ?? '') ?></textarea>
  <label for="f-creator">Your name (optional)</label>
  <input id="f-creator" name="creator" type="text" maxlength="50" value="<?= e($_POST['creator'] ?? '') ?>">
  <fieldset>
    <legend>Slots — one per row (e.g. “Tue 18:00”)</legend>
    <div id="slot-rows">
      <input class="slot-input" name="slots[]" type="text" maxlength="80" placeholder="Tue 18:00">
      <input class="slot-input" name="slots[]" type="text" maxlength="80" placeholder="Wed 19:00">
      <input class="slot-input" name="slots[]" type="text" maxlength="80" placeholder="Thu 20:00">
    </div>
    <button type="button" class="linklike" id="add-slot">+ Add another slot</button>
  </fieldset>
  <button class="primary" type="submit">Create poll</button>
</form>
</main>
<footer>MVP Lab · no accounts · results never expire</footer>
<script src="/assets/app.js" defer></script>
</body>
</html>