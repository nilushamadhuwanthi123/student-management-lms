# Release candidate: 25 October 2026

Branch `feature/lms-enhancements-safe`, on top of commit `212f6f9`. Not yet committed.

## Fixed in this round
| Problem | Effect before | Fix |
|---|---|---|
| **Student registration set no password** (`password_hash` left empty) | New students could never log in; on a strict-mode database registration failed outright | Random temporary password, shown once, must be changed at first login |
| **Guardian details on the registration form were thrown away** | No parent login was ever created for new students | Parent login created from guardian name + phone, with its own temporary password |
| **Student search returned an error page** (repeated SQL placeholder) | Searching by name/ID/phone on the Students page failed for every role | Separate placeholders; search characters escaped |
| First-login password change could be skipped | A user given a default password could go straight to the dashboard | Every page redirects to the password form until it is changed |
| Deactivation, role change and password change did not affect open sessions | A removed staff member stayed logged in until the session expired | Account re-checked on every request; sessions end at once |
| Staff accounts created with the fixed password `Password@123`; admins chose and saw other people's new passwords | Anyone knowing the default could take over a new account | Random temporary passwords; admins issue one-time reset codes instead |
| No password recovery | Forgotten passwords had no safe path | Office-issued one-time reset codes (hashed, 30 min, single use, 5 tries, rate-limited) |
| Login rate limit was per ID only | Guessing across many IDs was not slowed | Extra limit per address (30 failures / 15 min) |
| "Remember me" box did nothing | Misleading | Removed |
| Settings pages visible to branch admins, but saving refused | Confusing; showed system-wide settings | Settings pages and links: super admin only |
| Demo/default passwords usable on a live server | Shared demo password = open door | With `APP_ENV=production`, default passwords cannot log in; `bin/accounts_audit.php` finds and switches them off |
| Dashboard said "Across all institutes" to branch users; "Configure" link shown to admins who could not use it | Misleading | Wording per scope; link super admin only |
| **Test bug:** "direct material link refused when unpaid" failed | Looked like a fee bypass | The app was correct: the test crashed when its student had no unpaid class, then requested an empty ID. Test fixed and a full regression test added (below) |

## Added
- **Today's work** on the admin, coordinator and staff dashboards: payment slips to check, new enrolment inquiries, open help requests, approvals waiting, unpaid fees this month, marked absent today. Each shows a real count for the viewer's branch and links to the page that handles it; items the role cannot act on are not shown.
- `bin/migrate.php` (migration runner with ledger), `bin/create_admin.php`, `bin/accounts_audit.php`.
- Migration `013_account_security.sql`.
- Sinhala for all new screens (dictionary: 849 entries).
- Docs: DEPLOYMENT (XAMPP + git deployment), ACCOUNTS_AND_RECOVERY, WHATSAPP, this file; MIGRATIONS, BACKUP_AND_RESTORE and ACCEPTANCE_CHECKLIST updated.

## Tests run (25 Oct 2026, PHP 8.2.12, MariaDB 10.4.32)
Tests marked *(copy)* ran against an isolated copy of the app and database (`lms_rc` / `student_lms_rc`), with a test super admin that exists only there.

| Suite | Result |
|---|---|
| Role and branch matrix *(copy)*: super admin, 5 admins, 5 coordinators, 3 staff, 5 teachers, student, parent | **148 / 148 passed** |
| Unpaid student vs. class material *(copy)*: class page, open link, direct download URL, raw storage path, path traversal, assistant (AJAX), parent, other-branch admin, then paid | **20 / 20 passed** |
| Login, registration credentials, first-login, reset codes, session end on deactivate/role change/reset, idle expiry, production default-password block *(copy)* | **50 / 50 passed** |
| Fresh install: empty database → migrate → create_admin → login *(copy)* | passed |
| Private files over the web (`.env`, `.git`, storage, database, bin, config) | all 403/404 |
| Upload backup → restore drill | 15/15 files identical |
| Existing suites on the real app: all pages, portals, grade isolation, features (homework, help, notices, slips), public site, assistant, scope | all passed |
| Branch isolation on the real app | 43 / 48: the 5 failures are the super-admin checks, **blocked** because the real super admin password is unknown (not guessed). The same checks passed on the copy |

## Not yet tested (blocked or not run)
- **PHP 8.4 / MariaDB 11.4.** Not run. The app has only been tested on PHP 8.2.12 / MariaDB 10.4.32. Must be tested on the server before go-live (DEPLOYMENT.md).
- Production server, HTTPS, server cron: not set up. The deployer does this.
- Real phones (layouts were checked only by resizing a desktop browser).
- Quiz timer expiry and attendance-edit report accuracy: covered only by the older page-level suites, not by a new targeted test.
- WhatsApp: prepare and log only; no provider (WHATSAPP.md).
- Sinhala wording: not reviewed by a native speaker.

## Remaining release blockers
1. Run the app on **PHP 8.4 + MariaDB 11.4/11.8 LTS** on the server and repeat the acceptance checklist. PHP 8.2.12 must not be used live.
2. On the live server, `bin/accounts_audit.php` must report **0** default-password accounts (this includes the built-in `SA20260001` from migration 001).
3. Client acceptance checklist signed.

## Known limitations
- One parent phone number = one child's parent login.
- Not built (client to approve scope): Science topic tagging, past-paper library, absence notifications to parents, learning-gap analytics.
