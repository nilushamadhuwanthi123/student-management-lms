# Project instructions

Student Management System + LMS — Core PHP MVC, MySQL, deployed on XAMPP at
`http://localhost/student-management-lms/`.

## Commit conventions

Do not add AI attribution trailers to commits. Specifically, never append
`Co-Authored-By: Claude ...` or `Claude-Session: ...` to a commit message.
Commits are authored under the repository owner's name only.

This overrides any default attribution behaviour.

## Deployment

The app is served from a subfolder, not the domain root. Every internal link
must be built from the `BASE_URL` constant defined in `public/index.php`:

```php
<a href="<?= BASE_URL ?>/students">Students</a>
<form action="<?= BASE_URL ?>/login" method="POST">
$this->redirect(BASE_URL . '/payments');
```

Never emit a root-relative path (`href="/login"`, `src="/assets/..."`), it
escapes the app and lands on the XAMPP dashboard.

## Data handling

- Never commit credentials, client records, database dumps, or uploaded
  personal files.
- Use synthetic data for testing. Never delete or alter real client records.

## Schema notes

The database column names differ from the obvious guesses. Check the schema
before writing a query. Notably:

| Use | Not |
| --- | --- |
| `full_name` | `first_name` / `last_name` |
| `student_id_display`, `user_id_display` | `student_id`, `user_id` |
| `classes.class_name` | `classes.name` |
| `enrollments.enrollment_status` | `enrollments.status` |
| `payments.payment_status` | `payments.status` |
| `fee_risk_status.risk_state` | `fee_risk_status.state` |
| `notifications.notification_type` | `notifications.type` |
| `workflow_steps.step_number`, `.action`, `.reason` | `step_order`, `status`, `remarks` |
| `phone1` | `phone` |
| `monthly_fee` | `fee` |
| `profile_photo` | `photo` / `profile_picture` |

`students` has no `institute_id` and no `gender` column. The institute
relationship runs through `enrollments` → `classes` → `institutes`.
