$base = "http://localhost/lms_rc"; $tmp = "C:\lms_rc_data\jars"
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-60} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Get($jar, $p) { $out = "$tmp\si_body.html"; $c = curl.exe -s -o $out -w "%{http_code}" -L -b $jar -c $jar "$base$p"; return @{ code = [int]$c; body = [IO.File]::ReadAllText($out, [Text.Encoding]::UTF8) } }
function Login($jar, $id) { Remove-Item $jar -ea 0; $t = [regex]::Match((Get $jar '/login').body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value; $null = curl.exe -s -o NUL -b $jar -c $jar --data-urlencode "_csrf_token=$t" --data-urlencode "user_id=$id" --data-urlencode "password=Demo@12345" "$base/login"; $null = Get $jar '/lang/si' }
function Clean($b) { $b -notmatch 'Fatal error|Parse error|Warning:</b>|Undefined|SQLSTATE' }
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE student_lms_rc; DELETE FROM login_attempts;" 2>&1 | Out-Null
$t = "$tmp\si_t.jar"; Login $t 'TCH20260100'
foreach ($pg in @(@('/topics?grade=6', 'ඒකක'), @('/past-papers', 'පසුගිය ප්‍රශ්න පත්‍ර'), @('/past-papers/create?grade=6', 'ප්‍රශ්න පත්‍රයක් එක් කරන්න'), @('/attendance/register?class_id=7', 'පන්ති ලේඛනය'), @('/classes/7/learning-gaps', 'ඉගෙනීමේ හිඩැස්'), @('/teaching', 'ඉගෙනීමේ හිඩැස්'), @('/classes/7/quizzes/create', 'ඒකකය'), @('/classes/7/materials/create', 'ඒකකය'), @('/classes/7/assignments/create', 'ඒකකය'))) {
  $r = Get $t $pg[0]; Check "teacher, Sinhala: $($pg[0])" ($r.code -eq 200 -and (Clean $r.body) -and $r.body.Contains($pg[1]))
}
$s = "$tmp\si_s.jar"; Login $s 'STU20260200'
$r = Get $s '/my/papers'; Check "student, Sinhala: /my/papers" ($r.code -eq 200 -and (Clean $r.body) -and $r.body.Contains('පසුගිය ප්‍රශ්න පත්‍ර'))
$r = Get $s '/my/payments'; Check "student, Sinhala: slip page uses the new slip term" ((Clean $r.body) -and -not $r.body.Contains('රිසිට්පත'))
$a = "$tmp\si_a.jar"; Login $a 'ADM20260101'
$r = Get $a '/dashboard'; Check "admin, Sinhala: dashboard work queue" ($r.code -eq 200 -and (Clean $r.body) -and $r.body.Contains('අද කළ යුතු වැඩ'))
$r = Get $a '/payments/slips'; Check "admin, Sinhala: slip queue page" ($r.code -eq 200 -and (Clean $r.body))
""; "$($script:n - $script:fails) of $($script:n) passed"
