<?php
/**
 * src/PollService.php — all DB access for PlainSlots. PDO via db()/dbq().
 * MySQL in production; sqlite in tests (same SQL, portable subset).
 */
declare(strict_types=1);

final class PollService
{
    /** Create a poll; returns [slug, edit_slug]. */
    public static function create(string $title, string $description, string $creator, array $slots): array
    {
        $slug = random_base62(10);
        $editSlug = random_base62(16);
        dbq('INSERT INTO polls (slug, edit_slug, title, description, status, created_at) VALUES (?,?,?,?,?,?)',
            [$slug, $editSlug, $title, $description !== '' ? $description : null, 'open', date('Y-m-d H:i:s')]);
        $pollId = (int)db()->lastInsertId();
        self::replaceSlots($pollId, $slots);
        if ($creator !== '') {
            dbq('INSERT INTO participants (poll_id, name, created_at) VALUES (?,?,?)',
                [$pollId, $creator, date('Y-m-d H:i:s')]);
        }
        return [$slug, $editSlug];
    }

    /**
     * Full poll JSON per UX-plan contract (used by api/poll.php and tests).
     * {ok, poll:{title,description,status}, slots:[{id,label}],
     *  participants:[{name,votes:{slot_id:yes|maybe|no}}], best_slot_ids:[…]}
     */
    public static function pollJson(array $poll): array
    {
        $pollId = (int)$poll['id'];
        $slots = self::slots($pollId);
        $participants = self::participants($pollId);
        $votes = self::votes($pollId); // participant_id => slot_id => value
        $out = [
            'ok' => true,
            'poll' => [
                'title' => (string)$poll['title'],
                'description' => (string)($poll['description'] ?? ''),
                'status' => (string)$poll['status'],
            ],
            'slots' => array_map(function (array $s): array {
                return ['id' => $s['id'], 'label' => $s['label']];
            }, $slots),
            'participants' => [],
            'best_slot_ids' => self::bestSlotIds($slots, $votes),
        ];
        foreach ($participants as $p) {
            $pv = [];
            foreach ($slots as $s) {
                $v = $votes[$p['id']][$s['id']] ?? null;
                if ($v !== null) $pv[(string)$s['id']] = $v; // unset slots omitted; blank = no
            }
            $out['participants'][] = ['name' => $p['name'], 'votes' => $pv];
        }
        return $out;
    }

    public static function bySlug(string $slug): ?array
    {
        $row = dbq('SELECT id, slug, title, description, status, created_at FROM polls WHERE slug = ?', [$slug])->fetch();
        return $row ?: null;
    }

    public static function byEditSlug(string $editSlug): ?array
    {
        $row = dbq('SELECT id, slug, edit_slug, title, description, status, created_at FROM polls WHERE edit_slug = ?', [$editSlug])->fetch();
        return $row ?: null;
    }

    /** @return array<int,array{id:int,position:int,label:string}> */
    public static function slots(int $pollId): array
    {
        return array_map(function (array $r): array {
            return ['id' => (int)$r['id'], 'position' => (int)$r['position'], 'label' => (string)$r['label']];
        }, dbq('SELECT id, position, label FROM slots WHERE poll_id = ? ORDER BY position, id', [$pollId])->fetchAll());
    }

    /** @return array<int,array{id:int,name:string}> */
    public static function participants(int $pollId): array
    {
        return array_map(function (array $r): array {
            return ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        }, dbq('SELECT id, name FROM participants WHERE poll_id = ? ORDER BY id', [$pollId])->fetchAll());
    }

    /** @return array<int,int> participant_id => int map  participant_id => value */
    public static function votes(int $pollId): array
    {
        $out = [];
        foreach (dbq('SELECT v.participant_id, v.slot_id, v.value FROM votes v
                      JOIN participants p ON p.id = v.participant_id
                      WHERE p.poll_id = ?', [$pollId])->fetchAll() as $r) {
            $out[(int)$r['participant_id']][(int)$r['slot_id']] = (string)$r['value'];
        }
        return $out;
    }

    /**
     * Upsert a vote row-set for a name (case-insensitive, trimmed key).
     * Replaces the participant's previous votes entirely.
     * @param array<int,string> $votes slot_id => yes|maybe  (no/blank omitted = no)
     */
    public static function castVote(array $poll, string $name, array $votes): void
    {
        $pollId = (int)$poll['id'];
        db()->beginTransaction();
        try {
            $p = dbq('SELECT id FROM participants WHERE poll_id = ? AND LOWER(name) = LOWER(?)', [$pollId, $name])->fetch();
            if ($p) {
                $pid = (int)$p['id'];
                dbq('UPDATE participants SET name = ? WHERE id = ?', [$name, $pid]);
            } else {
                dbq('INSERT INTO participants (poll_id, name, created_at) VALUES (?,?,?)', [$pollId, $name, date('Y-m-d H:i:s')]);
                $pid = (int)db()->lastInsertId();
            }
            dbq('DELETE FROM votes WHERE participant_id = ?', [$pid]);
            $valid = array_map(function ($s) { return (int)$s; }, array_column(self::slots($pollId), 'id'));
            foreach ($votes as $slotId => $value) {
                if (!in_array((int)$slotId, $valid, true)) throw new RuntimeException('unknown slot');
                if (in_array($value, ['yes', 'maybe'], true)) {
                    dbq('INSERT INTO votes (participant_id, slot_id, value) VALUES (?,?,?)', [$pid, (int)$slotId, $value]);
                }
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
    }

    /**
     * Admin: set title/description and replace the slot list.
     * Slots whose cleaned label survives keep their id (and votes);
     * removed labels cascade-delete their votes; new labels append.
     */
    public static function adminEdit(int $pollId, string $title, string $description, array $slots): void
    {
        db()->beginTransaction();
        try {
            dbq('UPDATE polls SET title = ?, description = ? WHERE id = ?',
                [$title, $description !== '' ? $description : null, $pollId]);
            $existing = [];
            foreach (self::slots($pollId) as $s) $existing[$s['label']] = $s;
            $keep = [];
            foreach (array_unique($slots) as $label) $keep[$label] = true;
            // delete removed labels (votes cascade)
            foreach (array_keys($existing) as $label) {
                if (!isset($keep[$label])) {
                    dbq('DELETE FROM slots WHERE poll_id = ? AND label = ?', [$pollId, $label]);
                }
            }
            // insert new labels, renumber positions 1..n by submitted order
            $pos = 0;
            foreach ($slots as $label) {
                $pos++;
                if (isset($existing[$label])) {
                    dbq('UPDATE slots SET position = ? WHERE id = ?', [$pos, $existing[$label]['id']]);
                } else {
                    dbq('INSERT INTO slots (poll_id, position, label) VALUES (?,?,?)', [$pollId, $pos, $label]);
                }
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
    }

    public static function setStatus(int $pollId, string $status): void
    {
        dbq('UPDATE polls SET status = ? WHERE id = ?', [$status, $pollId]);
    }

    public static function delete(int $pollId): void
    {
        db()->beginTransaction();
        try {
            dbq('DELETE FROM votes WHERE participant_id IN (SELECT id FROM participants WHERE poll_id = ?)', [$pollId]);
            dbq('DELETE FROM participants WHERE poll_id = ?', [$pollId]);
            dbq('DELETE FROM slots WHERE poll_id = ?', [$pollId]);
            dbq('DELETE FROM polls WHERE id = ?', [$pollId]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
    }

    /**
     * Best slot ids: score = yes + 0.5*maybe; all maxima (ties highlighted).
     * @param array<int,array{id:int,...}> $slots
     * @param array<int,array<int,string>> $votes
     * @return int[] slot ids
     */
    public static function bestSlotIds(array $slots, array $votes): array
    {
        $scores = [];
        foreach ($slots as $s) $scores[$s['id']] = 0.0;
        foreach ($votes as $bySlot) {
            foreach ($bySlot as $slotId => $value) {
                if (!isset($scores[$slotId])) continue;
                if ($value === 'yes') $scores[$slotId] += 1.0;      // yes = 1
                elseif ($value === 'maybe') $scores[$slotId] += 0.5; // maybe = 0.5
                // 'no' (or anything else) = 0
            }
        }
        $max = null;
        foreach ($scores as $sc) if ($max === null || $sc > $max) $max = $sc;
        if ($max === null || $max <= 0.0) return []; // no votes = no highlight
        $ids = [];
        foreach ($scores as $sid => $sc) if ($sc === $max) $ids[] = (int)$sid;
        return $ids;
    }

    /** Replace the full slot list (create path). */
    private static function replaceSlots(int $pollId, array $labels): void
    {
        $pos = 0;
        foreach ($labels as $label) {
            $pos++;
            dbq('INSERT INTO slots (poll_id, position, label) VALUES (?,?,?)', [$pollId, $pos, $label]);
        }
    }
}