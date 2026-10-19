# PlainSlots — Run & Deploy Guide (operator)

**You deploy; this doc tells you exactly how.** Nothing here is automated
on purpose: every step is a copy-paste you can see and control.

- Target: **myapp3mvp.earth-turtle.com** (Hostinger subdomain)
- Stack: PHP 8+ + MySQL, no build steps, repo root = web root
- External APIs: none ($0 running cost)

---

## 1. Create the subdomain (hPanel)

1. hPanel → **Websites → Dashboard** (for earth-turtle.com).
2. **Subdomains → Add subdomain**: name `myapp3mvp`, domain `earth-turtle.com`.
3. Hostinger creates the web root folder automatically, typically:
   `domains/myapp3mvp.earth-turtle.com/public_html`
   (confirm the exact path on the Subdomains page — it is shown there).
4. **PHP version**: hPanel → Subdomain dashboard → **PHP Configuration**.
   Pick **PHP 8.1 or newer** (8.3 recommended). PlainSlots needs no
   extensions beyond `pdo_mysql` (standard on Hostinger).

> Make sure the subdomain has an SSL certificate (hPanel → Security → SSL,
> "Install/Force HTTPS"). PlainSlots works fine on both http and https;
> https is recommended since admin links are secrets.

## 2. Create the MySQL database (hPanel)

hPanel → subdomain dashboard → **Databases → MySQL Databases**:

- Database name: `u<account>_plainslots` (Hostinger prefixes automatically)
- User: create one (e.g. `u<account>_plainslots`) with a strong password
- Keep host `localhost` and port `3306` (Hostinger default for same-server)

Save the three values (name, user, password) — you'll paste them into
`lib/config.php` in step 4.

## 3. Upload the code

Option A — Git (preferred if you have shell/SSH on the plan):
```sh
cd domains/myapp3mvp.earth-turtle.com/public_html
git clone <repo-url> .        # or: git remote add + git pull
```

Option B — File Manager:
1. Zip the repo (WITHOUT `lib/config.php`, `data/`, `.git/`).
2. hPanel → File Manager → upload the zip into `public_html`,
   right-click → Extract. All files should end up directly in
   `public_html` (so `public_html/index.php`, `public_html/.htaccess`, …).

## 4. Configure

```sh
cp lib/config.example.php lib/config.php
```
Then edit `lib/config.php` and fill from step 2:

```php
'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'u<account>_plainslots',
    'user' => 'u<account>_plainslots',
    'pass' => '<the password you set>',
],
'app_url' => 'https://myapp3mvp.earth-turtle.com',
'debug' => false,   // keep false in production
```

`lib/config.php` is gitignored — it will not be committed.

## 5. Run migrations

Follow **migrations/README.md** (phpMyAdmin: paste
`migrations/001_create_polls.sql` into the SQL tab and press Go).
Re-running is safe. This creates the 4 tables: polls, slots,
participants, votes.

## 6. Smoke-test (2 minutes)

1. Open `https://myapp3mvp.earth-turtle.com` — landing page renders.
2. Create a test poll with 2 slots → you land on `/p/<slug>` with a
   one-time admin link.
3. Submit a vote → results table shows tallies.
4. Open the admin link → close voting → the poll page says "closed".
5. Delete the test poll from admin.
6. Verify internals are blocked: `https://…/lib/config.php` → 404
   (checked and verified under real Apache in QA).

## Rollback

PlainSlots is stateless apart from MySQL. To roll back a deploy:
re-upload the previous code (git checkout previous tag / re-extract old
zip). The schema from `001_create_polls.sql` is not version-sensitive;
no down-migration needed for v1.

## Operational notes

- **Backups**: polls live only in MySQL. hPanel has automatic daily
  backups; that's sufficient for an MVP.
- **Rate limiting / spam**: out of scope per scope-lock (no login,
  no rate limiting). Anyone can create polls. Acceptable for MVP;
  revisit if abused.
- **Logs**: with `debug => false`, errors go to the PHP error log only
  (hPanel → subdomain dashboard → Logs), never into HTML output.
- **Updating**: `git pull` (or re-upload changed files) + re-check
  migrations/README.md for NEW numbered files. Migrations are additive
  and idempotent.
