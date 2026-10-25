# Database migrations

Plain SQL files in `database/migrations`, applied in number order by the runner:

```
php bin/migrate.php --status     # applied / pending
php bin/migrate.php --dry-run    # what would run
php bin/migrate.php              # apply pending files, in order
```

The runner records each applied file (and its checksum) in `schema_migrations`, so each file runs once.
It stops at the first error and records nothing for that file.

**An install created before the runner existed** (tables made by hand, like the development PC): run
`php bin/migrate.php --baseline=012` once. That records 001-012 as applied without running them; after that, plain `php bin/migrate.php` applies only 013 and later.

**Always take a backup first** (see BACKUP_AND_RESTORE.md).

| File | What it does | Safe to re-run? |
|---|---|---|
| 001–007 | Original schema, security, LMS modules, WhatsApp, calendar, assistant | Already on every install |
| 008_branch_access.sql | `user_institutes` table (which staff work at which branch) plus a backfill from coordinators, class teachers and staff | Yes (`IF NOT EXISTS`, `INSERT IGNORE`) |
| 009_engagement.sql | `assignments`, `assignment_submissions`, `help_threads`, `help_messages`, `notices` | Yes (`IF NOT EXISTS`) |
| 010_payment_slips.sql | `payment_slips` | Yes (`IF NOT EXISTS`) |
| 011_slip_details_and_delivery.sql | Slip `bank_name` and `approved_amount`; class `delivery_mode`, `meeting_url`, `capacity` | Yes on MariaDB (`ADD COLUMN IF NOT EXISTS`). See the MySQL 8 note below |
| 012_public_site.sql | `institutes.is_public` and `public_show_fees`; enrolment fields on `contact_messages` | Yes on MariaDB. See the MySQL 8 note below |
| 013_account_security.sql | `must_change_password` and `credential_version` on accounts; `password_resets` (hashed one-time codes); `schema_migrations` | Yes on MariaDB |
| 014_learning_tools.sql | `topics`; `topic_id` on materials, quizzes, questions, homework; `past_papers`; `absence_alerts`; a **unique key on attendance (student, class, day)** | Yes on MariaDB. Before running on a server, check for duplicate attendance rows (query in the file's header); the key cannot be added while duplicates exist |

## MySQL 8 note

Files 011 to 014 use `ADD COLUMN IF NOT EXISTS`. MariaDB (XAMPP) supports this; **MySQL 8 does not**.
On a MySQL 8 host, delete the words `IF NOT EXISTS` after `ADD COLUMN` in those files and run each one exactly once.

## Tested
- Empty database → `bin/migrate.php` applied 001-013 (43 tables), a second run applied nothing, `create_admin.php` worked and the new admin could log in (MariaDB 10.4.32, 25 Oct 2026).
- Existing development database → `--baseline=012`, then 013 and 014 applied (a backup was taken before each).

## After 008: check the branch assignments

The backfill can only guess for branch admins. On this install, admins were matched to branches in seed order:
ADM20260101 → Pannipitiya, ADM20260102 → Galle, ADM20260103 → Kandy.
On a real server, open **Users → Edit → Branches** for each admin, coordinator and staff member and confirm the branches are right.
A staff member with no branch sees nothing. That is deliberate.

## After 012: choose public branches

Only branches marked public appear on the website (**Institutes → Edit → Show on public website**).
Fees stay hidden unless **Show fees publicly** is also ticked.

## Rolling back

No automatic rollback exists. To undo, restore the backup taken before the migration.
New tables can be dropped by hand **only if they hold no real data**. Never drop `payment_slips` once families have uploaded slips.
