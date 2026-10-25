# Acceptance test scripts (Windows / XAMPP)

PowerShell scripts that drive the running app over HTTP with `curl.exe` and check the database with the MySQL client. They are not unit tests and they need the demo data (password `Demo@12345`). **Never run them against a live server with real families' data.**

| Script | Runs against | What it checks |
|---|---|---|
| `rc_setup.ps1` | creates `student_lms_rc` from a dump of `student_lms` | isolated copy + a test super admin (random password in `C:\lms_rc_data`, outside the web root) |
| `rc_sync.ps1` | copies the code to `htdocs\lms_rc` | the isolated app, pointed at `student_lms_rc` |
| `test_roles_rc.ps1` | copy | every role's branch scope, from the explicit database relations |
| `test_materials.ps1` | copy | an unpaid student cannot reach class material by any route |
| `test_auth_rc.ps1` | copy | registration credentials, first login, reset codes, session ending, production default-password block |
| `test_fresh.ps1` | copy, empty database | fresh install with `bin/migrate.php` and `bin/create_admin.php` |
| `test_features.ps1`, `test_public.ps1`, `baseline/*.ps1` | development app | homework, help desk, notices, payment slips, public site, portals |

Order: `rc_setup.ps1` once, then `rc_sync.ps1` after every code change, then the tests. Each test removes the rows it creates.
