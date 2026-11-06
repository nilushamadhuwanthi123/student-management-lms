# Role-based feature visibility (nav-only) -- isolated copy only.
# Proves: (1) hiding a feature removes its sidebar link for that role,
# (2) the underlying route is NOT affected -- it stays exactly as
# protected as before, because this is UI-only, never an auth check,
# (3) Super Admin can never be locked out of Settings by this feature,
# and (4) toggling super_admin itself is rejected.
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
function Jar($w) { Join-Path $tmp "fv_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "fv_body.bin"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from) {
  $t = Tok $w $from; $out = Join-Path $tmp "fv_body.bin"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { foreach ($v in @($fields[$k])) { $a += '--data-urlencode'; $a += "$k=$v" } }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id, $pw = 'Demo@12345') { Remove-Item (Jar $w) -ea 0; $null = Post $w '/login' @{ user_id = $id; password = $pw } '/login'; return ((Get $w '/dashboard').url -notlike '*/login') }

function AllOn($role, $overrides) {
    $f = @{ active_role = $role }
    foreach ($k in @('homework','recordings','past_papers','payments','notices','help','my_progress','calendar','ai_assistant')) {
        $v = if ($overrides.ContainsKey($k)) { $overrides[$k] } else { '1' }
        $f["features[$k]"] = $v
    }
    return $f
}

X "DELETE FROM login_attempts;"
X "DELETE FROM feature_settings WHERE role='student';"

Check "super admin logs in" (Login 'sa' 'SATEST0001' $saPw)
Check "student logs in" (Login 's' 'STU20260200')

"=== before any change: homework link is shown, route works ==="
$r = Get 's' '/my/home'
Check "homework link shown by default" ($r.body -match '/my/assignments')
$r = Get 's' '/my/assignments'
Check "assignments page reachable by default" ($r.code -eq 200)

"=== super admin hides homework for students ==="
$r = Post 'sa' '/settings/feature-visibility' (AllOn 'student' @{ homework = '0' }) '/settings/feature-visibility'
Check "save redirected back to the page" ($r.url -like '*feature-visibility*')
Check "stored as hidden" ((Q "SELECT is_visible FROM feature_settings WHERE role='student' AND feature_key='homework';") -eq '0')

"=== the nav link is gone, but the route itself still works exactly as before ==="
$r = Get 's' '/my/home'
Check "homework link no longer shown" ($r.body -notmatch '/my/assignments')
Check "other student links unaffected" ($r.body -match '/my/recordings')
$r = Get 's' '/my/assignments'
Check "assignments route STILL reachable -- hiding a link is not an access control" ($r.code -eq 200)

"=== Super Admin can always reach the page that manages this (no lock-out) ==="
$r = Get 'sa' '/settings/feature-visibility'
Check "super admin still reaches the feature-visibility page" ($r.code -eq 200 -and $r.url -notlike '*/login')

"=== toggling super_admin itself is rejected outright ==="
$before = Q "SELECT COUNT(*) FROM feature_settings WHERE role='super_admin';"
$r = Post 'sa' '/settings/feature-visibility' @{ active_role = 'super_admin'; 'features[help]' = '0' } '/settings/feature-visibility'
$after = Q "SELECT COUNT(*) FROM feature_settings WHERE role='super_admin';"
Check "no row was ever created for super_admin" ($before -eq $after -and $after -eq '0')

"=== restoring visibility brings the link back ==="
$null = Post 'sa' '/settings/feature-visibility' (AllOn 'student' @{}) '/settings/feature-visibility'
$r = Get 's' '/my/home'
Check "homework link restored" ($r.body -match '/my/assignments')

X "DELETE FROM feature_settings WHERE role='student';"
X "DELETE FROM login_attempts;"
Check "test rows removed" ((Q "SELECT COUNT(*) FROM feature_settings WHERE role='student';") -eq '0')

"$($script:n - $script:fails) of $($script:n) passed"
