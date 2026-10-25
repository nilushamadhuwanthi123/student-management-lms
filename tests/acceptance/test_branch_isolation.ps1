# Branch isolation checks. Read-only except two password "resets" that set
# the account's password to the value it already has (Demo@12345), so even
# a failure changes nothing.
$base  = "http://localhost/student-management-lms"
$mysql = "C:\xampp\mysql\bin\mysql.exe"
function Q($sql) { $o = & $mysql -u root -N -e "USE student_lms; $sql"; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function Csrf($h) { [regex]::Match($h, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
$script:fails = 0; $script:n = 0
function Check($label, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-62} {1}" -f $label, $(if ($ok) { "OK" } else { "FAIL" }) }
function Login($id) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest -Uri "$base/login" -WebSession $s -UseBasicParsing
    $null = Invoke-WebRequest -Uri "$base/login" -WebSession $s -Method POST -UseBasicParsing -Body @{ user_id = $id; password = 'Demo@12345'; '_csrf_token' = (Csrf $r.Content) }
    return $s
}
function Get($s, $p) {
    try { $r = Invoke-WebRequest -Uri "$base$p" -WebSession $s -UseBasicParsing -MaximumRedirection 5; return @{ ok = $true; url = $r.BaseResponse.ResponseUri.AbsolutePath; body = $r.Content; code = [int]$r.StatusCode } }
    catch { $c = 0; if ($_.Exception.Response) { $c = [int]$_.Exception.Response.StatusCode }; return @{ ok = $false; url = ''; body = ''; code = $c } }
}
function Clean($r) { $r.body -notmatch 'Fatal error|Parse error|Warning:</b>|Undefined variable|Undefined array key|SQLSTATE' }
function LoggedIn($s) { $r = Get $s '/dashboard'; return ($r.url -notlike '*/login') }

# Fixtures from the data, so the test cannot pass on an empty database.
$pStudent = Q "SELECT s.id FROM students s JOIN enrollments e ON e.student_id=s.id JOIN classes c ON c.id=e.class_id WHERE c.institute_id=3 AND NOT EXISTS (SELECT 1 FROM enrollments e2 JOIN classes c2 ON c2.id=e2.class_id WHERE e2.student_id=s.id AND c2.institute_id<>3) LIMIT 1;"
$gStudent = Q "SELECT s.id FROM students s JOIN enrollments e ON e.student_id=s.id JOIN classes c ON c.id=e.class_id WHERE c.institute_id=4 AND NOT EXISTS (SELECT 1 FROM enrollments e2 JOIN classes c2 ON c2.id=e2.class_id WHERE e2.student_id=s.id AND c2.institute_id<>4) LIMIT 1;"
$pName = Q "SELECT full_name FROM students WHERE id=$pStudent;"
$gName = Q "SELECT full_name FROM students WHERE id=$gStudent;"
$gClass = Q "SELECT id FROM classes WHERE institute_id=4 LIMIT 1;"
$pClass = Q "SELECT id FROM classes WHERE institute_id=3 LIMIT 1;"
$gPay = Q "SELECT id FROM payments WHERE class_id IN (SELECT id FROM classes WHERE institute_id=4) LIMIT 1;"
"fixtures: Pannipitiya student $pStudent ($pName), Galle student $gStudent ($gName), Galle class $gClass, Galle payment $gPay"
Check "fixtures exist" ($pStudent -ne '' -and $gStudent -ne '' -and $gClass -ne '' -and $gPay -ne '')

"=== PANNIPITIYA ADMIN (ADM20260101) ==="
$a = Login 'ADM20260101'
Check "logged in" (LoggedIn $a)
$r = Get $a '/students?search='; Check "student list shows own branch student" ($r.body -match [regex]::Escape($pName))
Check "student list hides Galle student" ($r.body -notmatch [regex]::Escape($gName))
$r = Get $a "/students/$pStudent"; Check "own branch student opens" ($r.url -like "*/students/$pStudent" -and (Clean $r))
$r = Get $a "/students/$gStudent"; Check "Galle student refused by direct id" ($r.url -notlike "*/students/$gStudent" -and $r.body -notmatch 'Student 360')
$r = Get $a "/students/$gStudent/edit"; Check "Galle student edit refused" ($r.url -notlike "*/edit")
$r = Get $a "/payments/student/$gStudent"; Check "Galle student's payments refused" ($r.body -notmatch [regex]::Escape($gName))
$r = Get $a '/classes'; Check "class list hides Galle classes" ($r.body -notmatch 'Science - Galle' -and $r.body -match 'Science - Pannipitiya')
$r = Get $a "/classes/$gClass/edit"; Check "Galle class edit refused" ($r.url -notlike '*/edit')
$r = Get $a "/classes/$gClass/materials"; Check "Galle class materials refused" ($r.url -notlike '*/materials')
$r = Get $a '/payments'; Check "payments page clean" (Clean $r); Check "payments hide Galle classes" ($r.body -notmatch 'Science - Galle')
$r = Get $a '/reports/payments'; Check "payment report clean" (Clean $r); Check "payment report hides Galle" ($r.body -notmatch 'Galle Science Centre')
$r = Get $a "/reports/payments?month=2026-10'%20OR%201=1--"; Check "payment report rejects injected month" ((Clean $r) -and $r.code -eq 200)
$r = Get $a '/reports/students'; Check "student report hides Galle student" ((Clean $r) -and $r.body -notmatch [regex]::Escape($gName))
$r = Get $a '/reports/attendance'; Check "attendance report clean" (Clean $r)
$r = Get $a '/attendance'; Check "attendance page clean" (Clean $r)
$r = Get $a '/insights'; Check "insights clean" (Clean $r); Check "insights hide Galle branch" ($r.body -notmatch 'Galle Science Centre')
$r = Get $a '/institutes'; Check "branches list shows only own" ($r.body -match 'Pannipitiya Science Centre' -and $r.body -notmatch 'Kandy Science Centre')
$r = Get $a '/institutes/create'; Check "creating a branch refused" ($r.url -notlike '*/institutes/create')
$r = Get $a '/calendar'; Check "calendar clean" (Clean $r); Check "calendar hides Galle classes" ($r.body -notmatch 'Science - Galle')
$r = Get $a '/api/classes-by-institute/4'; Check "API refuses Galle class list" ($r.body.Trim() -eq '[]' -or $r.body -notmatch 'Galle')
$r = Get $a '/users'; Check "users list hides Galle coordinator" ((Clean $r) -and $r.body -notmatch 'CRD20260102')
$r = Get $a '/users/15/edit'; Check "Galle coordinator edit refused" ($r.url -notlike '*/edit')
$r = Get $a '/users/1/edit'; Check "super admin edit refused" ($r.url -notlike '*/edit')
$r = Get $a '/audit'; Check "full audit log refused" ($r.url -notlike '*/audit')
$r = Get $a '/whatsapp/reminders'; Check "whatsapp page clean" (Clean $r); Check "whatsapp hides Galle" ($r.body -notmatch 'Science - Galle')
$r = Get $a '/dashboard'; Check "dashboard clean" (Clean $r)
$r = Get $a '/teaching'; Check "teaching hub hides Galle" ((Clean $r) -and $r.body -notmatch 'Science - Galle')

# Writes that must be refused. The password value is the account's
# existing one, so a failure of the check still changes nothing.
$page = Get $a '/users'; $tok = Csrf $page.body
$before = Q "SELECT password_hash FROM users WHERE id=15;"
try { $null = Invoke-WebRequest -Uri "$base/users/15/reset-password" -WebSession $a -Method POST -UseBasicParsing -Body @{ '_csrf_token' = $tok; new_password = 'Demo@12345' } } catch {}
$after = Q "SELECT password_hash FROM users WHERE id=15;"
Check "reset of Galle coordinator's password refused" ($before -eq $after)
$paidBefore = Q "SELECT CONCAT(payment_status,'|',IFNULL(paid_at,'')) FROM payments WHERE id=$gPay;"
try { $null = Invoke-WebRequest -Uri "$base/payments/mark-paid" -WebSession $a -Method POST -UseBasicParsing -Body @{ '_csrf_token' = $tok; payment_id = $gPay } } catch {}
$paidAfter = Q "SELECT CONCAT(payment_status,'|',IFNULL(paid_at,'')) FROM payments WHERE id=$gPay;"
Check "marking a Galle payment refused" ($paidBefore -eq $paidAfter)

"=== GALLE COORDINATOR (CRD20260102) ==="
$c = Login 'CRD20260102'
Check "logged in" (LoggedIn $c)
$r = Get $c '/students?search='; Check "list shows Galle student, hides Pannipitiya" ($r.body -match [regex]::Escape($gName) -and $r.body -notmatch [regex]::Escape($pName))
$r = Get $c "/students/$pStudent"; Check "Pannipitiya student refused" ($r.url -notlike "*/students/$pStudent")
$r = Get $c "/classes/$pClass/quizzes"; Check "Pannipitiya quizzes refused" ($r.url -notlike '*/quizzes')

"=== TEACHER (TCH20260100) ==="
$t = Login 'TCH20260100'
Check "logged in" (LoggedIn $t)
$r = Get $t '/teaching'; Check "teacher sees her classes in every branch" ($r.body -match 'Science - Galle' -and $r.body -match 'Science - Kandy')
$r = Get $t "/classes/$gClass/materials"; Check "teacher opens her Galle class" ($r.url -like '*/materials' -and (Clean $r))

"=== SUPER ADMIN (SA20260001) ==="
$s = Login 'SA20260001'
Check "logged in" (LoggedIn $s)
$r = Get $s '/students?search='; Check "super admin sees both branches' students" ($r.body -match [regex]::Escape($gName) -and $r.body -match [regex]::Escape($pName))
$r = Get $s '/insights'; Check "super admin insights show all branches" ($r.body -match 'Galle Science Centre' -and $r.body -match 'Kandy Science Centre')
$r = Get $s '/users/17/edit'; Check "super admin sees branch picker" ($r.body -match 'name="branches\[\]"')
$r = Get $s '/audit'; Check "super admin opens audit log" ($r.url -like '*/audit' -and (Clean $r))

""; "$($script:n - $script:fails) of $($script:n) passed"
