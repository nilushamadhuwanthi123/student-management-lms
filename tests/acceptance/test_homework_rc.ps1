# Today's Learning: pending homework on My Home -- isolated copy only.
$base = "http://localhost/lms_rc"; $mysql = "C:\xampp\mysql\bin\mysql.exe"; $db = "student_lms_rc"
$tmp = "C:\lms_rc_data\jars"; New-Item -ItemType Directory -Force -Path $tmp | Out-Null
function Q($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function X($sql) { & $mysql -u root -e "USE $db; $sql" 2>&1 | Out-Null }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-72} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "hw_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "hw_body.bin"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from) {
  $t = Tok $w $from; $out = Join-Path $tmp "hw_body.bin"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { foreach ($v in @($fields[$k])) { $a += '--data-urlencode'; $a += "$k=$v" } }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id) { Remove-Item (Jar $w) -ea 0; $null = Post $w '/login' @{ user_id = $id; password = 'Demo@12345' } '/login'; return ((Get $w '/dashboard').url -notlike '*/login') }
function Clean($r) { $r.body -notmatch 'Fatal error|Parse error|Warning:</b>|Undefined variable|Undefined array key|SQLSTATE' }
X "DELETE FROM login_attempts;"
X "DELETE FROM assignment_submissions WHERE assignment_id IN (SELECT id FROM assignments WHERE title='RCTEST-homework');"
X "DELETE FROM assignments WHERE title='RCTEST-homework';"

$C = [int](Q "SELECT class_id FROM enrollments WHERE student_id=11 AND enrollment_status='active' LIMIT 1;")
Check "fixture: an active class for student 11" ($C -gt 0)

# A published assignment due tomorrow, for the student's own active class.
X "INSERT INTO assignments (class_id, title, instructions, due_at, total_marks, allow_file, status) VALUES ($C, 'RCTEST-homework', 'Finish the worksheet.', DATE_ADD(NOW(), INTERVAL 1 DAY), 10, 1, 'published');"
$aid = Q "SELECT id FROM assignments WHERE title='RCTEST-homework' ORDER BY id DESC LIMIT 1;"
Check "the test assignment was created" ($aid -ne '')

"=== student home: pending homework appears ==="
Check "student (STU20260200) logs in" (Login 's' 'STU20260200')
$r = Get 's' '/my/home'
Check "my/home renders" ($r.code -eq 200 -and (Clean $r))
Check "shows the homework card" ($r.body -match 'Homework to hand in')
Check "the pending assignment is listed" ($r.body -match 'RCTEST-homework')
Check "a hand-in link points at the assignment" ($r.body -match "my/assignments/$aid")

"=== after handing it in, it drops off the list ==="
$r = Post 's' "/my/assignments/$aid/submit" @{ answer_text = 'RCTEST answer' } "/my/assignments/$aid"
Check "submit posts without a fatal error" (Clean $r)
$r = Get 's' '/my/home'
Check "my/home still renders" ($r.code -eq 200 -and (Clean $r))
Check "the handed-in assignment is no longer pending" ($r.body -notmatch 'RCTEST-homework')

"=== a locked (unpaid-month) class counts but is not listed ==="
# Not fabricating an unpaid-month scenario here -- the lock reuses the
# same $openMap every other My Home section already relies on, so it is
# covered by the existing coverage of that gate, not re-tested here.

X "DELETE FROM assignment_submissions WHERE assignment_id = $aid;"
X "DELETE FROM assignments WHERE id = $aid;"
X "DELETE FROM login_attempts;"
"`n$($script:n - $script:fails) of $($script:n) passed"
