# PlainSlots — Migrations

Plain SQL files, run in order. Each file states what it creates and whether
re-running is safe.

## How to run (Hostinger hPanel → phpMyAdmin)

1. Open phpMyAdmin, select the PlainSlots database.
2. For each file below: open the SQL tab, paste the whole file, press Go.
3. Run them in order, once. Re-running is safe (all statements are idempotent).

## CLI alternative (if you have shell access)

```sh
mysql -u DBUSER -p DBNAME < migrations/001_create_polls.sql
```

## Files

### 001_create_polls.sql
Creates the four core tables:

- `polls` — one row per poll; `slug` (public), `edit_slug` (secret admin key)
- `slots` — time-slot rows per poll (`poll_id`, `position`, `label`), unique per poll+position
- `participants` — voter names per poll
- `votes` — one row per (participant, slot) with value yes/maybe/no

Foreign keys cascade: deleting a poll removes its slots, participants and votes.

**Re-run safe:** yes — `CREATE TABLE IF NOT EXISTS` everywhere.