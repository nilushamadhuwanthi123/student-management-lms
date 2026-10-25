# Deploying to a server

Tested only on XAMPP (PHP 8.2, MariaDB 10.4) on Windows. **A production server has not been set up or tested yet.**

## Requirements

- PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`
- MySQL 8 or MariaDB 10.4+, `utf8mb4`
- Apache with `mod_rewrite` (the two `.htaccess` files route everything through `public/index.php`)

## Steps

1. Copy the code. Make sure `.env`, `storage/uploads/*` and `storage/logs/*` are **not** copied from the development machine.
2. Create `.env` from `.env.example`:
   - `APP_ENV=production` and `APP_DEBUG=false` (debug shows error details to visitors)
   - `APP_URL`: the real https address
   - a dedicated database user with a strong password, not `root`
   - `APP_TIMEZONE=Asia/Colombo`. The database connection follows this clock automatically.
3. If the site is not served from `/student-management-lms`, change `RewriteBase` in both `.htaccess` files.
4. Import the database: run migrations 001–012 in order (MIGRATIONS.md). Do **not** load the demo seed on a live server.
5. Permissions: the web server needs write access to `storage/uploads` and `storage/logs` only.
6. HTTPS: install a certificate (for example Let's Encrypt) and redirect http to https. Sessions and payment slips must not travel unencrypted.
7. Create the real super-admin account, then change or remove every demo account (all demo passwords are `Demo@12345`).

## Scheduled jobs

Run both every 10 minutes (Windows Task Scheduler, or cron on Linux):

```
php bin/send_reminders.php     # calendar reminders
php bin/publish_notices.php    # notices scheduled for a later time
```

Both are command-line only and refuse web requests.

## WhatsApp

The WhatsApp module builds messages and logs them, but **no WhatsApp provider is connected**.
Real sending needs a WhatsApp Business API account. Its keys belong in `.env`, never in the code.

## Logs and secrets

- PHP errors go to the server error log. The app never logs passwords, tokens or slip contents.
- Never commit `.env`. If a secret leaks, change it immediately.
- `robots.txt` and a `noindex` tag keep the portal pages out of search engines. The public pages stay indexable.
