# Reference sites → requirements

Studied on 2026-10-24 by reading each public website. Nothing was signed up for and no restricted areas were visited.
**Observed** means the site's public pages say so. **Claimed** is vendor marketing that we could not verify. **Inferred** is our own reading of what a Sri Lankan tuition class needs.

| Source | What was seen | How this LMS answers it | Status |
|---|---|---|---|
| SmartClass | Attendance as present / absent / late (observed) | Attendance module with QR terminal; branch-scoped reports | Existing, now branch-scoped |
| SmartClass | Cash and bank payments with PDF receipts (observed) | Office records cash; family uploads bank slip; receipt after approval | Implemented + tested |
| SmartClass | Zoom / Meet links for classes (observed) | `delivery_mode` online/hybrid with a private `meeting_url`, shown only to enrolled students who have paid for the month | Implemented + tested |
| SmartClass | MCQ and paper marks, ranks (observed) | Timed quizzes (existing); homework marking; rankings stay private to staff | Existing + homework added |
| SmartClass | Works on phones and slow connections (claimed) | Responsive layout checked at 375/768/1024/1440 px; no heavy front-end framework | Checked on desktop browser emulation only |
| TutoraX | Slip approval / rejection with a reason (observed) | Approve (full or part amount) or reject with a required reason; family is notified | Implemented + tested |
| TutoraX | Chat-style support (observed) | Help & questions: questions go to the class teacher, support goes to the branch office | Implemented + tested |
| TutoraX | Events, materials, protected recordings (observed) | Calendar (existing), monthly materials locked until the month's fee is paid | Existing |
| DreamClass | OCR slip reading, 1080p video, 99.98% uptime (claimed) | **Not copied.** Slips are checked by a person; no uptime promise is made | Deliberately not done |
| DreamClass | Multi-branch dashboards (claimed) | `BranchScope`: every admin, coordinator and staff member sees only their branch | Implemented + tested |
| DreamClass | Fee lock-out, homework submission (claimed) | Materials and homework hand-in need the month paid | Implemented + tested |
| e-Thaksalawa | Content by grade, subject and language; past papers (observed) | Grades 6–11, EN/සිං interface. Topic tagging and a past-paper library are **not built yet** | Partly |
| Tution.lk | Student-code login, payment history, EN/සිං toggle (observed) | Student ID login, parent phone login, fee history and receipts, language switch | Existing |
| (Inferred) | Parents find classes through a public page | Public home, timetable, branch pages and an enrolment inquiry form that goes to the right branch office | Implemented + tested |
| (Inferred) | Fake numbers damage trust | The public site shows only real data from the database: no student counts, results or ratings | Implemented + tested |
