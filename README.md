# PlainSlots

Group time-slot polls. **No login, no ads, ever.** Yes/no/maybe voting, unlimited
free-text slots (soft cap 50), permanent results, one secret admin link per poll.

LAMP per MVP Lab stack standard: PHP 8+ (no frameworks), MySQL via PDO
(prepared statements only), vanilla HTML5/JS/CSS, zero build steps.
**Repo root = web root.**

## Features (scope-locked)

- `/` — create a poll: title, optional description/creator, 1–50 free-text slot rows
  (“Tue 18:00”, “2026-11-03 19:00”). Works with JS off.
- `/p/{slug}` — poll page: vote form (name + yes/maybe/no per slot, upsert by name),
  results table with per-slot tally bars and best-slot highlight
  (score = yes + 0.5×maybe, ties all highlighted), share box with copy button.
- `/admin/{edit_slug}` — secret admin page: edit title/description/slots,
  close/re-open voting, delete poll (typed DELETE + confirm). Wrong link = generic 404.
- `X-Robots-Tag: noindex` on poll + admin pages; landing indexable.

Out of scope (by scope lock): accounts, email, notifications, ads, CSV/.ics export,
QR codes, date pickers, rate limiting, admin-link recovery.

## API (JSON, POST except GET poll)

| Endpoint | Body | Response |
|---|---|---|
| `POST /api/create.php` | `{title, description?, creator?, slots:[…]}` | `{ok, slug, edit_slug}` |
| `GET /api/poll.php?slug=…` | — | `{ok, poll:{title,description,status}, slots:[{id,label}], participants:[{name,votes}], best_slot_ids:[…]}` |
| `POST /api/vote.php` | `{slug, name, votes:{slot_id:"yes\|"maybe\|"no"}}` | `{ok}` |
| `POST /api/admin/edit.php` | `{edit_slug, title, description?, slots:[…]}` | `{ok}` |
| `POST /api/admin/close.php` | `{edit_slug}` (optional `status`:"open"/"closed", absent = toggle) | `{ok, status}` |
| `POST /api/admin/delete.php` | `{edit_slug}` (no-JS form adds `confirm=DELETE`) | `{ok}` |

Validation: title 1..200 chars, slots 1..50 × ≤80 chars (control chars stripped),
name 1..50 chars. Same-name re-vote replaces (case-insensitive, trimmed).
External APIs: **none** — $0.

## Deploy (Hostinger subdomain)

1. Clone/upload repo to the subdomain's web root
2. cp lib/config.example.php lib/config.php — fill db creds from hPanel
3. Run migrations/ per migrations/README.md
4. Done — no build steps

## Dev / running locally

- PHP 8 + Apache (or any docroot server — `.htaccess` does routing).
- For tests and a quick spin: `php -S localhost:8080 router.php` — but note
  the dev router lies about `.htaccess` rules; verify 403/404 under real Apache.
- Unit + e2e tests: `php tests/run.php` (sqlite in-memory; no MySQL needed).

## Security notes

- PDO prepared statements everywhere (`dbq()`), no dynamic SQL.
- Output escaped via `e()` (htmlspecialchars, UTF-8, ENT_QUOTES) in every view.
- `lib/config.php` is gitignored; secrets never in repo.
- `.htaccess` blocks `/lib /src /tests /migrations /views /data`, `config.example.php`,
  and `api/admin/_guard.php`; `Options -Indexes`.
- Admin URLs use 16-char CSPRNG base62 secrets; unknown = generic 404.
## Run / Deploy

See **HOSTINGER-DEPLOY.md** for the full operator guide (subdomain
myapp3mvp.earth-turtle.com, database, config, migrations, smoke test).
Quick start: upload repo → `cp lib/config.example.php lib/config.php`
→ fill db creds → run `migrations/001_create_polls.sql` → done.

## QA verdict (2026-10-19)

stack-standard: **PASS**. Browser pass done (Chromium/Playwright) under
real Apache 2.4 + PHP 8.3 against both sqlite and real MySQL 8 (fresh
DB from migrations, re-run safe). 48/48 unit tests. Admin close/delete
via no-JS forms end on raw JSON (cosmetic, see QA card). XSS escaped,
hostile input rejected, internals 404, config-missing message clean.
