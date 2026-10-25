# Client acceptance checklist

To be ticked by the teacher or office staff on the real server, using real accounts. ☐ = not yet checked by the client.

## Public website
- ☐ Home, Timetable and each branch page show the right classes, days and times
- ☐ No wrong or invented facts (student numbers, results, prices)
- ☐ Inquiry form: a test inquiry reaches **only** the chosen branch office (Inquiries page)
- ☐ Pages read well on a phone

## Branches and access
- ☐ Each branch admin sees only their branch's students, classes, fees and reports
- ☐ Each staff member, coordinator and teacher has the right branches under **Users → Edit**
- ☐ The super admin sees all branches

## Accounts
- ☐ Registering a student shows a temporary password for the student (and parent, if a guardian phone was given), only once
- ☐ The student and parent must choose their own password at first login
- ☐ Forgotten password: the office issues a reset code after confirming identity; the person resets on "Forgot password?"
- ☐ A deactivated account cannot keep using an open session
- ☐ `php bin/accounts_audit.php` on the live server reports 0 accounts with default passwords

## Dashboards
- ☐ Admin, coordinator and staff see "Today's work" with real counts for their branch only, and each card opens the right page

## Fees and payment slips
- ☐ Family uploads a slip → office sees it → approve → fee shows as paid → receipt downloads
- ☐ Rejecting needs a reason, and the family sees that reason
- ☐ Part payment leaves the rest outstanding
- ☐ Materials unlock only after the month is paid

## Classes
- ☐ Online/hybrid class: the join link appears only for paid, enrolled students
- ☐ Creating a class at the same time and room as another is refused

## Learning
- ☐ Teacher creates homework → student submits → teacher marks → student and parent see the mark
- ☐ Student asks a question → class teacher replies
- ☐ Notice to a class reaches only that class's families
- ☐ Quizzes, attendance and progress work as before

## Topics, past papers, register, learning gaps
- ☐ The teacher enters her units for each grade (Topics) and tags a few notes, quizzes and homework
- ☐ Past paper uploaded with a marking scheme; a student of that grade downloads it; the marking scheme opens only after release
- ☐ Class register: mark a test student absent → the parent sees the alert in the portal, and the WhatsApp message is in the message log as "queued"
- ☐ Saving the register again does not alert the parent twice
- ☐ Learning gaps page for a class shows the unit averages and the weakest students, and the numbers match the marks

## Language
- ☐ Sinhala wording read by a native speaker (terminology was made consistent, but no native speaker has reviewed it yet)

## Operations
- ☐ Daily backup set up and one restore tested (BACKUP_AND_RESTORE.md)
- ☐ Scheduled jobs running (DEPLOYMENT.md)
- ☐ Demo accounts removed or deactivated (`bin/accounts_audit.php --disable --yes`)

Signed off by: ______________________   Date: ____________
