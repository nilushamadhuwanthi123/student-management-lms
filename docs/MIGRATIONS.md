# Database migrations

Plain SQL files in `database/migrations`, applied in number order with the MySQL client.
There is no migration runner, so keep a note of what each server has applied.

```
C:\xampp\mysql\bin\mysql.exe -u root student_lms < database\migrations\008_branch_access.sql
```

**Always take a backup first** (see BACKUP_AND_RESTORE.md).

| File | What it does | Safe to re-run? |
|---|---|---|
| 001–007 | Original schema, security, LMS modules, WhatsApp, calendar, assistant | Already on every install |
| 008_branch_access.sql | `user_institutes` table (which staff work at which branch) plus a backfill from coordinators, class teachers and staff | Yes (`IF NOT EXISTS`, `INSERT IGNORE`) |
| 009_engagement.sql | `assignments`, `assignment_submissions`, `help_threads`, `help_messages`, `notices` | Yes (`IF NOT EXISTS`) |
| 010_payment_slips.sql | `payment_slips` | Yes (`IF NOT EXISTS`) |
| 011_slip_details_and_delivery.sql | Slip `bank_name` and `approved_amount`; class `delivery_mode`, `meeting_url`, `capacity` | Yes on MariaDB (`ADD COLUMN IF NOT EXISTS`). See the MySQL 8 note below |
| 012_public_site.sql | `institutes.is_public` and `public_show_fees`; enrolment fields on `contact_messages` | Yes on MariaDB. See the MySQL 8 note below |

## MySQL 8 note

Files 011 and 012 use `ADD COLUMN IF NOT EXISTS`. MariaDB (XAMPP) supports this; **MySQL 8 does not**.
On a MySQL 8 host, delete the words `IF NOT EXISTS` after `ADD COLUMN` in those two files and run each one exactly once.

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
