# Setup and deployment

Two parts: **A** runs the LMS on a Windows PC with XAMPP (development, or a single office PC). **B** puts it on a server through git.

> **Runtime warning, read first.** The current XAMPP has **PHP 8.2.12** (October 2023) and **MariaDB 10.4.32**. Both are unsafe for a live site:
> - PHP published critical security releases on 21 October 2026 (8.2.35, 8.3.36 and the matching 8.4/8.5 releases). PHP 8.2 security support ends on 31 December 2026.
> - MariaDB 10.4 is no longer maintained. Maintained long-term releases: **10.11, 11.4, 11.8**.
>
> **On the server, use PHP 8.4 (latest patch) and MariaDB 11.4 or 11.8 LTS.** The code has only been tested on PHP 8.2.12 and MariaDB 10.4.32 so far. The first deployment on the newer versions must be followed by the acceptance checklist before real families use it (see "Not yet tested" in RELEASE_NOTES.md).

---

## A. Local setup with XAMPP (Windows)

1. Install XAMPP. Start **Apache** and **MySQL** from the XAMPP control panel.
2. Get the code into `C:\xampp\htdocs\student-management-lms`:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/nilushamadhuwanthi123/student-management-lms.git
   cd student-management-lms
   git checkout feature/lms-enhancements-safe
   ```
3. Create the database (empty):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE student_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   ```
4. `copy .env.example .env` and check `DB_NAME`, `APP_URL=http://localhost/student-management-lms`.
5. Create the tables: `C:\xampp\php\php.exe bin\migrate.php`
6. Demo data is optional, for training only: `C:\xampp\mysql\bin\mysql.exe -u root student_lms < database\seeds\demo_seed.sql`. Never load it on a live server.
7. Create the first super admin: `C:\xampp\php\php.exe bin\create_admin.php --name="Your Name"`. Note the login ID and temporary password it prints. Migration 001 also creates a built-in `SA20260001` with a default password; switch it off with `C:\xampp\php\php.exe bin\accounts_audit.php --disable --yes` unless you are only using demo data.
8. Open http://localhost/student-management-lms

---

## B. Server deployment through git

### Requirements
- PHP **8.4** (latest patch) with `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`
- MariaDB **11.4 or 11.8 LTS** (or 10.11). Migrations 011–013 use MariaDB syntax; MySQL 8 is not supported without edits (MIGRATIONS.md).
- Apache with `mod_rewrite`; a domain with HTTPS (Let's Encrypt is fine)
- git on the server

### First install
```bash
cd /var/www
git clone https://github.com/nilushamadhuwanthi123/student-management-lms.git lms
cd lms
git checkout main            # or the release branch/tag agreed with the client
cp .env.example .env         # then edit, see below
```
Edit `.env`:

| Setting | Value on the live server |
|---|---|
| `APP_ENV` | `production`. This also blocks every account still using a demo/default password |
| `APP_DEBUG` | `false` (true shows error details to visitors) |
| `APP_URL` | the real https address, e.g. `https://lms.example.lk` |
| `DB_USER` / `DB_PASS` | a dedicated database user with a long random password, **not root** |
| `APP_TIMEZONE` | `Asia/Colombo`. The database session follows it automatically |

Database user with only the rights the app needs:
```sql
CREATE DATABASE student_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lms_app'@'localhost' IDENTIFIED BY '<long random password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON student_lms.* TO 'lms_app'@'localhost';
```

Then:
```bash
php bin/migrate.php                          # creates all tables (001-013)
php bin/create_admin.php --name="Harshani Dilrukshi" --email=...
php bin/accounts_audit.php --disable --yes   # switches off the built-in SA20260001 from migration 001
php bin/accounts_audit.php                   # must now report 0 default-password accounts
```

Web server:
- If the site is **not** served from `/student-management-lms`, change `RewriteBase` in `.htaccess` and `public/.htaccess` (for a domain root: `/` and `/public/`). Better: point the virtual host's DocumentRoot at `public/`.
- File permissions: the web server user needs write access to `storage/uploads`, `storage/logs`, `storage/cache` **only**. Everything else read-only.
- Check that `https://<site>/storage/uploads/` and `https://<site>/.env` return **404**, not a file.
- Force HTTPS (redirect http → https).

### Updating an existing server
```bash
cd /var/www/lms
# 1. backup (BACKUP_AND_RESTORE.md)
git fetch origin
git log --oneline HEAD..origin/main      # read what is coming
git pull --ff-only
php bin/migrate.php --status             # see pending migrations
php bin/migrate.php                      # apply them
```
**Existing install made before the migration runner** (the tables were created by hand): run `php bin/migrate.php --baseline=012` **once** to record 001–012 as applied, then `php bin/migrate.php`.

To roll back code: `git checkout <previous commit>`. Database changes are not rolled back automatically; restore the backup taken in step 1.

### Scheduled jobs (every 10 minutes)
```
*/10 * * * * cd /var/www/lms && php bin/send_reminders.php  >> storage/logs/cron.log 2>&1
*/10 * * * * cd /var/www/lms && php bin/publish_notices.php >> storage/logs/cron.log 2>&1
```
Windows: Task Scheduler, `C:\xampp\php\php.exe C:\xampp\htdocs\student-management-lms\bin\send_reminders.php` (same for publish_notices). Both scripts refuse web requests.

### Go-live checklist (deployer)
- [ ] PHP and MariaDB versions as above, latest patches
- [ ] `.env`: production, debug off, own DB user, https URL
- [ ] `php bin/accounts_audit.php` reports **0**; no demo data loaded
- [ ] `/.env`, `/storage/...`, `/database/...`, `/bin/...` all return 404 from the browser
- [ ] HTTPS valid, http redirects
- [ ] Backup job running and one restore tested (BACKUP_AND_RESTORE.md)
- [ ] Scheduled jobs running (`storage/logs/cron.log`)
- [ ] Client acceptance checklist signed (ACCEPTANCE_CHECKLIST.md)

### Errors and logs
- PHP errors go to the web server error log; with `APP_DEBUG=false` visitors see a plain error page.
- The app never logs passwords, temporary passwords or reset codes. Audit entries record who issued a reset code and when, never the code.
- Never commit `.env`. If a secret leaks, change it at once.
