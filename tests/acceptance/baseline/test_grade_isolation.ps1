$base = "http://localhost/student-management-lms"
$mysql = "C:\xampp\mysql\bin\mysql.exe"

function Get-Csrf($html) { [regex]::Match($html, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Q($sql) { $out = & $mysql -u root -N -e "USE student_lms; $sql"; if ($null -eq $out) { return "" } return ([string]$out).Trim() }
function Rows($sql) { $out = & $mysql -u root -N -e "USE student_lms; $sql"; if ($null -eq $out) { return @() } return @($out | ForEach-Object { ([string]$_).Trim() } | Where-Object { $_ -ne "" }) }
function Check($label, $ok) { "{0,-56} {1}" -f $label, $(if ($ok) { "OK" } else { "FAIL" }) }

# A student enrolled in exactly one grade.
$studentId = Q @"
SELECT e.student_id FROM enrollments e
 JOIN classes c ON c.id = e.class_id
WHERE e.enrollment_status='active' AND c.subject='Science'
GROUP BY e.student_id HAVING COUNT(DISTINCT c.grade) = 1
LIMIT 1;
"@

$display = Q "SELECT student_id_display FROM students WHERE id=$studentId;"
$myGrade = Q "SELECT DISTINCT c.grade FROM enrollments e JOIN classes c ON c.id=e.class_id WHERE e.student_id=$studentId AND e.enrollment_status='active' LIMIT 1;"
$myClasses = Rows "SELECT c.class_name FROM enrollments e JOIN classes c ON c.id=e.class_id WHERE e.student_id=$studentId AND e.enrollment_status='active';"

# Everything they are NOT in.
$otherClasses = Rows "SELECT class_name FROM classes WHERE subject='Science' AND id NOT IN (SELECT class_id FROM enrollments WHERE student_id=$studentId);"
$otherClassIds = Rows "SELECT id FROM classes WHERE subject='Science' AND id NOT IN (SELECT class_id FROM enrollments WHERE student_id=$studentId);"

"Student $display is in $myGrade"
"  their classes:     $($myClasses -join ', ')"
"  not enrolled in:   $($otherClasses.Count) other science classes"
""

$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest -Uri "$base/login" -WebSession $s -UseBasicParsing
$null = Invoke-WebRequest -Uri "$base/login" -WebSession $s -Method POST -UseBasicParsing `
    -Body @{ user_id = $display; password = 'Demo@12345'; '_csrf_token' = (Get-Csrf $r.Content) }

$page = (Invoke-WebRequest -Uri "$base/my/classes" -WebSession $s -UseBasicParsing).Content

# Their own classes must be there.
$allMine = $true
foreach ($c in $myClasses) { if (-not $page.Contains($c)) { $allMine = $false } }
Check "their own class(es) are listed" $allMine

# No other class may appear anywhere on the page.
$leaked = @()
foreach ($c in $otherClasses) { if ($page.Contains($c)) { $leaked += $c } }
Check "no other grade's class appears" ($leaked.Count -eq 0)
if ($leaked.Count -gt 0) { "    leaked: $($leaked -join ', ')" }

# Nor may another grade be reachable by typing its id.
$blocked = $true
$checked = 0
foreach ($id in $otherClassIds) {
    if ($checked -ge 5) { break }
    $detail = (Invoke-WebRequest -Uri "$base/my/classes/$id" -WebSession $s -UseBasicParsing).Content
    if ($detail -notmatch 'not enrolled in that class') { $blocked = $false }
    $checked++
}
Check "another grade's class blocked by direct id ($checked tried)" $blocked

# And its quizzes must refuse too.
$otherQuiz = Q "SELECT q.id FROM quizzes q WHERE q.class_id NOT IN (SELECT class_id FROM enrollments WHERE student_id=$studentId) AND q.quiz_status='published' LIMIT 1;"
if ($otherQuiz -ne "") {
    $sit = (Invoke-WebRequest -Uri "$base/my/quizzes/$otherQuiz/sit" -WebSession $s -UseBasicParsing).Content
    Check "another grade's quiz cannot be sat" ($sit -notmatch 'data-question')
}

# Only one teacher should be teaching science.
$teacherCount = Q "SELECT COUNT(DISTINCT teacher_id) FROM classes WHERE subject='Science' AND teacher_id IS NOT NULL;"
$teacherName = Q "SELECT DISTINCT u.full_name FROM classes c JOIN users u ON u.id=c.teacher_id WHERE c.subject='Science' LIMIT 1;"
""
"Science classes are taught by $teacherCount teacher(s): $teacherName"
