# Release candidate: 25 October 2026

Branch `feature/lms-enhancements-safe`, on top of commit `212f6f9`. Not yet committed.

## Update: learning tools (25 Oct 2026, evening)
| Added | What it does |
|---|---|
| **Topics** (`/topics`) | The teacher's own list of units per grade (English and Sinhala names). Nothing is pre-filled: the units come from her syllabus, not invented here. Notes, quizzes, single questions and homework can be tagged with a unit; only units of that class's grade are offered |
| **Past paper library** (`/past-papers`, students: `/my/papers`) | Papers and marking schemes by grade, year, paper type and medium (PDF/PNG/JPG checked by content, 15 MB). Students see published papers of their own grade, can open them when this month is paid, and the marking scheme only after the teacher releases it |
| **Class register + absence alerts** (`/attendance/register`) | One class, one day, every enrolled student; QR scans show as present. Marking a student absent sends **one** alert per class per day: a portal notice to the parent(s) and the student, plus a WhatsApp message *prepared* for the office. Registers older than yesterday save without alerting |
| **Learning gaps** (`/classes/{id}/learning-gaps`) | Per class: unit averages (hardest first) from marked quizzes (latest attempt, unanswered = 0) and marked homework; students below 50% per unit; each student's weakest unit; questions most often wrong; quizzes and homework not done |

Also fixed:
- **Attendance had no unique key**, so re-marking added a second row instead of updating (reports could double count). Migration 014 adds the key; the development database had no duplicates.
- Manual attendance marking accepted any status and date; it now accepts only present, late or absent.
- Sinhala: "slip" and "receipt" were both "රිසිට්පත". Now slip = **ස්ලිප් පත**, receipt = **කුවිතාන්සිය**, recording = **පටිගත කිරීම්** (36 entries corrected). 102 new entries; 951 in total.

Tests for this update *(copy)*: learning tools **46/46** (exact unit percentages checked against known marks); new pages in Sinhala **13/13**.

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
- Sinhala wording: terminology made consistent, but not yet read by a native speaker.
- New pages on real phones: layouts use the existing responsive grid, but they were not checked at phone width.

## Remaining release blockers
1. Run the app on **PHP 8.4 + MariaDB 11.4/11.8 LTS** on the server and repeat the acceptance checklist. PHP 8.2.12 must not be used live.
2. On the live server, `bin/accounts_audit.php` must report **0** default-password accounts (this includes the built-in `SA20260001` from migration 001).
3. Client acceptance checklist signed.

## Known limitations
- One parent phone number = one child's parent login.
- Topics start empty; the teacher must enter her units before the learning-gaps page can group by unit.
- Absence alerts reach families in the portal; the WhatsApp copy still needs a staff member to press Send.
- Sinhala has not yet been read by a native speaker.
