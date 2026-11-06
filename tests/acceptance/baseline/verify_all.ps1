$base = "http://localhost/student-management-lms"
$mysql = "C:\xampp\mysql\bin\mysql.exe"

function Get-Csrf($h) { [regex]::Match($h, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Q($sql) { $o = & $mysql -u root -N -e "USE student_lms; $sql"; if ($null -eq $o) { return "" } return ([string]$o).Trim() }
function Check($l, $ok) { "{0,-46} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }

function Login($id) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest -Uri "$base/login" -WebSession $s -UseBasicParsing
    $null = Invoke-WebRequest -Uri "$base/login" -WebSession $s -Method POST -UseBasicParsing `
        -Body @{ user_id = $id; password = 'Demo@12345'; '_csrf_token' = (Get-Csrf $r.Content) }
    return $s
}

function Page($s, $path) {
    try { return (Invoke-WebRequest -Uri "$base$path" -WebSession $s -UseBasicParsing) }
    catch { return $null }
}

function Good($r) {
    if ($null -eq $r) { return $false }
    if ($r.StatusCode -ne 200) { return $false }
    return $r.Content -notmatch 'Fatal error|Parse error|Warning:</b>|Undefined variable|Undefined array key'
}

$fails = 0

"=== TEACHER (Harshani) ==="
$t = Login 'TCH20260100'
$classId = Q "SELECT id FROM classes WHERE class_name LIKE 'Grade 9 Science - Pannipitiya%' LIMIT 1;"
$quizId  = Q "SELECT id FROM quizzes WHERE class_id=$classId LIMIT 1;"

$pages = @(
    @{ p = "/dashboard";                              n = "dashboard" },
    @{ p = "/teaching";                               n = "teaching hub" },
    @{ p = "/classes/$classId/materials";             n = "class material" },
    @{ p = "/classes/$classId/materials/create";      n = "post material form" },
    @{ p = "/classes/$classId/quizzes";               n = "quiz list" },
    @{ p = "/classes/$classId/quizzes/create";        n = "set-a-quiz form" },
    @{ p = "/classes/$classId/quizzes/$quizId";       n = "question builder" },
    @{ p = "/classes/$classId/quizzes/$quizId/results"; n = "quiz results" }
)
foreach ($x in $pages) { $r = Page $t $x.p; $ok = Good $r; Check $x.n $ok; if (-not $ok) { $fails++ } }

$r = Page $t "/teaching"
Check "teaching hub groups by branch" ($r.Content -match 'Pannipitiya|Galle|Kandy')
Check "teaching hub surfaces marking backlog" ($r.Content -match 'Papers to mark')
Check "sidebar has the Teaching link" ($r.Content -match '/teaching"')
Check "sidebar has a collapse button" ($r.Content -match 'sidebarCollapse')

""
"=== STUDENT ==="
$sd = Q "SELECT student_id_display FROM students WHERE student_id_display LIKE 'STU2026%' ORDER BY id LIMIT 1;"
$s = Login $sd
$sid = Q "SELECT id FROM students WHERE student_id_display='$sd';"
$myClass = Q "SELECT class_id FROM enrollments WHERE student_id=$sid AND enrollment_status='active' LIMIT 1;"

$pages = @(
    @{ p = "/my/classes";            n = "my classes" },
    @{ p = "/my/classes/$myClass";   n = "class detail" },
    @{ p = "/my/marks";              n = "my marks" },
    @{ p = "/my/progress";           n = "my progress" }
)
foreach ($x in $pages) { $r = Page $s $x.p; $ok = Good $r; Check $x.n $ok; if (-not $ok) { $fails++ } }

$r = Page $s "/my/classes"
Check "student sidebar shows My Classes" ($r.Content -match 'My Classes' -or $r.Content -match 'මගේ පන්ති')
Check "student sidebar hides Teaching" ($r.Content -notmatch '>\s*Teaching\s*<')

""
"=== PARENT ==="
$pp = Q "SELECT phone1 FROM parents WHERE status='active' LIMIT 1;"
$p = Login $pp
foreach ($x in @(
    @{ p = "/family";       n = "parent overview" },
    @{ p = "/family/marks"; n = "parent marks" },
    @{ p = "/family/fees";  n = "parent fees" }
)) { $r = Page $p $x.p; $ok = Good $r; Check $x.n $ok; if (-not $ok) { $fails++ } }

$r = Page $p "/family"
Check "parent sidebar shows My Child" ($r.Content -match 'My Child' -or $r.Content -match 'මගේ දරුවා')

""
"=== SUPER ADMIN ==="
$a = Login 'SA20260001'
foreach ($x in @(
    @{ p = "/dashboard";  n = "dashboard" },
    @{ p = "/teaching";   n = "teaching (all branches)" },
    @{ p = "/users";      n = "users" },
    @{ p = "/institutes"; n = "institutes" },
    @{ p = "/reports";    n = "reports" },
    @{ p = "/payments";   n = "payments" }
)) { $r = Page $a $x.p; $ok = Good $r; Check $x.n $ok; if (-not $ok) { $fails++ } }

""
"=== PUBLIC ==="
foreach ($x in @(
    @{ p = "/";         n = "home" },
    @{ p = "/about";    n = "about" },
    @{ p = "/features"; n = "features" },
    @{ p = "/contact";  n = "contact" },
    @{ p = "/login";    n = "login" }
)) {
    $anon = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Page $anon $x.p; $ok = Good $r; Check $x.n $ok; if (-not $ok) { $fails++ }
}

""
if ($fails -eq 0) { "ALL PAGES OK" } else { "$fails PAGE(S) FAILED" }
