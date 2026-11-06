# Seminars & Events -- isolated copy only.
$base = "http://localhost/lms_rc"
$mysql = "C:\xampp\mysql\bin\mysql.exe"
$db = "student_lms_rc"
$tmp = "C:\lms_rc_data\jars"
New-Item -ItemType Directory -Force -Path $tmp | Out-Null
$saPw = [IO.File]::ReadAllText("C:\lms_rc_data\test_sa_password.txt").Trim()

function Q($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function X($sql) { & $mysql -u root -e "USE $db; $sql" 2>&1 | Out-Null }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-72} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "sm_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "sm_body.bin"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from) {
  $t = Tok $w $from; $out = Join-Path $tmp "sm_body.bin"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { foreach ($v in @($fields[$k])) { $a += '--data-urlencode'; $a += "$k=$v" } }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id, $pw = 'Demo@12345') { Remove-Item (Jar $w) -ea 0; $null = Post $w '/login' @{ user_id = $id; password = $pw } '/login'; return ((Get $w '/dashboard').url -notlike '*/login') }

X "DELETE FROM seminar_registrations WHERE seminar_id IN (SELECT id FROM seminars WHERE title LIKE 'RCTEST-seminar%');"
X "DELETE FROM seminars WHERE title LIKE 'RCTEST-seminar%';"
X "DELETE FROM login_attempts;"

$eventDate = (Get-Date).AddDays(10).ToString('yyyy-MM-dd')

Check "super admin logs in" (Login 'sa' 'SATEST0001' $saPw)
Check "teacher logs in" (Login 't' 'TCH20260100')
Check "student (class 7, Grade 6) logs in" (Login 's1' 'STU20260200')
Check "student (class 8, Grade 7, same branch) logs in" (Login 's2' 'STU20260201')
Check "parent of student 11 logs in" (Login 'p' '0720000000')

"=== teacher cannot post a grade-wide seminar -- only her own classes ==="
$r = Post 't' '/seminars/create' @{
    title = 'RCTEST-seminar grade attempt'; description = 'x'; event_date = $eventDate
    target_scope = 'grade'; grade = 'Grade 6'; institute_id = '0'
    for_students = '1'; for_parents = '1'; action = 'publish'
} '/seminars/create'
Check "teacher's grade-wide seminar was rejected, not created" ((Q "SELECT COUNT(*) FROM seminars WHERE title='RCTEST-seminar grade attempt';") -eq '0')

"=== super admin publishes a seminar for all of Grade 6, every branch ==="
$r = Post 'sa' '/seminars/create' @{
    title = 'RCTEST-seminar grade 6'; description = 'For every Grade 6 student'; event_date = $eventDate
    target_scope = 'grade'; grade = 'Grade 6'; institute_id = '0'
    for_students = '1'; for_parents = '1'; registration_required = '1'; action = 'publish'
} '/seminars/create'
$gid = Q "SELECT id FROM seminars WHERE title='RCTEST-seminar grade 6';"
Check "grade-wide seminar created and published" ($gid -ne '' -and (Q "SELECT status FROM seminars WHERE id=$gid;") -eq 'published')

$r = Get 's1' '/my/seminars'
Check "Grade 6 student sees the grade-wide seminar" ($r.body -match 'RCTEST-seminar grade 6')
$r = Get 's2' '/my/seminars'
Check "Grade 7 student (same branch) does NOT see it" ($r.body -notmatch 'RCTEST-seminar grade 6')
$r = Get 'p' '/family/seminars'
Check "parent of the Grade 6 student sees it too" ($r.body -match 'RCTEST-seminar grade 6')

"=== registration (RSVP) ==="
Check "not registered yet" ((Q "SELECT COUNT(*) FROM seminar_registrations WHERE seminar_id=$gid AND user_type='student';") -eq '0')
$r = Post 's1' "/my/seminars/$gid/register" @{} '/my/seminars'
Check "student registered" ((Q "SELECT COUNT(*) FROM seminar_registrations WHERE seminar_id=$gid AND user_type='student' AND user_id=11;") -eq '1')
$r = Get 's1' '/my/seminars'
Check "student sees their own registration reflected" ($r.body -match 'Cancel my registration' -or $r.body -match 'ලියාපදිංචිය')
$r = Post 's1' "/my/seminars/$gid/unregister" @{} '/my/seminars'
Check "student unregistered" ((Q "SELECT COUNT(*) FROM seminar_registrations WHERE seminar_id=$gid AND user_type='student' AND user_id=11;") -eq '0')

"=== teacher posts a class-scoped seminar for her own class only ==="
$tt = Tok 't' '/seminars/create'
$r = Post 't' '/seminars/create' @{
    title = 'RCTEST-seminar class 7'; description = 'Class only'; event_date = $eventDate
    target_scope = 'class'; class_id = '7'
    for_students = '1'; for_parents = '0'; action = 'publish'
} '/seminars/create'
$cid = Q "SELECT id FROM seminars WHERE title='RCTEST-seminar class 7';"
Check "class-scoped seminar created" ($cid -ne '')
$r = Get 's1' '/my/seminars'
Check "class 7 student sees the class seminar" ($r.body -match 'RCTEST-seminar class 7')
$r = Get 's2' '/my/seminars'
Check "class 8 student does not see the class 7 seminar" ($r.body -notmatch 'RCTEST-seminar class 7')

"=== drafts can be deleted outright; published ones cannot ==="
$r = Post 'sa' '/seminars/create' @{
    title = 'RCTEST-seminar draft'; description = 'x'; event_date = $eventDate
    target_scope = 'all'; for_students = '1'; for_parents = '1'; action = 'draft'
} '/seminars/create'
$did = Q "SELECT id FROM seminars WHERE title='RCTEST-seminar draft';"
Check "draft created, not published" ($did -ne '' -and (Q "SELECT status FROM seminars WHERE id=$did;") -eq 'draft')
$r = Post 'sa' "/seminars/$did/delete" @{} '/seminars'
Check "draft deleted outright" ((Q "SELECT COUNT(*) FROM seminars WHERE id=$did;") -eq '0')

$r = Post 'sa' "/seminars/$cid/delete" @{} '/seminars'
Check "a published seminar refuses delete" ((Q "SELECT COUNT(*) FROM seminars WHERE id=$cid;") -eq '1')
$r = Post 'sa' "/seminars/$cid/cancel" @{} '/seminars'
Check "cancel works instead, for a published one" ((Q "SELECT status FROM seminars WHERE id=$cid;") -eq 'cancelled')

X "DELETE FROM seminar_registrations WHERE seminar_id IN (SELECT id FROM seminars WHERE title LIKE 'RCTEST-seminar%');"
X "DELETE FROM seminars WHERE title LIKE 'RCTEST-seminar%';"
X "DELETE FROM login_attempts;"
Check "test rows removed" ((Q "SELECT COUNT(*) FROM seminars WHERE title LIKE 'RCTEST-seminar%';") -eq '0')

"$($script:n - $script:fails) of $($script:n) passed"
