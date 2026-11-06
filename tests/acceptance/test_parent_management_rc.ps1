# Parent/Guardian account management (add / edit / deactivate / reactivate)
# -- isolated copy only.
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
function Jar($w) { Join-Path $tmp "pm_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "pm_body.bin"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from) {
  $t = Tok $w $from; $out = Join-Path $tmp "pm_body.bin"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { $a += '--data-urlencode'; $a += "$k=$($fields[$k])" }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id, $pw = 'Demo@12345') { Remove-Item (Jar $w) -ea 0; $null = Post $w '/login' @{ user_id = $id; password = $pw } '/login'; return ((Get $w '/dashboard').url -notlike '*/login') }

X "DELETE FROM parents WHERE parent_name LIKE 'RCTEST-%';"

$pStudent = Q "SELECT s.id FROM students s JOIN enrollments e ON e.student_id=s.id JOIN classes c ON c.id=e.class_id WHERE c.institute_id=3 AND e.enrollment_status='active' LIMIT 1;"
"fixture: Pannipitiya student $pStudent"
Check "fixture student exists" ($pStudent -ne '')

Check "Pannipitiya admin logs in" (Login 'a' 'ADM20260101')
Check "Galle coordinator logs in" (Login 'g' 'CRD20260102')
Check "teacher logs in" (Login 't' 'TCH20260100')

"=== add a guardian ==="
$r = Post 'a' "/students/$pStudent/parents/create" @{
    parent_name = 'RCTEST-Guardian'; phone1 = '0771230001'; phone2 = ''; whatsapp = ''
} "/students/$pStudent/parents/create"
$prid = Q "SELECT id FROM parents WHERE parent_name='RCTEST-Guardian';"
Check "guardian row created" ($prid -ne '' -and (Q "SELECT student_id FROM parents WHERE id=$prid;") -eq $pStudent)
Check "guardian starts active" ((Q "SELECT status FROM parents WHERE id=$prid;") -eq 'active')

"=== teacher cannot add a guardian ==="
$before = Q "SELECT COUNT(*) FROM parents WHERE student_id=$pStudent;"
$r = Post 't' "/students/$pStudent/parents/create" @{ parent_name = 'RCTEST-ShouldNotExist'; phone1 = '0771230099' } "/dashboard"
Check "teacher's attempt created nothing" ((Q "SELECT COUNT(*) FROM parents WHERE student_id=$pStudent;") -eq $before)

"=== Galle coordinator cannot touch a Pannipitiya guardian ==="
$r = Get 'g' "/parents/$prid/edit"
Check "Galle coordinator refused the edit page" ($r.url -notlike "*/parents/$prid/edit")
$r = Post 'g' "/parents/$prid/deactivate" @{} '/dashboard'
Check "Galle coordinator's deactivate attempt did nothing" ((Q "SELECT status FROM parents WHERE id=$prid;") -eq 'active')

"=== edit the guardian ==="
$r = Post 'a' "/parents/$prid/edit" @{
    parent_name = 'RCTEST-Guardian-Renamed'; phone1 = '0771230002'; phone2 = ''; whatsapp = ''
} "/parents/$prid/edit"
Check "name updated" ((Q "SELECT parent_name FROM parents WHERE id=$prid;") -eq 'RCTEST-Guardian-Renamed')
Check "phone updated" ((Q "SELECT phone1 FROM parents WHERE id=$prid;") -eq '0771230002')

"=== deactivate / reactivate ==="
# Give the test guardian a known password (Demo@12345, same hash every other
# fixture account uses) so the login checks below don't depend on the
# random one-time temporary password that was generated and shown once.
X "UPDATE parents SET password_hash=(SELECT password_hash FROM users WHERE user_id_display='ADM20260101') WHERE id=$prid;"
Check "new guardian can log in before deactivation" (Login 'np' '0771230002')
$r = Post 'a' "/parents/$prid/deactivate" @{} "/parents/$prid/edit"
Check "status is now deactivated" ((Q "SELECT status FROM parents WHERE id=$prid;") -eq 'deactivated')
Check "student itself is untouched" ((Q "SELECT status FROM students WHERE id=$pStudent;") -eq 'active')
Check "deactivated guardian can no longer log in" (-not (Login 'np2' '0771230002'))
$r = Post 'a' "/parents/$prid/reactivate" @{} "/parents/$prid/edit"
Check "status is active again" ((Q "SELECT status FROM parents WHERE id=$prid;") -eq 'active')
Check "guardian can log in again" (Login 'np3' '0771230002')

X "DELETE FROM parents WHERE parent_name LIKE 'RCTEST-%';"
X "DELETE FROM login_attempts;"
Check "test rows removed" ((Q "SELECT COUNT(*) FROM parents WHERE parent_name LIKE 'RCTEST-%';") -eq '0')

"$($script:n - $script:fails) of $($script:n) passed"
