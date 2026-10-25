# Authentication, registration credentials and account recovery -- isolated copy only.
$base = "http://localhost/lms_rc"; $mysql = "C:\xampp\mysql\bin\mysql.exe"; $db = "student_lms_rc"
$envFile = "C:\xampp\htdocs\lms_rc\.env"; $envOrig = [IO.File]::ReadAllText($envFile)
$tmp = "C:\lms_rc_data\jars"; New-Item -ItemType Directory -Force -Path $tmp | Out-Null
$saPw = [IO.File]::ReadAllText("C:\lms_rc_data\test_sa_password.txt")
function Q($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function X($sql) { & $mysql -u root -e "USE $db; $sql" 2>&1 | Out-Null }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-72} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "a_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "a_body.html"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from = $null) {
  if ($null -eq $from) { $from = $p }
  $t = Tok $w $from; $out = Join-Path $tmp "a_body.html"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { foreach ($v in @($fields[$k])) { $a += '--data-urlencode'; $a += "$k=$v" } }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id, $pw = 'Demo@12345') { Remove-Item (Jar $w) -ea 0; $r = Post $w '/login' @{ user_id = $id; password = $pw } '/login'; return $r }
function In($r) { $r.url -notlike '*/login' }
function SetEnv($k, $v) { [IO.File]::WriteAllText($envFile, $envOrig + "`n$k=$v`n") }
function Secrets($body) { @([regex]::Matches($body, 'letter-spacing:[^"]*">([^<]+)</code>') | % { $_.Groups[1].Value }) }
function Logins($body) { @([regex]::Matches($body, '<td><code>([^<]+)</code>') | % { $_.Groups[1].Value }) }
$rand = Get-Random -Minimum 1000000 -Maximum 9999999
$gPhone = "077$rand"

function ClearLimits { X "DELETE FROM login_attempts;" }
ClearLimits
"=== existing accounts still work after migration 013 ==="
Check "student logs in" (In (Login 's0' 'STU20260200'))
Check "parent logs in" (In (Login 'p0' '0720000000'))
Check "branch admin logs in" (In (Login 'adm' 'ADM20260101'))
Check "test super admin logs in" (In (Login 'sa' 'SATEST0001' $saPw))
Check "login page has a working 'Forgot password?' link" (((Get 'x' '/login').body -match '/reset-password') -and (Get 'x' '/reset-password').code -eq 200)
Check "login page no longer offers a dead 'Remember me' box" ((Get 'x' '/login').body -notmatch 'remember_me')

"=== registration issues real credentials ==="
$r = Post 'adm' '/students/create' @{ full_name = 'RCTEST Student'; date_of_birth = '2013-05-01'; guardian_name = 'RCTEST Guardian'; guardian_phone = $gPhone; institute_id = '3'; 'class_ids[]' = '7' } '/students/create'
$secrets = @(Secrets $r.body); $logins = @(Logins $r.body)
Check "registration lands on the one-time login details page" ($r.url -like '*/accounts/issued' -and $secrets.Count -eq 2)
$sid = Q "SELECT id FROM students WHERE full_name='RCTEST Student' ORDER BY id DESC LIMIT 1;"
$sDisp = Q "SELECT student_id_display FROM students WHERE id=$sid;"
"  new student ID: $sDisp; shown: $($logins -join ', ')"
Check "student ID auto-generated and shown" ($sDisp -match '^STU\d{7,}$' -and $logins -contains $sDisp)
Check "student has a real password hash and must change it" ((Q "SELECT CONCAT(LENGTH(password_hash)>20,must_change_password) FROM students WHERE id=$sid;") -eq '11')
Check "parent login created, flagged to change password" ((Q "SELECT CONCAT(COUNT(*),MAX(must_change_password)) FROM parents WHERE student_id=$sid AND phone1='$gPhone';") -eq '11')
Check "student actually enrolled in the chosen class" ((Q "SELECT COUNT(*) FROM enrollments WHERE student_id=$sid AND class_id=7 AND enrollment_status='active';") -eq '1')
Check "temporary passwords are not stored in plain text" ((Q "SELECT COUNT(*) FROM students WHERE id=$sid AND password_hash='$($secrets[0])';") -eq '0')
$again = Get 'adm' '/accounts/issued'
Check "login details page is empty on a second visit" ((Secrets $again.body).Count -eq 0)
$tempS = $secrets[0]; $tempP = $secrets[1]

$r = Post 'g' '/login' @{ user_id = 'ADM20260102'; password = 'Demo@12345' } '/login'
$before = Q "SELECT COUNT(*) FROM students;"
$null = Post 'g' '/students/create' @{ full_name = 'RCTEST Wrong Branch'; date_of_birth = '2013-05-01'; institute_id = '3'; 'class_ids[]' = '7' } '/students/create'
Check "Galle admin cannot register into a Pannipitiya class" ((Q "SELECT COUNT(*) FROM students;") -eq $before)

"=== first login with a temporary password ==="
$r = Login 'ns' $sDisp $tempS
Check "new student logs in with the temporary password" (In $r)
Check "...and is sent straight to the change-password page" ($r.url -like '*/first-login-password')
Check "other pages are blocked until the password is changed" ((Get 'ns' '/my/classes').url -like '*/first-login-password')
$null = Post 'ns' '/first-login-password' @{ new_password = 'short'; new_password_confirmation = 'short' }
Check "weak password refused" ((Q "SELECT must_change_password FROM students WHERE id=$sid;") -eq '1')
$null = Post 'ns' '/first-login-password' @{ new_password = 'Demo@12345'; new_password_confirmation = 'Demo@12345' }
Check "demo password refused as a new password" ((Q "SELECT must_change_password FROM students WHERE id=$sid;") -eq '1')
$newS = 'Rc!' + [guid]::NewGuid().ToString('N').Substring(0, 8) + 'aZ9'
$r = Post 'ns' '/first-login-password' @{ new_password = $newS; new_password_confirmation = $newS }
Check "strong password accepted; student reaches the portal" ((Q "SELECT must_change_password FROM students WHERE id=$sid;") -eq '0' -and (Get 'ns' '/my/classes').url -like '*/my/classes')
Check "the session continues after changing the password" (In (Get 'ns' '/dashboard'))
$r = Login 'np' $gPhone $tempP
Check "new parent is forced to change the temporary password" ($r.url -like '*/first-login-password')

"=== reset codes ==="
$r = Post 'adm' "/students/$sid/reset-code" @{} "/students/$sid"
$code = [string](@(Secrets $r.body)[0])
Check "admin issues a reset code, shown once" ($r.url -like '*/accounts/issued' -and $code -match '^[A-Z2-9]{4}-[A-Z2-9]{4}$')
Check "only a hash of the code is stored" ((Q "SELECT COUNT(*) FROM password_resets WHERE account_type='student' AND account_id=$sid AND code_hash LIKE '\$2y\$%' AND code_hash NOT LIKE '%$($code.Replace('-',''))%';") -eq '1')
Check "the code is not written to the audit log" ((Q "SELECT COUNT(*) FROM audit_log WHERE details LIKE '%$($code.Replace('-',''))%' OR details LIKE '%$code%';") -eq '0')
$newS2 = 'Rs!' + [guid]::NewGuid().ToString('N').Substring(0, 8) + 'bY8'
$r = Post 'anon' '/reset-password' @{ login_id = $sDisp; reset_code = 'AAAA-AAAA'; new_password = $newS2; new_password_confirmation = $newS2 }
Check "a wrong code is refused and counted" ((Q "SELECT attempts FROM password_resets WHERE account_type='student' AND account_id=$sid ORDER BY id DESC LIMIT 1;") -eq '1')
$r = Post 'anon' '/reset-password' @{ login_id = 'STU99999999'; reset_code = $code; new_password = $newS2; new_password_confirmation = $newS2 }
Check "the code does not work with another login ID" ($r.body -match 'not valid')
$r = Post 'anon' '/reset-password' @{ login_id = $sDisp; reset_code = $code.ToLower(); new_password = $newS2; new_password_confirmation = $newS2 }
Check "the correct code resets the password (case-insensitive)" ($r.url -like '*/login' -and $r.body -match 'password has been changed|මුරපදය')
Check "the old session ends at once after a reset" ((Get 'ns' '/my/classes').url -like '*/login')
Check "the old password no longer works" (-not (In (Login 'ns2' $sDisp $newS)))
Check "the new password works" (In (Login 'ns3' $sDisp $newS2))
$r = Post 'anon2' '/reset-password' @{ login_id = $sDisp; reset_code = $code; new_password = 'Zz!' + $newS2; new_password_confirmation = 'Zz!' + $newS2 }
Check "a used code cannot be used again" ($r.body -match 'not valid' -and (In (Login 'ns4' $sDisp $newS2)))
$r = Post 'adm' "/students/$sid/reset-code" @{} "/students/$sid"; $c2 = [string](@(Secrets $r.body)[0])
X "UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE account_type='student' AND account_id=$sid AND used_at IS NULL AND revoked_at IS NULL;"
$r = Post 'anon3' '/reset-password' @{ login_id = $sDisp; reset_code = $c2; new_password = 'Exp!' + $newS2; new_password_confirmation = 'Exp!' + $newS2 }
Check "an expired code is refused" ($r.body -match 'not valid')
$r = Post 'adm' "/students/$sid/reset-code" @{} "/students/$sid"; $c3 = [string](@(Secrets $r.body)[0])
$r = Post 'adm' "/students/$sid/reset-code" @{} "/students/$sid"; $c4 = [string](@(Secrets $r.body)[0])
$r = Post 'anon4' '/reset-password' @{ login_id = $sDisp; reset_code = $c3; new_password = 'Old!' + $newS2; new_password_confirmation = 'Old!' + $newS2 }
Check "issuing a new code cancels the earlier one" ($r.body -match 'not valid')
ClearLimits
foreach ($k in 1..5) { $null = Post "w$k" '/reset-password' @{ login_id = $sDisp; reset_code = 'BBBB-BBBB'; new_password = 'Bad!' + $newS2; new_password_confirmation = 'Bad!' + $newS2 } }
$r = Post 'anon5' '/reset-password' @{ login_id = $sDisp; reset_code = $c4; new_password = 'Lck!' + $newS2; new_password_confirmation = 'Lck!' + $newS2 }
Check "after five wrong tries even the right code is refused" ($r.body -match 'not valid')
$pid_ = Q "SELECT id FROM parents WHERE student_id=$sid LIMIT 1;"
$cnt = Q "SELECT COUNT(*) FROM password_resets;"
$null = Post 'g' "/students/$sid/reset-code" @{} '/dashboard'
$null = Post 'g' "/parents/$pid_/reset-code" @{} '/dashboard'
Check "another branch's admin cannot issue codes for this family" ((Q "SELECT COUNT(*) FROM password_resets;") -eq $cnt)
$null = Login 't' 'TCH20260100'; $null = Post 't' "/students/$sid/reset-code" @{} '/dashboard'
Check "a teacher cannot issue reset codes" ((Q "SELECT COUNT(*) FROM password_resets;") -eq $cnt)
$null = Post 'adm' '/users/20/reset-code' @{} '/dashboard'; $null = Post 'adm' '/users/20/reset-password' @{ new_password = 'Demo@12345' } '/dashboard'
Check "a branch admin cannot reset the super admin" ((Q "SELECT COUNT(*) FROM password_resets WHERE account_type='user' AND account_id=20;") -eq '0' -and (In (Login 'sa2' 'SATEST0001' $saPw)))
$r = Post 'sa' '/users/15/reset-code' @{} '/users/15/edit'
Check "super admin can issue a staff reset code" (((Secrets $r.body).Count) -eq 1)
X "UPDATE password_resets SET revoked_at = NOW() WHERE account_type='user' AND account_id=15 AND used_at IS NULL;"

ClearLimits
foreach ($k in 1..10) { $null = Post "rl$k" '/reset-password' @{ login_id = 'STU00000000'; reset_code = 'CCCC-CCCC'; new_password = 'Rl!' + $newS2; new_password_confirmation = 'Rl!' + $newS2 } }
$r = Post 'rl11' '/reset-password' @{ login_id = $sDisp; reset_code = 'CCCC-CCCC'; new_password = 'Rl!' + $newS2; new_password_confirmation = 'Rl!' + $newS2 }
Check "reset page slows down an address after 10 failed tries" ($r.body -match 'Too many attempts')
ClearLimits
"=== access changes take effect immediately ==="
$null = Login 'crd' 'CRD20260101'
Check "coordinator is signed in" (In (Get 'crd' '/dashboard'))
X "UPDATE users SET status='inactive' WHERE user_id_display='CRD20260101';"
Check "deactivating the account ends the open session" ((Get 'crd' '/dashboard').url -like '*/login')
Check "...and blocks a new login" (-not (In (Login 'crd2' 'CRD20260101')))
X "UPDATE users SET status='active' WHERE user_id_display='CRD20260101';"
$null = Login 'crd' 'CRD20260101'
X "UPDATE users SET role='staff' WHERE user_id_display='CRD20260101';"
Check "changing the role ends the open session" ((Get 'crd' '/dashboard').url -like '*/login')
X "UPDATE users SET role='coordinator' WHERE user_id_display='CRD20260101';"
$null = Login 'stx' 'STU20260200'
X "UPDATE students SET status='deactivated' WHERE id=11;"
Check "deactivating a student ends the open session" ((Get 'stx' '/my/classes').url -like '*/login')
X "UPDATE students SET status='active' WHERE id=11;"

"=== session expiry and production rules ==="
SetEnv 'SESSION_TIMEOUT' '4'
$null = Login 'exp' 'ADM20260101'; Start-Sleep -Seconds 6
Check "an idle session expires" ((Get 'exp' '/dashboard').url -like '*/login')
SetEnv 'APP_ENV' 'production'
Check "production: a demo password cannot log in" (-not (In (Login 'prod' 'STU20260200')))
Check "production: a personal password still logs in" (In (Login 'prod2' $sDisp $newS2))
[IO.File]::WriteAllText($envFile, $envOrig)
Check "development again: demo login works" (In (Login 'dev' 'STU20260200'))

"=== clean up ==="
X "DELETE FROM password_resets WHERE account_type='student' AND account_id=$sid; DELETE FROM parents WHERE student_id=$sid; DELETE FROM enrollments WHERE student_id=$sid; DELETE FROM students WHERE id=$sid;"
Check "test student removed" ((Q "SELECT COUNT(*) FROM students WHERE full_name LIKE 'RCTEST%';") -eq '0')
""; "$($script:n - $script:fails) of $($script:n) passed"
