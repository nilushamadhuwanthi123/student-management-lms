# Class Recordings module -- isolated copy only.
$base = "http://localhost/lms_rc"; $mysql = "C:\xampp\mysql\bin\mysql.exe"; $db = "student_lms_rc"
$tmp = "C:\lms_rc_data\jars"; New-Item -ItemType Directory -Force -Path $tmp | Out-Null
function Q($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function X($sql) { & $mysql -u root -e "USE $db; $sql" 2>&1 | Out-Null }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-72} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "rec_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "rec_body.bin"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function GetNoFollow($w, $p) { $out = Join-Path $tmp "rec_body2.bin"; $raw = curl.exe -s -D - -o $out -b (Jar $w) -c (Jar $w) "$base$p"; $c = ($raw -join "`n"); $loc = [regex]::Match($c, '(?im)^Location:\s*(\S+)').Groups[1].Value.Trim(); $code = [regex]::Match($c, '^HTTP/\S+\s+(\d+)').Groups[1].Value; return @{ code = [int]$code; location = $loc } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from) {
  $t = Tok $w $from; $out = Join-Path $tmp "rec_body.bin"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { $a += '--data-urlencode'; $a += "$k=$($fields[$k])" }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id) { Remove-Item (Jar $w) -ea 0; $null = Post $w '/login' @{ user_id = $id; password = 'Demo@12345' } '/login'; return ((Get $w '/dashboard').url -notlike '*/login') }
function Clean($r) { $r.body -notmatch 'Fatal error|Parse error|Warning:</b>|Undefined variable|Undefined array key|SQLSTATE' }
X "DELETE FROM login_attempts;"
X "DELETE FROM recordings WHERE title='RCTEST-recording';"

$C = [int](Q "SELECT class_id FROM enrollments WHERE student_id=11 AND enrollment_status='active' LIMIT 1;")
Check "fixture: an active class for student 11" ($C -gt 0)
# Use a month we know is already settled in the fixture data, so the
# unlock path can actually be exercised -- "this month" ages past the
# fixture's payment rows as real calendar time passes.
$paidMonth = Q "SELECT DATE_FORMAT(month_year, '%Y-%m') FROM payments WHERE student_id=11 AND class_id=$C AND payment_status='paid' ORDER BY month_year DESC LIMIT 1;"
Check "fixture: a settled month exists for student 11" ($paidMonth -ne '')
$parentPhone = Q "SELECT phone1 FROM parents WHERE student_id=11 LIMIT 1;"
Check "fixture: a parent phone exists for student 11" ($parentPhone -ne '')

"=== teacher: Class Recordings page and add form ==="
Check "teacher (TCH20260100) logs in" (Login 't' 'TCH20260100')
$r = Get 't' "/classes/$C/recordings"
Check "recordings index renders" ($r.code -eq 200 -and (Clean $r))
Check "shows the Class Recordings heading" ($r.body -match 'Class Recordings')
$r = Get 't' "/classes/$C/recordings/create"
Check "add-recording form renders" ($r.code -eq 200 -and (Clean $r))

$r = Post 't' "/classes/$C/recordings/create" @{ title = 'RCTEST-recording'; video_url = 'https://youtube.com/watch?v=rctest'; status = 'published'; month_year = $paidMonth } "/classes/$C/recordings/create"
Check "store posts without a fatal error" (Clean $r)
$rid = Q "SELECT id FROM recordings WHERE title='RCTEST-recording' ORDER BY id DESC LIMIT 1;"
Check "the recording was created" ($rid -ne '')
Check "it is published" ((Q "SELECT status FROM recordings WHERE id=$rid;") -eq 'published')

$r = Get 't' "/classes/$C/recordings"
Check "the new recording is listed" ($r.body -match 'RCTEST-recording')

"=== student: sees it on My Recordings and can open it ==="
Check "student (STU20260200) logs in" (Login 's' 'STU20260200')
$r = Get 's' '/my/recordings'
Check "my/recordings renders" ($r.code -eq 200 -and (Clean $r))
Check "the recording is listed" ($r.body -match 'RCTEST-recording')
$open = GetNoFollow 's' "/my/recordings/$rid/open"
Check "opening it redirects straight to the video link" ($open.code -eq 302 -and $open.location -eq 'https://youtube.com/watch?v=rctest')

"=== parent: read-only family view shows it too ==="
X "DELETE FROM login_attempts;"
Check "parent ($parentPhone) logs in" (Login 'p' $parentPhone)
$r = Get 'p' '/family/recordings'
Check "family/recordings renders" ($r.code -eq 200 -and (Clean $r))
Check "the recording is listed for the parent" ($r.body -match 'RCTEST-recording')

"=== teacher: taking it down removes it from the student list ==="
$r = Post 't' "/classes/$C/recordings/$rid/archive" @{} "/classes/$C/recordings"
Check "archive posts without a fatal error" (Clean $r)
Check "status is archived" ((Q "SELECT status FROM recordings WHERE id=$rid;") -eq 'archived')
$r = Get 's' '/my/recordings'
Check "the archived recording is gone from My Recordings" ($r.body -notmatch 'RCTEST-recording')

X "DELETE FROM recordings WHERE title='RCTEST-recording';"
X "DELETE FROM login_attempts;"
"`n$($script:n - $script:fails) of $($script:n) passed"
