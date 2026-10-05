# Student Management System + LMS

A comprehensive student management and learning management system built with Core PHP, MySQL, and XAMPP. Designed for educational institutes in Sri Lanka to manage students, enrollments, payments, attendance, workflows, and more.

## Features

- **Multi-Role Authentication** — Super Admin, Admin, Coordinator, Staff, Teacher, Student, and Parent login portals with role-based access control
- **Student Management** — Full CRUD with profile photos, contact details, guardian linking, and student ID generation
- **Multi-Institute Support** — Manage multiple institutes/branches with dedicated coordinators
- **Class & Enrollment** — Multi-class enrollment per student with per-class scholarship types and monthly fees
- **Payment Tracking** — Monthly fee generation, payment recording, scholarship handling, and fee-risk analysis
- **Workflow Engine** — Multi-step approval chains for student deactivation and deletion with configurable approver roles
- **Audit Logging** — Complete audit trail for all sensitive operations with user attribution
- **Guardian/Parent Portal** — Parent accounts linked to students with independent authentication
- **Secure File Uploads** — Profile photos served through authenticated controller (not public directory)
- **CSRF Protection** — Time-based token validation on all forms
- **Rate Limiting** — Login attempt throttling with configurable lockout
- **Security Headers** — Content-Security-Policy, X-Frame-Options, and other hardening headers

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.2+ |
| Database | MySQL 8.0+ (InnoDB, utf8mb4_unicode_ci) |
| Server | Apache (XAMPP) |
| Frontend | Vanilla JS, CSS custom properties (emerald/teal glass design) |
| Architecture | Custom MVC (no framework) |

## Requirements

- [XAMPP](https://www.apachefriends.org/) with PHP 8.2+ and MySQL
- Apache mod_rewrite enabled
- PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`

## Installation

### 1. Clone the Repository

```bash
cd C:\xampp\htdocs
git clone https://github.com/nilushamadhuwanthi123/student-management-lms.git
```

### 2. Configure Environment

```bash
cd student-management-lms
cp .env.example .env
```

Edit `.env` with your database credentials:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=student_lms
DB_USER=root
DB_PASS=
```

### 3. Create the Database

Open phpMyAdmin or MySQL CLI and run:

```sql
CREATE DATABASE student_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import the migration files in order:

```bash
mysql -u root student_lms < database/migrations/001_initial_schema.sql
mysql -u root student_lms < database/migrations/002_auth_security.sql
mysql -u root student_lms < database/migrations/003_contact_and_fixes.sql
```

### 4. Seed Demo Data (Optional)

```bash
mysql -u root student_lms < database/seeds/demo_seed.sql
```

### 5. Set Directory Permissions

Ensure the following directories are writable by Apache:

```
storage/logs/
storage/uploads/profiles/
storage/cache/
public/uploads/profiles/
```

### 6. Access the Application

```
http://localhost/student-management-lms/
```

## Project Structure

```
student-management-lms/
├── app/
│   ├── Controllers/          # Request handlers
│   │   ├── BaseController.php
│   │   ├── AuthController.php
│   │   ├── StudentController.php
│   │   ├── PaymentController.php
│   │   ├── WorkflowController.php
│   │   └── ...
│   ├── Models/               # Database models (BaseModel pattern)
│   │   ├── BaseModel.php
│   │   ├── Student.php
│   │   ├── Enrollment.php
│   │   ├── Payment.php
│   │   ├── Workflow.php
│   │   └── ...
│   ├── Services/             # Business logic layer
│   │   ├── AuthService.php
│   │   ├── FileUploadService.php
│   │   ├── PaymentService.php
│   │   └── WorkflowService.php
│   ├── Middleware/            # Request middleware
│   │   ├── AuthMiddleware.php
│   │   ├── RateLimiter.php
│   │   └── SecurityHeaders.php
│   ├── Validators/           # Input validation
│   └── Views/                # PHP templates
│       ├── layouts/          # admin, auth, public layouts
│       ├── components/       # Reusable UI components
│       ├── students/
│       ├── payments/
│       ├── workflows/
│       └── ...
├── config/                   # App, DB, session, security config
├── database/
│   ├── migrations/           # SQL schema migrations
│   └── seeds/                # Demo data seeds
├── public/                   # Web root (Apache DocumentRoot)
│   ├── index.php             # Front controller & BASE_URL definition
│   ├── .htaccess             # URL rewriting rules
│   └── assets/
│       ├── css/style.css
│       └── js/app.js
├── routes/
│   └── web.php               # Route definitions & Router class
├── storage/
│   ├── logs/
│   ├── uploads/              # Secure file storage (not publicly accessible)
│   └── cache/
├── .env.example
├── .gitignore
└── .htaccess                 # Root-level rewrite to public/
```

## Key Architecture Decisions

- **Subfolder Deployment**: Runs at `/student-management-lms/` via `BASE_URL` constant defined in `public/index.php`. All URLs and redirects use this prefix.
- **Database Singleton**: `Database::connect()` returns a shared PDO instance. All models use this via `BaseModel`.
- **Fillable Pattern**: Models declare a `$fillable` array. `filterFillable()` strips any columns not in the whitelist before insert/update.
- **Multi-Table Auth**: `AuthService` authenticates against `users`, `students`, and `parents` tables sequentially.
- **Workflow Steps**: Approval chains stored as `workflow_steps` rows with `step_number` and `action` ENUM (`pending`, `approved`, `rejected`).

## Database Schema

20+ tables including:

| Table | Purpose |
|-------|---------|
| `users` | Staff/admin accounts with role-based access |
| `students` | Student profiles and contact info |
| `parents` | Guardian/parent accounts linked to students |
| `institutes` | Educational institute/branch records |
| `classes` | Classes belonging to institutes |
| `enrollments` | Student-class enrollments with per-class fees |
| `payments` | Monthly fee records and payment tracking |
| `workflows` | Multi-step approval workflow headers |
| `workflow_steps` | Individual approval steps within workflows |
| `audit_logs` | System-wide audit trail |
| `settings` | Application configuration key-value store |
| `notifications` | In-app notification records |
| `fee_risks` | Fee collection risk assessments |

## Configuration

All configuration is via `.env` file:

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_HOST` | MySQL host | `localhost` |
| `DB_NAME` | Database name | `student_lms` |
| `APP_TIMEZONE` | Application timezone | `Asia/Colombo` |
| `APP_CURRENCY` | Currency code | `LKR` |
| `SESSION_LIFETIME` | Session duration (seconds) | `7200` |
| `MAX_LOGIN_ATTEMPTS` | Failed login threshold | `5` |
| `LOGIN_LOCKOUT_MINUTES` | Lockout duration | `15` |
| `MIN_PASSWORD_LENGTH` | Minimum password length | `8` |

## User Roles

| Role | Access Level |
|------|-------------|
| `super_admin` | Full system access, all settings |
| `admin` | User management, reports, workflows |
| `coordinator` | Institute-level management |
| `staff` | Student records, enrollments |
| `teacher` | Class and attendance views |
| `student` | Own profile, payments, attendance |
| `parent` | Child's profile and payment history |

## License

This project is proprietary software. All rights reserved.
