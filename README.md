# Student Management System + LMS

A student management and learning management system for educational institutes,
built on Core PHP with a custom MVC framework, MySQL, and XAMPP.

## Features

**Students & enrollment**
- Student CRUD with profile photos, contact details and generated display IDs
- Guardian/parent records linked to students, with their own login
- Multi-class enrollment, per-class monthly fees and scholarship types
  (`full_payment`, `half_payment`, `free_card`)
- Student 360 view aggregating profile, enrollments, payments and attendance

**Institutes & classes**
- Multiple institutes/branches, each with an assigned coordinator
- Classes belonging to an institute, with status toggling
- User and role management, including printable ID cards

**Payments**
- Monthly fee generation across active enrollments
- Payment recording, overdue tracking and revenue stats by month
- Fee-risk states (`safe`, `grace`, `high_risk`, `deactivated`, `temporary_access`)

**Attendance**
- QR scan terminal with audible alert, plus manual marking
- Attendance reporting per class and per student

**Workflows**
- Multi-step approval chains for student deactivation, deletion and temporary
  activation, with configurable approver roles and an approval inbox

**Reporting & oversight**
- Five management reports: students, users, classes, payments, attendance
- System-wide audit log with per-user history
- In-app messaging/notification inbox
- Configurable dashboard widgets

**Security**
- Role-based access control across seven roles
- CSRF token validation on all forms
- Login rate limiting with configurable lockout
- Security headers (CSP, X-Frame-Options and others)

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.2+ |
| Database | MySQL 8.0+ (InnoDB, `utf8mb4_unicode_ci`) |
| Server | Apache (XAMPP) |
| Frontend | Vanilla JS, CSS custom properties |
| Architecture | Custom MVC, no external framework |

## Requirements

- [XAMPP](https://www.apachefriends.org/) with PHP 8.2+ and MySQL
- Apache `mod_rewrite` enabled
- PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`

## Installation

### 1. Clone into the web root

```bash
cd C:\xampp\htdocs
git clone https://github.com/nilushamadhuwanthi123/student-management-lms.git
```

### 2. Configure the environment

```bash
cd student-management-lms
cp .env.example .env
```

Edit `.env`:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=student_lms
DB_USER=root
DB_PASS=
```

### 3. Create the database

```sql
CREATE DATABASE student_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import the migrations in order:

```bash
mysql -u root student_lms < database/migrations/001_initial_schema.sql
mysql -u root student_lms < database/migrations/002_auth_security.sql
mysql -u root student_lms < database/migrations/003_contact_and_fixes.sql
```

### 4. Seed demo data (optional)

```bash
mysql -u root student_lms < database/seeds/demo_seed.sql
```

### 5. Make storage writable

Apache needs write access to:

```
storage/logs/
storage/uploads/
storage/cache/
public/uploads/profiles/
```

### 6. Open the app

```
http://localhost/student-management-lms/
```

## Demo Credentials

The demo seed creates accounts for every role. Log in with the **User ID**, not
the email address.

| Role | User ID | Password |
| --- | --- | --- |
| Super Admin | `SA20260001` | `Demo@12345` |
| Admin | `ADM20260001` | `Demo@12345` |
| Coordinator | `CRD20260001` | `Demo@12345` |
| Staff | `STF20260001` | `Demo@12345` |
| Teacher | `TCH20260001` | `Demo@12345` |

The super admin ships with status `pending_first_login`, so the first successful
login forces it to set a new password before anything else is reachable.

> **Before any production or client deployment:** change every one of these
> passwords, and re-seed with your own data. These accounts exist for local
> development only.

## Subfolder Deployment

The app is served from a subfolder, not the domain root. `public/index.php`
derives a `BASE_URL` constant from the request path, and both `.htaccess` files
set `RewriteBase` to match.

Every internal link must be built from `BASE_URL`:

```php
<a href="<?= BASE_URL ?>/students">Students</a>
<form action="<?= BASE_URL ?>/login" method="POST">
$this->redirect(BASE_URL . '/payments');
```

A root-relative path such as `href="/login"` escapes the app and lands on the
XAMPP dashboard. To deploy at a different path, change `RewriteBase` in
`.htaccess` and `public/.htaccess`; `BASE_URL` adapts on its own.

## Project Structure

```
student-management-lms/
├── app/
│   ├── Controllers/        # 18 request handlers, extending BaseController
│   ├── Models/             # 14 models, extending BaseModel
│   ├── Services/           # AuthService, PaymentService, WorkflowService
│   ├── Middleware/         # AuthMiddleware, RateLimiter, SecurityHeaders
│   ├── Validators/         # Input validation
│   └── Views/              # PHP templates
│       ├── layouts/        # admin, auth, public, app
│       ├── components/     # Reusable UI partials
│       └── students/ payments/ workflows/ reports/ attendance/
│           users/ institutes/ classes/ messages/ settings/ audit/
├── config/                 # App, DB, session, CSRF, security config
├── database/
│   ├── migrations/         # 001_initial_schema, 002_auth_security,
│   │                       # 003_contact_and_fixes
│   └── seeds/              # demo_seed.sql
├── public/                 # Apache serves from here
│   ├── index.php           # Front controller, defines BASE_URL
│   ├── .htaccess           # Front-controller rewrite
│   └── assets/css/ assets/js/
├── routes/web.php          # Router class and 87 route registrations
├── storage/                # logs, uploads, cache (not web-accessible)
├── .env.example
└── .htaccess               # Rewrites into public/
```

## Architecture Notes

- **Database singleton** — `Database::connect()` returns a shared PDO instance;
  every model reaches the database through `BaseModel`.
- **Fillable whitelist** — models declare `$fillable`, and `filterFillable()`
  strips any column outside that list before insert or update.
- **Multi-table auth** — `AuthService` authenticates against `users`, then
  `students`, then `parents`, so all three account types share one login form.
- **Workflow steps** — approval chains are rows in `workflow_steps`, ordered by
  `step_number`, each carrying an `action` ENUM of `pending`/`approved`/`rejected`.

## Database Schema

22 tables. The main ones:

| Table | Purpose |
|-------|---------|
| `users` | Staff and admin accounts with roles |
| `students` | Student profiles and contact details |
| `parents` | Guardian accounts linked to students |
| `institutes` | Institute/branch records |
| `classes` | Classes belonging to an institute |
| `user_classes` | Teacher-to-class assignments |
| `enrollments` | Student-class enrollments, fees and scholarships |
| `payments` | Monthly fee records and payment state |
| `fee_risk_status` | Per-student fee collection risk state |
| `attendance` | Attendance records from QR and manual marking |
| `workflows` | Approval workflow headers |
| `workflow_steps` | Individual approval steps |
| `workflow_inbox` | Pending approvals routed to approvers |
| `notifications` | In-app notifications |
| `contact_messages` | Public contact form submissions |
| `audit_log` | System-wide audit trail |
| `system_settings` | Application configuration |
| `dashboard_settings` | Configurable dashboard widgets |
| `deactivation_settings` | Deactivation policy (key-value) |
| `deletion_settings` | Deletion policy (key-value) |
| `login_attempts` | Rate-limiting counters |
| `remember_tokens` | Persistent login tokens |

Column names are not always the obvious guess — `full_name` rather than
`first_name`/`last_name`, `payments.payment_status` rather than `status`,
`classes.class_name` rather than `name`. See `CLAUDE.md` for the full list.

## Configuration

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_HOST` | MySQL host | `localhost` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_NAME` | Database name | `student_lms` |
| `DB_CHARSET` | Connection charset | `utf8mb4` |
| `APP_ENV` | Environment | `local` |
| `APP_DEBUG` | Verbose errors | `true` |
| `APP_TIMEZONE` | Application timezone | `Asia/Colombo` |
| `APP_CURRENCY` | Currency code | `LKR` |
| `SESSION_LIFETIME` | Session duration (seconds) | `7200` |
| `SESSION_TIMEOUT` | Idle timeout (seconds) | `1800` |
| `CSRF_TOKEN_LIFETIME` | CSRF token validity (seconds) | `3600` |
| `MAX_LOGIN_ATTEMPTS` | Failed login threshold | `5` |
| `LOGIN_LOCKOUT_MINUTES` | Lockout duration | `15` |
| `REMEMBER_TOKEN_DAYS` | "Remember me" duration | `30` |
| `MIN_PASSWORD_LENGTH` | Minimum password length | `8` |

## User Roles

| Role | Access |
|------|--------|
| `super_admin` | Everything, including all settings |
| `admin` | User management, reports, workflows |
| `coordinator` | Institute-level management |
| `staff` | Student records and enrollments |
| `teacher` | Class and attendance views |
| `student` | Own profile, payments, attendance |
| `parent` | Linked child's profile and payment history |

## Branches

`main` carries the integrated application. Seven `fix/*` branches hold
schema-alignment and hardening work that is not yet merged:

| Branch | Scope |
|--------|-------|
| `fix/shared-database-and-api-contracts` | DB connection, API contracts, subfolder deployment |
| `fix/auth-privacy-and-secure-uploads` | Secure file uploads, auth and session hardening |
| `fix/student-guardian-and-enrollment` | Student/guardian registration and enrollment |
| `fix/workflows-and-lifecycle-policies` | Workflow alignment, deactivate/reactivate lifecycle |
| `fix/payments-attendance-and-integration` | Payment, fee-risk, notification, institute schema |
| `fix/subfolder-url-base-path` | `BASE_URL` on every internal link |
| `fix/controller-redirect-base-url` | `BASE_URL` on failure-path redirects |

## Contributing

Read `CLAUDE.md` before committing. It records the commit conventions, the
`BASE_URL` rule, the data-handling policy, and the schema column names that
differ from the obvious guesses.

Never commit credentials, client records, database dumps or uploaded personal
files. Use synthetic data for testing.

## License

Proprietary. All rights reserved.
