# Video Editor Production & Follow-Up Tracker

A lightweight internal tool for tracking short-form video production from
assignment to final approval — built to the MVP PRD: no database, JSON-file
storage, PHP + vanilla JS/CSS. WhatsApp stays the channel for sharing videos
and feedback; this app just records assignments, submissions, versions,
approvals, and produces weekly/monthly reports.

## Requirements

- PHP 8.0+ with the `session`, `json`, and `fileinfo` extensions (all
  enabled by default on virtually any shared host).
- Apache with `mod_rewrite`/`.htaccess` support is assumed for the security
  rules in `.htaccess` and `data/.htaccess`. On nginx, see **Nginx notes**
  below — the app still works, but you must add the equivalent `location`
  blocks yourself.
- No database, no Composer dependencies, nothing to build.

## Getting Started

1. Point your web server's document root at this folder (or just run the
   PHP built-in server for local testing):

   ```bash
   php -S localhost:8000
   ```

2. Visit the site. Since no accounts exist yet, you'll land on a one-time
   **"Create Admin Account"** screen instead of the login form. Fill it in —
   this becomes your first admin/founder login. That screen only appears
   while `data/users.json` is empty; once an account exists, it's gone for
   good and everyone sees the normal login form.

   Alternatively, create the first admin from the command line instead:

   ```bash
   php bin/create_admin.php "Founder Name" "founder@example.com" "a-strong-password"
   ```

3. Log in as admin → **Team** → add your editors (and any extra admins).
   Each new account gets a random temporary password shown once on screen —
   share it with that person however you like (WhatsApp, in person, etc.)
   and have them change it under **Account** after they log in.

4. **Projects** → create your projects/categories (e.g. "Hacking RA",
   "PRINZA"). **Videos** → assign work to an editor with a title, project,
   and deadline.

That's it — the day-to-day loop is: admin assigns → editor shares the video
in WhatsApp and clicks *Submit Version* in the tracker → admin reviews the
video in WhatsApp and clicks *Approve* or *Request Changes* in the tracker.

## Project Layout

```
config.php              Bootstrap: sessions, error handling, security headers
includes/
  Storage.php            JSON "table" with file locking + atomic writes
  functions.php           Escaping, CSRF, dates, status/overdue logic
  auth.php                 Login, sessions, roles, lockout
  layout.php               Shared page header/footer
  reporting.php             Weekly/monthly period + metric calculations
admin/                    Admin-only pages (require_role('admin') on every one)
editor/                   Editor-only pages (require_role('editor') on every one)
actions/                  POST-only handlers behind CSRF checks; redirect back
assets/                  style.css, app.js (no build step, no CDN dependency)
data/                    *.json data files — NOT web-accessible (see below)
bin/create_admin.php     CLI account bootstrap
```

## Data & Backups

All data lives in `data/*.json.php`:

- `users.json.php` — accounts (passwords are hashed with `password_hash()`,
  never stored in plain text)
- `projects.json.php`
- `videos.json.php` — one row per assignment
- `versions.json.php` — one row per submitted version, linked to a video

(The `.json.php` extension is deliberate, not a typo — see **Data is not
web-accessible** below.)

Writes are **file-locked** (`flock`) and **atomic** (written to a temp file,
then `rename()`'d over the original), so two people saving at the same
moment can't corrupt a data file or silently clobber each other's change —
see `includes/Storage.php`.

Because there's no database, **the `data/` folder is your database — back
it up.** The easiest way: as admin, go to **Reports → Download Full Data
Backup (JSON)** to export everything in one file, or just copy the `data/`
folder itself. Do this on a regular schedule (e.g. a cron job that copies
`data/` somewhere off-server nightly).

### Data is not web-accessible

Three independent layers, so a request for a data file never returns raw
JSON regardless of server/config:

1. **Self-denying files (works everywhere).** Every `data/*.json.php` file
   *is* a PHP script: it starts with a line that calls
   `http_response_code(403); exit;` before the JSON payload. Request one
   directly over HTTP on any PHP-capable server (Apache, nginx, the PHP
   built-in dev server, misconfigured or not) and the server executes that
   line and stops — never the data after it. `includes/Storage.php` strips
   that header line back off before `json_decode`-ing on the way in, and
   re-adds it on every write.
2. **`data/.htaccess`** additionally denies all access to the `data/`
   directory outright (Apache 2.2 and 2.4 syntax both included).
3. **`data/index.php`** returns a 403 for bare directory requests, as a
   second static fallback.

Even so, prefer hosting with `data/` **outside the public web root** where
your hosting setup allows it — move the `data/` folder up a level and
change `DATA_DIR` in `config.php` to match; layer 1 keeps working either
way.

## Security Notes

- Passwords: hashed with `password_hash()` / verified with
  `password_verify()`; never logged or stored in plain text (except the
  one-time on-screen reveal when an admin creates an account or resets a
  password — tell the user to change it).
- Sessions: `httponly`, `SameSite=Lax` cookies, auto-`secure` when served
  over HTTPS, session ID regenerated on every login.
- Login lockout: 5 failed attempts locks an account for 5 minutes.
- CSRF: every state-changing form carries a per-session token, checked on
  every POST in `verify_csrf()`.
- Output escaping: all user-supplied text is passed through `e()`
  (`htmlspecialchars`) before being echoed — no raw interpolation.
- Authorization: every admin page calls `require_role('admin')`, every
  editor page calls `require_role('editor')`; editors can only ever see and
  act on videos assigned to their own account (enforced server-side in
  `actions/submit_version.php`, not just hidden in the UI).
- Security headers: `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy` set on every response.

## Nginx notes

If you're on nginx instead of Apache, the `.htaccess` files are ignored.
Add the equivalent yourself, e.g.:

```nginx
location /data/ { deny all; return 403; }
location ~ /(includes|config\.php)$ { deny all; return 403; }
```

## What's intentionally not here (v1 scope)

Per the PRD: no WhatsApp API integration, no video upload/hosting, no
notifications/email, no payroll/attendance, no Kanban/calendar, no
database. These are documented "future features" once the MVP proves out.

## Timezone

`config.php` sets the app's timezone (`date_default_timezone_set`) — it
defaults to `UTC`. Change it to your local timezone (e.g. `Asia/Kolkata`)
so deadlines and timestamps display correctly for your team.
