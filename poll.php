<?php
/**
 * poll.php — server-rendered poll page (scope-lock #2/#4/#5): vote form (no-JS core),
 * results with best-slot highlight, share box, one-time admin box, status chip.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
boot();

require_once __DIR__ . '/src/Validator.php';
require_once __DIR__ . '/src/PollService.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$poll = ($slug !== '' && strlen($slug) <= 16) ? PollService::bySlug($slug) : null;
if (!$poll) { http_response_code(404); require __DIR__ . '/views/404.php'; exit; }

send_noindex();

$slots = PollService::slots((int)$poll['id']);
$participants = PollService::participants((int)$poll['id']);
$votes = PollService::votes((int)$poll['id']);          // participant_id => slot_id => value
$best = PollService::bestSlotIds($slots, $votes);        // slot ids to highlight
$bestMap = array_fill_keys($best, true);

// per-slot tallies for the results table
$tally = [];
foreach ($slots as $s) $tally[$s['id']] = ['yes' => 0, 'maybe' => 0, 'no' => 0];
$voterCount = count($participants);
foreach ($votes as $bySlot) {
    foreach ($bySlot as $slotId => $value) {
        if (isset($tally[$slotId])) $tally[$slotId][$value]++;
    }
}

$isAdminNew = isset($_GET['new']);
$justVoted = $_GET['voted'] ?? null;
$closed = ($poll['status'] !== 'open');
$pollUrl = app_url() . '/p/' . rawurlencode($slug);
$ERR = '';
if (isset($_GET['err'])) $ERR = (string)$_GET['err'];

// one-time admin box: edit_slug travels via session (set by create), never via URL.
if (session_status() !== PHP_SESSION_ACTIVE) session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$shownEdit = null;
if ($isAdminNew && !empty($_SESSION['new_edit_slug']) && ($_SESSION['new_edit_poll'] ?? '') === $slug) {
    $shownEdit = $_SESSION['new_edit_slug'];
    unset($_SESSION['new_edit_slug'], $_SESSION['new_edit_poll']); // shown ONCE
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($poll['title']) ?> — PlainSlots</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header"><a class="wordmark" href="/">PlainSlots</a></header>
<main>
<p class="crumb"><a href="/">← PlainSlots</a></p>
<h1><?= e($poll['title']) ?></h1>
<?php if (($poll['description'] ?? '') !== ''): ?>
<p class="poll-desc"><?= e($poll['description']) ?></p>
<?php endif; ?>
<p><span class="chip <?= $closed ? 'chip-closed' : 'chip-open' ?>"><?= $closed ? 'closed' : 'open' ?></span>
<?= $closed ? 'voting closed' : 'voting is open' ?></p>

<?php if ($shownEdit !== null): ?>
<section class="card admin-box">
  <h2>Save this admin link — shown once, we can’t recover it</h2>
  <label class="sr-only" for="admin-url">Admin link</label>
  <input id="admin-url" readonly value="<?= e(app_url() . '/admin/' . rawurlencode($shownEdit)) ?>">
  <button type="button" class="copy-btn" data-copy="admin-url">Copy</button>
  <p class="fineprint">It lets anyone holding it edit or delete this poll. Keep it private.</p>
</section>
<?php endif; ?>

<?php if ($justVoted): ?>
<p class="flash" role="status">Thanks, <?= e($justVoted) ?> — your votes are saved. Submit again any time to change them.</p>
<?php endif; ?>
<?php if ($ERR): ?><p class="error" role="alert"><?= e($ERR) ?></p><?php endif; ?>

<section class="card" id="vote">
  <h2><?= $voterCount ? 'Vote' : 'No votes yet — be the first.' ?></h2>
  <?php if ($closed): ?>
  <p>Voting is closed. Results are below.</p>
  <?php else: ?>
  <form method="post" action="/api/vote.php">
    <input type="hidden" name="slug" value="<?= e($slug) ?>">
    <label for="v-name">Your name</label>
    <input id="v-name" name="name" type="text" required maxlength="50"
           value="<?= e($_GET['name'] ?? '') ?>" autocomplete="name">
    <table class="vote-grid">
      <tbody>
      <?php foreach ($slots as $s): $sid = $s['id']; ?>
        <tr>
          <th scope="row"><?= e($s['label']) ?></th>
          <td class="radios">
            <?php foreach (['yes' => 'Yes', 'maybe' => 'Maybe', 'no' => 'No'] as $val => $lab): ?>
            <label class="radio"><input type="radio" name="votes[<?= (int)$sid ?>]" value="<?= $val ?>"> <?= $lab ?></label>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="fineprint">Blank = no. Submitting again with the same name replaces your votes.</p>
    <button class="primary" type="submit">Save my votes</button>
  </form>
  <?php endif; ?>
</section>

<section class="card" id="results">
  <h2>Results</h2>
  <div class="scrollwrap">
  <table class="results">
    <thead><tr><th>Slot</th><th>Yes</th><th>Maybe</th><th>No</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($slots as $s): $t = $tally[$s['id']]; $votedThis = $t['yes'] + $t['maybe'];
          $total = max(1, $voterCount);
          $barYes = (int)round($t['yes'] / $total * 12);
          $barMay = (int)round($t['maybe'] / $total * 12); ?>
      <tr class="<?= isset($bestMap[$s['id']]) ? 'best' : '' ?>">
        <th scope="row"><?= e($s['label']) ?></th>
        <td class="num"><?= (int)$t['yes'] ?></td>
        <td class="num"><?= (int)$t['maybe'] ?></td>
        <td class="num"><?= (int)$t['no'] ?></td>
        <td class="tally"><?= str_repeat('▓', $barYes) ?><?= str_repeat('▒', $barMay) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($voterCount): ?>
  <p class="fineprint">Voted: <?= e(implode(', ', array_column($participants, 'name'))) ?> (<?= $voterCount ?>)</p>
  <?php else: ?>
  <p class="fineprint">No votes yet.</p>
  <?php endif; ?>
  <p class="fineprint">Best slot = most yes, maybe counts half. Ties are all highlighted.</p>
</section>

<section class="card" id="share">
  <h2>Share this poll</h2>
  <label class="sr-only" for="poll-url">Poll link</label>
  <input id="poll-url" readonly value="<?= e($pollUrl) ?>">
  <button type="button" class="copy-btn" data-copy="poll-url">Copy</button>
</section>
</main>
<footer>MVP Lab · no accounts · results never expire</footer>
<script src="/assets/app.js" defer></script>
</body>
</html>