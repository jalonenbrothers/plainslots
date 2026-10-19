<?php
/**
 * src/Validator.php — input validation for PlainSlots (UX-plan contract).
 * Pure functions; no DB access. Unit-tested in tests/run.php.
 */
declare(strict_types=1);

final class Validator
{
    public const MAX_TITLE = 200;
    public const MAX_DESCRIPTION = 1000;
    public const MAX_NAME = 50;
    public const MAX_SLOTS = 50;
    public const MAX_SLOT_LEN = 80;

    /** Strip control chars (keep \n and tab as spaces) and trim. */
    public static function cleanText(string $s): string
    {
        $s = str_replace(["\r", "\n", "\t"], ' ', $s);
        // remove remaining control chars U+0000-U+001F and U+007F
        $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
        return trim($s);
    }

    /**
     * Validate a create-poll payload.
     * @return array{ok:bool, error?:string, title?:string, description?:string, creator?:string, slots?:string[]}
     */
    public static function createPayload(array $in): array
    {
        $title = self::cleanText((string)($in['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'error' => 'Title is required.'];
        if (mb_strlen($title) > self::MAX_TITLE) return ['ok' => false, 'error' => 'Title must be at most 200 characters.'];

        $description = self::cleanText((string)($in['description'] ?? ''));
        if (mb_strlen($description) > self::MAX_DESCRIPTION) return ['ok' => false, 'error' => 'Description must be at most 1000 characters.'];

        $slotsIn = $in['slots'] ?? [];
        if (!is_array($slotsIn)) return ['ok' => false, 'error' => 'Slots must be a list.'];
        $slots = [];
        foreach ($slotsIn as $s) {
            $label = self::cleanText((string)$s);
            if ($label === '') continue; // blank form rows are dropped, not an error (no-JS fallback)
            if (mb_strlen($label) > self::MAX_SLOT_LEN) return ['ok' => false, 'error' => 'Each slot must be at most 80 characters.'];
            $slots[] = $label;
        }
        $n = count($slots);
        if ($n < 1) return ['ok' => false, 'error' => 'At least one slot is required.'];
        if ($n > self::MAX_SLOTS) return ['ok' => false, 'error' => 'Too many slots (max 50).'];

        $creator = self::cleanText((string)($in['creator'] ?? ''));
        if (mb_strlen($creator) > self::MAX_NAME) return ['ok' => false, 'error' => 'Creator name must be at most 50 characters.'];

        return ['ok' => true, 'title' => $title, 'description' => $description,
                'creator' => $creator, 'slots' => $slots];
    }

    /**
     * Validate a vote payload (name + slot_id => yes|maybe|no map).
     * Unknown slot ids are reported, blank values normalized away.
     * @return array{ok:bool, error?:string, name?:string, votes?:array<int,string>}
     */
    public static function votePayload(array $in): array
    {
        $name = self::cleanText((string)($in['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'error' => 'Please enter your name.'];
        if (mb_strlen($name) > self::MAX_NAME) return ['ok' => false, 'error' => 'Name must be at most 50 characters.'];

        $votesIn = $in['votes'] ?? [];
        if (!is_array($votesIn)) return ['ok' => false, 'error' => 'Votes must be an object.'];

        $votes = [];
        foreach ($votesIn as $slotId => $v) {
            $sid = (int)$slotId;
            $value = strtolower(trim((string)$v));
            if (!in_array($value, ['yes', 'maybe', 'no'], true)) {
                return ['ok' => false, 'error' => "Invalid vote value for slot $sid."];
            }
            if ($value !== 'no') $votes[$sid] = $value; // blank = no; store only yes/maybe
        }
        return ['ok' => true, 'name' => $name, 'votes' => $votes];
    }

    /** Validate admin edits: title required, description, slot labels. */
    public static function adminEditPayload(array $in): array
    {
        $title = self::cleanText((string)($in['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'error' => 'Title cannot be empty.'];
        if (mb_strlen($title) > self::MAX_TITLE) return ['ok' => false, 'error' => 'Title must be at most 200 characters.'];
        $description = self::cleanText((string)($in['description'] ?? ''));
        if (mb_strlen($description) > self::MAX_DESCRIPTION) return ['ok' => false, 'error' => 'Description must be at most 1000 characters.'];
        $slots = [];
        $slotsIn = $in['slots'] ?? [];
        if (!is_array($slotsIn)) return ['ok' => false, 'error' => 'Slots must be a list.'];
        foreach ($slotsIn as $s) {
            $label = self::cleanText((string)$s);
            if ($label === '') continue; // blank form rows dropped (no-JS admin form)
            if (mb_strlen($label) > self::MAX_SLOT_LEN) return ['ok' => false, 'error' => 'Each slot must be at most 80 characters.'];
            $slots[] = $label;
        }
        if (count($slots) > self::MAX_SLOTS) return ['ok' => false, 'error' => 'Too many slots (max 50).'];
        return ['ok' => true, 'title' => $title, 'description' => $description, 'slots' => $slots];
    }
}