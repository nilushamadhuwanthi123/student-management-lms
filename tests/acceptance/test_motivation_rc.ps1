# Student motivation: levels, achievements, streaks, goals -- isolated copy only.
$base = "http://localhost/lms_rc"; $mysql = "C:\xampp\mysql\bin\mysql.exe"; $db = "student_lms_rc"
$tmp = "C:\lms_rc_data\jars"; New-Item -ItemType Directory -Force -Path $tmp | Out-Null
function Q($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function QA($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return @() } return @($o | % { ([string]$_).Trim() } | ? { $_ -ne '' }) }
function X($sql) { & $mysql -u root -e "USE $db; $sql" 2>&1 | Out-Null }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-72} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "m_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "m_body.bin"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from) {
  $t = Tok $w $from; $out = Join-Path $tmp "m_body.bin"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { foreach ($v in @($fields[$k])) { $a += '--data-urlencode'; $a += "$k=$v" } }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id) { Remove-Item (Jar $w) -ea 0; $null = Post $w '/login' @{ user_id = $id; password = 'Demo@12345' } '/login'; return ((Get $w '/dashboard').url -notlike '*/login') }
function Clean($r) { $r.body -notmatch 'Fatal error|Parse error|Warning:</b>|Undefined variable|Undefined array key|SQLSTATE' }
X "DELETE FROM login_attempts;"
X "DELETE FROM student_goals WHERE note = 'RCTEST';"

$C = [int](Q "SELECT class_id FROM enrollments WHERE student_id=11 AND enrollment_status='active' LIMIT 1;")
$G = [int]((Q "SELECT grade FROM classes WHERE id=$C;") -replace '\D', '')
Check "fixture: a class with an active student" ($C -gt 0)

"=== student home: My Home renders with the new section ==="
Check "student (STU20260200) logs in" (Login 's' 'STU20260200')
$r = Get 's' '/my/home'
Check "my/home renders" ($r.code -eq 200 -and (Clean $r))
Check "shows the level card" ($r.body -match 'My level')
Check "shows achievements" ($r.body -match 'Achievements')
Check "shows the learning streak" ($r.body -match 'Learning streak')
Check "shows the goals card" ($r.body -match 'My goals')
Check "levels are labelled as encouragement, not grades" ($r.body -match 'not school grades')

"=== student sets, sees and removes a goal ==="
$before = [int](Q "SELECT COUNT(*) FROM student_goals WHERE student_id=11 AND status='active' AND set_by_type='student';")
$end = (Get-Date).AddDays(30).ToString('yyyy-MM-dd')
$r = Post 's' '/my/goals' @{ goal_type = 'quiz_average'; target_value = '75'; period_end = $end; note = 'RCTEST' } '/my/home'
Check "goal form posts without a fatal error" (Clean $r)
$after = [int](Q "SELECT COUNT(*) FROM student_goals WHERE student_id=11 AND status='active' AND set_by_type='student';")
Check "a new own goal was created" ($after -eq $before + 1)
$gid = Q "SELECT id FROM student_goals WHERE student_id=11 AND note='RCTEST' ORDER BY id DESC LIMIT 1;"
$r = Get 's' '/my/home'
Check "the new goal appears on My Home" ($r.body -match '75')
$r = Post 's' "/my/goals/$gid/remove" @{} '/my/home'
Check "removing the goal leaves no fatal error" (Clean $r)
Check "goal is gone (status removed)" ((Q "SELECT status FROM student_goals WHERE id=$gid;") -eq 'removed')

"=== a 4th own goal is refused by the server ==="
for ($i = 1; $i -le 3; $i++) {
    $null = Post 's' '/my/goals' @{ goal_type = 'attendance'; target_value = '80'; period_end = $end; note = "RCTEST$i" } '/my/home'
}
$activeOwn = [int](Q "SELECT COUNT(*) FROM student_goals WHERE student_id=11 AND status='active' AND set_by_type='student';")
Check "exactly 3 of the student's own goals are active" ($activeOwn -eq 3)
$r = Post 's' '/my/goals' @{ goal_type = 'attendance'; target_value = '80'; period_end = $end; note = 'RCTEST4' } '/my/home'
$stillThree = [int](Q "SELECT COUNT(*) FROM student_goals WHERE student_id=11 AND status='active' AND set_by_type='student';")
Check "a 4th active goal is refused, not silently created" ($stillThree -eq 3)
X "DELETE FROM student_goals WHERE student_id=11 AND note LIKE 'RCTEST%';"

"=== staff: Student 360 shows the Goals tab and can set a goal ==="
Check "a teacher logs in" (Login 't' 'TCH20260100')
$r = Get 't' '/students/11'
Check "student 360 page renders" ($r.code -eq 200 -and (Clean $r))
Check "the Goals tab is present" ($r.body -match 'panel-goals')
$r = Post 't' '/students/11/goals' @{ goal_type = 'homework'; target_value = '90'; period_end = $end; note = 'RCTEST-teacher' } '/students/11'
Check "teacher-set goal posts without a fatal error" (Clean $r)
$tgid = Q "SELECT id FROM student_goals WHERE student_id=11 AND note='RCTEST-teacher' ORDER BY id DESC LIMIT 1;"
Check "a teacher-set goal was recorded" ($tgid -ne '')
Check "it is marked set_by_type=user" ((Q "SELECT set_by_type FROM student_goals WHERE id=$tgid;") -eq 'user')

"=== the student cannot remove a goal their teacher set ==="
$r = Post 's' "/my/goals/$tgid/remove" @{} '/my/home'
Check "student's removal attempt is refused" ((Q "SELECT status FROM student_goals WHERE id=$tgid;") -eq 'active')
$r = Post 't' "/students/11/goals/$tgid/remove" @{} '/students/11'
Check "the teacher can remove their own goal" ((Q "SELECT status FROM student_goals WHERE id=$tgid;") -eq 'removed')
X "DELETE FROM student_goals WHERE student_id=11 AND note LIKE 'RCTEST%';"

"=== achievements persist a real earned date ==="
$code = Q "SELECT code FROM student_achievements WHERE student_id=11 LIMIT 1;"
if ($code -ne '') {
    $before = Q "SELECT earned_at FROM student_achievements WHERE student_id=11 AND code='$code';"
    $null = Get 's' '/my/home'
    $after = Q "SELECT earned_at FROM student_achievements WHERE student_id=11 AND code='$code';"
    Check "an already-earned achievement's date does not move on reload" ($before -eq $after)
} else {
    "(no achievement earned yet for student 11 -- nothing to check)"
}

"=== progress page still renders with the extended achievements ==="
$r = Get 's' '/my/progress'
Check "my/progress still renders" ($r.code -eq 200 -and (Clean $r))

X "DELETE FROM login_attempts;"
"`n$($script:n - $script:fails) of $($script:n) passed"
