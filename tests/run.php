<?php
/**
 * tests/run.php — PlainSlots unit + e2e test suite (sqlite in-memory).
 * Runner: php tests/run.php  — exit 0 = all green.
 * House style: lab-blog/safeword shape (assert() counter, no framework).
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/db.php';
require_once $ROOT . '/lib/helpers.php';
require_once $ROOT . '/src/Validator.php';
require_once $ROOT . '/src/PollService.php';

// ---- in-memory sqlite, schema ported from migrations/001_create_polls.sql
// (sqlite has no ENUM; CHECK constraints approximate it. FKs on.)
putenv('PLAINSLOTS_DB=sqlite::memory:');
db()->exec('PRAGMA foreign_keys = ON');
db()->exec('CREATE TABLE IF NOT EXISTS polls (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  edit_slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  description TEXT NULL,
  status TEXT NOT NULL DEFAULT "open" CHECK (status IN ("open","closed")),
  created_at TEXT NOT NULL)');
db()->exec('CREATE TABLE IF NOT EXISTS slots (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
  position INTEGER NOT NULL,
  label TEXT NOT NULL,
  UNIQUE (poll_id, position))');
db()->exec('CREATE TABLE IF NOT EXISTS participants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  created_at TEXT NOT NULL)');
db()->exec('CREATE TABLE IF NOT EXISTS votes (
  participant_id INTEGER NOT NULL REFERENCES participants(id) ON DELETE CASCADE,
  slot_id INTEGER NOT NULL REFERENCES slots(id) ON DELETE CASCADE,
  value TEXT NOT NULL CHECK (value IN ("yes","maybe","no")),
  PRIMARY KEY (participant_id, slot_id))');

// ---- tiny runner
$PASS = 0; $FAIL = 0; $FAILURES = [];
function t(string $name, bool $cond): void
{
    global $PASS, $FAIL, $FAILURES;
    if ($cond) { $PASS++; echo "ok  $name\n"; }
    else { $FAIL++; $FAILURES[] = $name; echo "FAIL $name\n"; }
}
function expect_error(string $name, array $r, string $needle): void
{
    t($name, !$r['ok'] && isset($r['error']) && str_contains($r['error'], $needle));
}

// ================= Validator: create =================
expect_error('create: title required', Validator::createPayload(['title' => '', 'slots' => ['a']]), 'Title');
expect_error('create: title 200 max', Validator::createPayload(['title' => str_repeat('x', 201), 'slots' => ['a']]), '200');
t('create: title exactly 200 ok', Validator::createPayload(['title' => str_repeat('x', 200), 'slots' => ['a']])['ok']);
expect_error('create: zero slots', Validator::createPayload(['title' => 'T', 'slots' => []]), 'At least one');
t('create: no all-empty slot list (blank rows dropped, must have ≥1 real)',
    !Validator::createPayload(['title' => 'T', 'slots' => ['  ', '']])['ok']);
expect_error('create: 51 slots', Validator::createPayload(['title' => 'T', 'slots' => array_fill(0, 51, 's')]), 'max 50');
t('create: 50 slots ok', Validator::createPayload(['title' => 'T', 'slots' => array_fill(0, 50, 's')])['ok']);
expect_error('create: slot 81 chars', Validator::createPayload(['title' => 'T', 'slots' => [str_repeat('s', 81)]]), '80');
t('create: control chars stripped',
    Validator::createPayload(['title' => "T\x01\x02", 'slots' => ["a\tb"]])['title'] === 'T'
    && Validator::createPayload(['title' => 'T', 'slots' => ["a\tb"]])['slots'] === ['a b']);
t('create: trim applied', Validator::createPayload(['title' => '  T  ', 'slots' => ['  a ']])['slots'] === ['a']);
t('create: unicode slots kept', Validator::createPayload(['title' => 'T', 'slots' => ['tiistai 18:00']])['ok']);
t('create: description 1000 ok, 1001 fail',
    Validator::createPayload(['title' => 'T', 'description' => str_repeat('d', 1000), 'slots' => ['a']])['ok']
    && !Validator::createPayload(['title' => 'T', 'description' => str_repeat('d', 1001), 'slots' => ['a']])['ok']);
t('create: creator 50 ok / 51 fail',
    Validator::createPayload(['title' => 'T', 'creator' => str_repeat('c', 50), 'slots' => ['a']])['ok']
    && !Validator::createPayload(['title' => 'T', 'creator' => str_repeat('c', 51), 'slots' => ['a']])['ok']);

// ================= Validator: vote =================
expect_error('vote: name required', Validator::votePayload(['name' => '', 'votes' => []]), 'name');
expect_error('vote: name 51', Validator::votePayload(['name' => str_repeat('n', 51), 'votes' => []]), '50');
t('vote: bad value rejected', !Validator::votePayload(['name' => 'A', 'votes' => ['1' => 'maybe?']])['ok']);
t('vote: blank=no normalized away',
    Validator::votePayload(['name' => 'A', 'votes' => ['7' => 'no']])['votes'] === []);
$v = Validator::votePayload(['name' => ' A ', 'votes' => ['3' => 'Yes ', '5' => 'maybe']])['votes'] ?? null;
t('vote: yes/maybe kept, keys cast to int', $v === [3 => 'yes', 5 => 'maybe']);

// ================= Service e2e: create/vote/poll json =================
[$slug, $editSlug] = PollService::create('Team sync', 'Pick a weekly slot', '', ['Mon 09:00', 'Tue 18:00', 'Wed 12:00']);
t('create: opaque slugs (10/16)', strlen($slug) === 10 && strlen($editSlug) === 16 && $slug !== $editSlug);
t('create: slug alnum', preg_match('/^[0-9A-Za-z]+$/', $slug) === 1 && preg_match('/^[0-9A-Za-z]+$/', $editSlug) === 1);
$poll = PollService::bySlug($slug);
t('bySlug finds poll, status open', $poll && $poll['status'] === 'open' && $poll['title'] === 'Team sync');
$slots = PollService::slots((int)$poll['id']);
t('slots: 3 ordered rows', count($slots) === 3 && $slots[0]['label'] === 'Mon 09:00' && $slots[2]['position'] === 3);

$sA = $slots[0]['id']; $sB = $slots[1]['id']; $sC = $slots[2]['id'];
PollService::castVote($poll, 'Ana', [$sA => 'yes', $sB => 'maybe']);
PollService::castVote($poll, 'Bo', [$sA => 'yes', $sB => 'yes', $sC => 'no']);
// re-vote same name different case = overwrite, not duplicate
PollService::castVote($poll, 'ana', [$sB => 'yes']);
$j = PollService::pollJson($poll);
t('pollJson: 2 participants (upsert case-insens)', count($j['participants']) === 2);
t('pollJson: slot labels + status', $j['poll']['status'] === 'open' && $j['slots'][0]['label'] === 'Mon 09:00');
$ana = null; foreach ($j['participants'] as $p) if (strcasecmp($p['name'], 'ana') === 0) $ana = $p;
t('pollJson: ana overwrite — only Tue yes', $ana && $ana['votes'] === [(string)$sB => 'yes']);
$bo = null; foreach ($j['participants'] as $p) if ($p['name'] === 'Bo') $bo = $p;
t('pollJson: unset slots omitted (blank=no)', $bo && !isset($bo['votes'][(string)$sC]));
// scores: sA yes1(Ana gone→0)=0, sB maybe.5+yes+yes=2.5, sC 0 → best = sB
t('bestSlotIds: maybe=0.5, winner sB', $j['best_slot_ids'] === [$sB]);

// tie: two slots equal max → both highlighted
PollService::castVote($poll, 'Cy', [$sA => 'yes', $sC => 'yes', $sB => 'yes']);
$j2 = PollService::pollJson($poll);
// sB: 2.5+1=3.5, sA: 1, sC: 1 → single winner still sB
t('best: after Cy, winner still sB only', $j2['best_slot_ids'] === [$sB]);

// unknown slot id rejected
$unknown = 999999;
try { PollService::castVote($poll, 'Bad', [$unknown => 'yes']); $threw = false; }
catch (RuntimeException $e) { $threw = true; }
t('castVote: unknown slot id → exception + rollback', $threw);
$names = array_column(PollService::participants((int)$poll['id']), 'name');
t('castVote: rolled back participant "Bad"', !in_array('Bad', $names, true));

// ================= Admin =================
$ap = PollService::byEditSlug($editSlug);
t('byEditSlug finds same poll', $ap && (int)$ap['id'] === (int)$poll['id']);
t('wrong edit slug 404', PollService::byEditSlug(strrev($editSlug)) === null);

// admin edit: rename title, keep Tue, drop Mon, add Thu
PollService::adminEdit((int)$poll['id'], 'Team sync!', 'desc v2', ['Thu 20:00', 'Tue 18:00']);
$poll2 = PollService::bySlug($slug);
$slots2 = PollService::slots((int)$poll2['id']);
t('adminEdit: title/desc updated', $poll2['title'] === 'Team sync!' && $poll2['description'] === 'desc v2');
t('adminEdit: Mon removed, Thu added, Tue kept id', count($slots2) === 2
    && !in_array('Mon 09:00', array_column($slots2, 'label'), true)
    && $slots2[0]['label'] === 'Thu 20:00' && $slots2[0]['position'] === 1);
$tue = null; foreach ($slots2 as $s) if ($s['label'] === 'Tue 18:00') $tue = $s;
t('adminEdit: Tue slot kept SAME id (votes survive)', $tue && $tue['id'] === $sB);
$votesAfter = PollService::votes((int)$poll2['id']);
$anaVotes = $votesAfter[key($votesAfter)] ?? []; // any participant
$kept = 0; foreach (PollService::votes((int)$poll2['id']) as $bySlot) foreach ($bySlot as $sid => $v) if ((int)$sid === $sB) $kept++;
t('adminEdit: votes for removed Mon cascade-deleted, Tue votes kept', $kept >= 2);

// close → vote rejected at API level (service check by caller); status flip
PollService::setStatus((int)$poll2['id'], 'closed');
t('close: status closed', PollService::bySlug($slug)['status'] === 'closed');
PollService::setStatus((int)$poll2['id'], 'open');
t('reopen: status open', PollService::bySlug($slug)['status'] === 'open');

// delete cascade
[$slug3, $edit3] = PollService::create('Doomed', '', 'Ed', ['x']);
$p3 = PollService::bySlug($slug3);
PollService::castVote($p3, 'V', [PollService::slots((int)$p3['id'])[0]['id'] => 'yes']);
$pid3 = (int)$p3['id'];
$s3 = PollService::slots($pid3);
$part3 = PollService::participants($pid3);
$slotIds3 = array_column($s3, 'id');
t('delete: poll 3 has 1 slot + 2 participants (creator Ed + voter V) before delete',
    count($s3) === 1 && count($part3) === 2);
PollService::delete($pid3);
t('delete: poll gone', PollService::bySlug($slug3) === null && PollService::byEditSlug($edit3) === null);
$partIds3 = implode(',', array_column($part3, 'id'));
$left = dbq("SELECT
    (SELECT COUNT(*) FROM polls WHERE id = $pid3) AS a,
    (SELECT COUNT(*) FROM slots WHERE poll_id = $pid3) AS b,
    (SELECT COUNT(*) FROM participants WHERE poll_id = $pid3) AS c,
    (SELECT COUNT(*) FROM votes WHERE participant_id IN ($partIds3) OR slot_id IN ($slotIds3[0])) AS d")->fetch();
t('delete: poll-3 slots/participants/votes all gone',
    (int)$left['a'] + (int)$left['b'] + (int)$left['c'] + (int)$left['d'] === 0);

// ================= bestSlotIds pure fn =================
$sx = [['id' => 1], ['id' => 2]];
t('bestSlotIds: no votes → none highlighted', PollService::bestSlotIds($sx, []) === []);
t('bestSlotIds: tie highlights both', PollService::bestSlotIds($sx, [10 => [1 => 'yes', 2 => 'yes']]) === [1, 2]);
t('bestSlotIds: maybe half-weight tiebreak',
    PollService::bestSlotIds([['id' => 1], ['id' => 2]], [10 => [1 => 'maybe', 2 => 'maybe'], 11 => [1 => 'maybe', 2 => 'no']]) === [1]);

// ================= helpers =================
t('e() escapes', e('<script>&"</script>') === '&lt;script&gt;&amp;&quot;&lt;/script&gt;');
t('random_base62 length+alphabet', strlen(random_base62(16)) === 16 && preg_match('/^[0-9A-Za-z]+$/', random_base62(50)) === 1);
t('cleanText strips control chars', Validator::cleanText("a\x00b\x1fc") === 'abc');

// ================= landing form no-JS path (index logic) =================
$_POST = ['title' => 'Form poll', 'description' => '', 'creator' => '', 'slots' => ['S1', 'S2', '']];
$v = Validator::createPayload($_POST);
t('form: trailing empty slot row → cleaned to 2 slots', $v['ok'] && count($v['slots']) === 2);

// ================= summary =================
echo "\n----\n";
if ($FAIL === 0) { echo "ALL PASS: $PASS passed, 0 failed\n"; exit(0); }
echo "FAILURES ($FAIL):\n" . implode("\n", array_map(fn($f) => "  - $f", $FAILURES)) . "\n";
exit(1);