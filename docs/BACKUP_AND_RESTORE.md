# Backup and restore

A full backup has **two parts**. You need both.

1. **The database**: students, marks, fees, slips and audit log.
2. **Uploaded files** in `storage/uploads`: payment slips, homework, help attachments and materials. These are not in git.

## Take a backup (daily, and before every update)

```
set D=%date:~-4%%date:~4,2%%date:~7,2%
C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --routines --triggers student_lms > D:\lms-backup\db_%D%.sql
robocopy C:\xampp\htdocs\student-management-lms\storage\uploads D:\lms-backup\uploads_%D% /E
```

- Keep copies **off the computer** as well (external drive or cloud storage). A backup on the same disk does not survive a disk failure.
- Keep at least 14 daily copies and one copy per month.
- The dump holds personal data about children and parents. Store it encrypted or in a private place.

## Check a backup (once a month)

Restore it into a **separate** test database and open a few pages. Never test against the live database.

```
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE lms_restore_test CHARACTER SET utf8mb4"
C:\xampp\mysql\bin\mysql.exe -u root lms_restore_test < D:\lms-backup\db_YYYYMMDD.sql
```

## Restore after a failure

This is a manual step and overwrites live data, so the person doing it must be sure.

1. Put the site in maintenance mode (stop Apache) so nobody writes while you restore.
2. Take one more backup of the current state, even if it is broken.
3. Load the chosen dump into `student_lms`, then copy the matching `uploads_YYYYMMDD` folder back into `storage/uploads`.
4. Start Apache. Log in as an admin and check students, fees and one payment slip.

There is **no automatic restore button** in the LMS, on purpose.
