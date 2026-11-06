$base = "http://localhost/student-management-lms"
$mysql = "C:\xampp\mysql\bin\mysql.exe"

function Get-Csrf($html) { [regex]::Match($html, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Get-Title($html) { [regex]::Match($html, '<title>([^<]+)</title>').Groups[1].Value }
function Q($sql) { $o = & $mysql -u root -N -e "USE student_lms; $sql"; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function Check($label, $ok) { "{0,-50} {1}" -f $label, $(if ($ok) { "OK" } else { "FAIL" }) }

function Login($userId, $password) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest -Uri "$base/login" -WebSession $s -UseBasicParsing
    $null = Invoke-WebRequest -Uri "$base/login" -WebSession $s -Method POST -UseBasicParsing `
        -Body @{ user_id = $userId; password = $password; '_csrf_token' = (Get-Csrf $r.Content) }
    return $s
}

# ============ STUDENT ============
"=== STUDENT PORTAL ==="

# A student with a paid class this month and an unpaid one, ideally.
$studentDisplay = Q "SELECT s.student_id_display FROM students s JOIN enrollments e ON e.student_id=s.id JOIN classes c ON c.id=e.class_id WHERE s.student_id_display LIKE 'STU2026%' AND c.subject='Science' GROUP BY s.id LIMIT 1;"
$studentId = Q "SELECT id FROM students WHERE student_id_display='$studentDisplay';"
"Student $studentDisplay (id $studentId)"

$ss = Login $studentDisplay 'Demo@12345'

$r = Invoke-WebRequest -Uri "$base/my/classes" -WebSession $ss -UseBasicParsing
Check "my classes renders" ($r.StatusCode -eq 200 -and ((Get-Title $r.Content) -like "My classes*" -or (Get-Title $r.Content) -like "මගේ පන්ති*"))
Check "my classes clean of PHP errors" ($r.Content -notmatch 'Fatal error|Warning:|Undefined')
Check "gate state shown on cards" ($r.Content -match 'Open|Locked')

# --- an OPEN class shows material ---
$openClass = Q "SELECT p.class_id FROM payments p JOIN enrollments e ON e.student_id=p.student_id AND e.class_id=p.class_id WHERE p.student_id=$studentId AND p.payment_status='paid' AND p.month_year=DATE_FORMAT(CURDATE(),'%Y-%m-01') LIMIT 1;"
if ($openClass -ne "") {
    $r = Invoke-WebRequest -Uri "$base/my/classes/$openClass" -WebSession $ss -UseBasicParsing
    Check "paid class opens" ($r.Content -notmatch 'is locked')
    Check "paid class lists material" ($r.Content -match 'material')
} else { "  (no paid class this month for this student - skipped)" }

# --- a LOCKED class hides material ---
$lockedClass = Q "SELECT p.class_id FROM payments p WHERE p.student_id=$studentId AND p.payment_status IN ('pending','overdue') AND p.month_year=DATE_FORMAT(CURDATE(),'%Y-%m-01') LIMIT 1;"
if ($lockedClass -ne "") {
    $r = Invoke-WebRequest -Uri "$base/my/classes/$lockedClass" -WebSession $ss -UseBasicParsing
    Check "unpaid class shows the lock" ($r.Content -match 'is locked')

    # The real test: no material title may appear anywhere in the body.
    $titles = (& $mysql -u root -N -e "USE student_lms; SELECT title FROM class_materials WHERE class_id=$lockedClass AND month_year=DATE_FORMAT(CURDATE(),'%Y-%m-01');")
    $leaked = $false
    foreach ($t in $titles) { if ($t.Trim() -ne "" -and $r.Content.Contains($t.Trim())) { $leaked = $true } }
    Check "locked class leaks NO material titles" (-not $leaked)

    # And the direct link must refuse too.
    $matId = Q "SELECT id FROM class_materials WHERE class_id=$lockedClass AND month_year=DATE_FORMAT(CURDATE(),'%Y-%m-01') LIMIT 1;"
    if ($matId -ne "") {
        $r2 = Invoke-WebRequest -Uri "$base/my/materials/$matId/open" -WebSession $ss -UseBasicParsing -MaximumRedirection 5
        Check "direct material link refused when unpaid" ($r2.Content -match 'is locked|Settle this month')
    }
} else { "  (no unpaid class this month for this student - skipped)" }

$r = Invoke-WebRequest -Uri "$base/my/progress" -WebSession $ss -UseBasicParsing
Check "progress renders" ($r.StatusCode -eq 200 -and $r.Content -notmatch 'Fatal error|Warning:')
Check "progress shows a level" ($r.Content -match 'Excellent|Strong|Steady|Developing|Needs attention|Not started')

$r = Invoke-WebRequest -Uri "$base/my/marks" -WebSession $ss -UseBasicParsing
Check "marks renders" ($r.StatusCode -eq 200 -and $r.Content -notmatch 'Fatal error|Warning:')

# --- another student's result must be refused ---
$otherAttempt = Q "SELECT id FROM quiz_attempts WHERE student_id <> $studentId LIMIT 1;"
if ($otherAttempt -ne "") {
    $r = Invoke-WebRequest -Uri "$base/my/quizzes/$otherAttempt/result" -WebSession $ss -UseBasicParsing
    Check "another student's result refused" ($r.Content -match 'not yours' -or (Get-Title $r.Content) -like "My marks*")
}

# --- a student must not reach the teacher's screens ---
$anyClass = Q "SELECT id FROM classes WHERE subject='Science' LIMIT 1;"
$r = Invoke-WebRequest -Uri "$base/classes/$anyClass/quizzes" -WebSession $ss -UseBasicParsing
Check "student blocked from teacher quiz screens" ((Get-Title $r.Content) -notlike "Quizzes*")

# ============ PARENT ============
""
"=== PARENT PORTAL ==="

$parentPhone = Q "SELECT phone1 FROM parents WHERE status='active' LIMIT 1;"
$parentChild = Q "SELECT s.full_name FROM parents p JOIN students s ON s.id=p.student_id WHERE p.phone1='$parentPhone' LIMIT 1;"
"Parent $parentPhone, child $parentChild"

$ps = Login $parentPhone 'Demo@12345'
$r = Invoke-WebRequest -Uri "$base/family" -WebSession $ps -UseBasicParsing
Check "parent overview renders" ($r.StatusCode -eq 200 -and $r.Content -notmatch 'Fatal error|Warning:')
Check "parent sees their own child's name" ($r.Content -match [regex]::Escape($parentChild))

$r = Invoke-WebRequest -Uri "$base/family/marks" -WebSession $ps -UseBasicParsing
Check "parent marks renders" ($r.StatusCode -eq 200 -and $r.Content -notmatch 'Fatal error|Warning:')

$r = Invoke-WebRequest -Uri "$base/family/fees" -WebSession $ps -UseBasicParsing
Check "parent fees renders" ($r.StatusCode -eq 200 -and $r.Content -notmatch 'Fatal error|Warning:')

# --- a parent must not reach the student portal or teacher screens ---
$r = Invoke-WebRequest -Uri "$base/my/classes" -WebSession $ps -UseBasicParsing
Check "parent blocked from student portal" ((Get-Title $r.Content) -notlike "My classes*")

$r = Invoke-WebRequest -Uri "$base/classes/$anyClass/materials" -WebSession $ps -UseBasicParsing
Check "parent blocked from teacher material screens" ((Get-Title $r.Content) -notlike "Class material*")

# --- the access log recorded the visits ---
$logged = Q "SELECT COUNT(*) FROM parent_access_log WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE);"
Check "parent views written to the access log" ([int]$logged -ge 3)

""
"done"
