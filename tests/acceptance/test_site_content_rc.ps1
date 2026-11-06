# Website Content (public-site CMS) -- a small, fixed set of editable
# text blocks on the home/about/contact pages. Isolated copy only.
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
function Jar($w) { Join-Path $tmp "sc_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "sc_body.bin"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from) {
  $t = Tok $w $from; $out = Join-Path $tmp "sc_body.bin"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { $a += '--data-urlencode'; $a += "$k=$($fields[$k])" }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id, $pw = 'Demo@12345') { Remove-Item (Jar $w) -ea 0; $null = Post $w '/login' @{ user_id = $id; password = $pw } '/login'; return ((Get $w '/dashboard').url -notlike '*/login') }

X "DELETE FROM site_content;"

"=== default wording shows when nothing has been customized ==="
$homePage = (Get 'anon' '/').body
Check "home shows the default hero text" ($homePage -like "*Weekly Science classes for Grades*")
$about = (Get 'anon2' '/about').body
Check "about shows the default 'In class' text" ($about -like "*Each grade has its own weekly class at the branch*")
Check "about's optional extra paragraph is hidden by default" ($about -notlike "*max-width:70ch*")
$contact = (Get 'anon3' '/contact').body
Check "contact shows the default intro line" ($contact -like "*Call the branch nearest to you*")

"=== access control ==="
Check "super admin (test account) logs in" (Login 'sa' 'SATEST0001' $saPw)
$r = Get 'sa' '/settings/site-content'
Check "super admin can open the Website Content settings page" ($r.code -eq 200 -and $r.body -like "*Website Content*")

Check "admin (non-super) logs in" (Login 'a' 'ADM20260101')
$r = Get 'a' '/settings/site-content'
Check "plain admin is refused -- this is super-admin only, like the rest of Settings" ($r.url -notlike "*/settings/site-content" -or $r.code -ne 200 -or $r.body -notlike "*fields[home_hero_text]*")

Check "coordinator logs in" (Login 'anura' 'CRD20260101')
$r = Get 'anura' '/settings/site-content'
Check "coordinator is refused" ($r.url -notlike "*/settings/site-content" -or $r.code -ne 200 -or $r.body -notlike "*fields[home_hero_text]*")

Check "teacher logs in" (Login 't' 'TCH20260100')
$r = Get 't' '/settings/site-content'
Check "teacher is refused" ($r.url -notlike "*/settings/site-content" -or $r.code -ne 200 -or $r.body -notlike "*fields[home_hero_text]*")

$r = Post 'anura' '/settings/site-content' @{
    'fields[home_hero_text]' = 'HACKED BY COORDINATOR'
} '/dashboard'
Check "coordinator's POST did not change the content" ((Q "SELECT COUNT(*) FROM site_content WHERE content_key='home_hero_text';") -eq '0')

"=== super admin updates the content, and it appears on the public pages ==="
$r = Post 'sa' '/settings/site-content' @{
    'fields[home_hero_text]' = 'RCTEST custom hero paragraph for the home page.'
    'fields[about_in_class_text]' = 'RCTEST custom in-class paragraph.'
    'fields[about_extra_text]' = 'RCTEST this is the optional extra story paragraph.'
    'fields[contact_intro_text]' = 'RCTEST custom contact intro line.'
} '/settings/site-content'
Check "update redirected back to the settings page" ($r.url -like "*/settings/site-content")
Check "home_hero_text stored" ((Q "SELECT content_value FROM site_content WHERE content_key='home_hero_text';") -eq 'RCTEST custom hero paragraph for the home page.')

$homePage2 = (Get 'anon4' '/').body
Check "home now shows the custom hero text" ($homePage2 -like "*RCTEST custom hero paragraph for the home page.*")
$about2 = (Get 'anon5' '/about').body
Check "about now shows the custom in-class text" ($about2 -like "*RCTEST custom in-class paragraph.*")
Check "about's optional extra paragraph now appears" ($about2 -like "*RCTEST this is the optional extra story paragraph.*")
$contact2 = (Get 'anon6' '/contact').body
Check "contact now shows the custom intro line" ($contact2 -like "*RCTEST custom contact intro line.*")

"=== blanking a field reverts it to the default, not a blank page ==="
$r = Post 'sa' '/settings/site-content' @{
    'fields[home_hero_text]' = ''
    'fields[about_in_class_text]' = 'RCTEST custom in-class paragraph.'
    'fields[about_extra_text]' = 'RCTEST this is the optional extra story paragraph.'
    'fields[contact_intro_text]' = 'RCTEST custom contact intro line.'
} '/settings/site-content'
$homePage3 = (Get 'anon7' '/').body
Check "blank hero field reverted home page to the default text" ($homePage3 -like "*Weekly Science classes for Grades*" -and $homePage3 -notlike "*RCTEST custom hero*")

"=== XSS / escaping safety ==="
$xssPayload = '<script>alert(1)</script>'
$r = Post 'sa' '/settings/site-content' @{
    'fields[home_hero_text]' = $xssPayload
    'fields[about_in_class_text]' = 'RCTEST custom in-class paragraph.'
    'fields[about_extra_text]' = ''
    'fields[contact_intro_text]' = 'RCTEST custom contact intro line.'
} '/settings/site-content'
$homePage4 = (Get 'anon8' '/').body
Check "script tag stored but rendered escaped, not executed" ($homePage4 -like "*&lt;script&gt;alert(1)&lt;/script&gt;*" -and $homePage4 -notlike "*<script>alert(1)</script>*")
Check "the settings form itself also escapes it (textarea safe)" ($r.code -ne 500)

X "DELETE FROM site_content;"
Check "test rows removed" ((Q "SELECT COUNT(*) FROM site_content;") -eq '0')

"$($script:n - $script:fails) of $($script:n) passed"
