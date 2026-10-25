# Accounts, passwords and recovery

## Who logs in with what
| Person | Login ID | First password |
|---|---|---|
| Staff (admin, coordinator, staff, teacher) | staff ID, e.g. ADM2026xxxx, or email | random temporary password, shown once when the account is created |
| Student | student ID, e.g. STU2026xxxx | random temporary password, shown once at registration |
| Parent | the guardian's phone number given at registration | random temporary password, shown once at registration |

A student ID or a phone number is **not secret**. It is printed on cards and known to classmates, so it is never enough on its own. Every account needs its password.

- Temporary passwords look like `Kp7m-Qx4t`. They are shown **once** on a printable page, and refreshing the page shows nothing.
- At the first login the person must choose their own password. No other page opens until they do.
- Password rule: at least 8 characters, upper and lower case, a number and a symbol. Demo/default passwords are refused.

## Forgotten password: office-issued reset code
The LMS has no verified email or SMS, so recovery goes through the branch office.

1. The person contacts the branch office.
2. **The office confirms who they are**: in person, or by calling back the phone number already on the record (never a number the caller gives now).
3. The office issues a reset code:
   - Student: open the student → **Password reset code**
   - Parent: open the student → Parent / Guardian → **Parent reset code**
   - Staff: Users → Edit → **Issue a password reset code**
4. The code (e.g. `7KQM-2HXP`) is shown once. Give it to the person directly.
5. The person opens the login page → **Forgot password?**, enters their login ID, the code and a new password.

Rules:
- A code works **once**, for **30 minutes**, and only for that account.
- 5 wrong tries cancel the code. A new code cancels the old one.
- 10 failed tries from one address in 15 minutes pause the reset page.
- Only a hash of the code is stored. The audit log records who issued a code and when, never the code itself.
- After a reset, **every open session of that account ends**.

Who may issue codes:
- Super admin: anyone.
- Branch admin and coordinator: students and parents of their branch.
- Branch admin: staff of their branch. Never the super admin, and never themselves.
- Teachers and staff: none.

## Changes take effect at once
- Deactivating an account, changing a staff member's role, or changing or resetting a password ends that person's open sessions on their next click.
- Sessions also end after 30 minutes of inactivity (`SESSION_TIMEOUT` in `.env`).

## Demo accounts: before going live
The demo data uses the shared password `Demo@12345`; older versions created users with `Password@123`.

1. Do not load the demo seed on the live server.
2. Set `APP_ENV=production`. Then **no account using a known default password can log in**, even if one slipped through.
3. Run `php bin/accounts_audit.php`. It lists every account still using a default password and changes nothing.
4. `php bin/accounts_audit.php --disable --yes` deactivates them all.
5. Create the real accounts: `bin/create_admin.php` for the super admin, then the Users page for staff and registration for students. Each gets a temporary password shown once.
6. A real person whose account was switched off gets it back from the office: confirm their identity → reactivate → issue a reset code.

## Known limitation
One parent phone number logs in to **one** child. For a second child, the guardian needs a different phone number as that child's parent login. Registration warns about this.
