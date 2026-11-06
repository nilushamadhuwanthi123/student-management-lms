# "@Full Name" mentions -> a personal notification, via the same
# notifications table every module already uses -- isolated copy only.
# Titles are compared by related_entity_id, not by English text, since
# the app's default language is Sinhala and the notification title is
# translated.
$base = "http://localhost/lms_rc"
$mysql = "C:\xampp\mysql\bin\mysql.exe"
$db = "student_lms_rc"
$tmp = "C:\lms_rc_data\jars"
New-Item -ItemType Directory -Force -Path $tmp | Out-Null

function Q($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function X($sql) { & $mysql -u root -e "USE $db; $sql" 2>&1 | Out-Null }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-72} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "mn_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "mn_body.bin"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from) {
  $t = Tok $w $from; $out = Join-Path $tmp "mn_body.bin"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { $a += '--data-urlencode'; $a += "$k=$($fields[$k])" }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id, $pw = 'Demo@12345') { Remove-Item (Jar $w) -ea 0; $null = Post $w '/login' @{ user_id = $id; password = $pw } '/login'; return ((Get $w '/dashboard').url -notlike '*/login') }

X "DELETE FROM help_threads WHERE subject = 'RCTEST-Mentions';"
X "DELETE FROM notices WHERE title = 'RCTEST-Mentions-Notice';"

# Fixtures: teacher Harshani Dilrukshi (13) teaches at institutes 3/4/5.
# Anura Senanayake (14, coordinator @ institute 3) is within her reach.
# Kamal Perera (2, staff @ institute 1) is not.
X "INSERT INTO help_threads (kind, student_id, class_id, institute_id, subject, status, last_reply_at) VALUES ('question', 11, 7, 3, 'RCTEST-Mentions', 'open', NOW());"
$tid = Q "SELECT id FROM help_threads WHERE subject='RCTEST-Mentions';"
Check "fixture help thread created" ($tid -ne '')
X "DELETE FROM notifications WHERE related_entity_type='help_thread' AND related_entity_id=$tid;"

Check "teacher logs in" (Login 't' 'TCH20260100')

"=== help-desk reply: mentions inside and outside the teacher's reach ==="
$r = Post 't' "/help/$tid/reply" @{
    body = 'Looping in @Anura Senanayake and @Kamal Perera -- also cc @Harshani Dilrukshi (myself).'
} "/help/$tid"
Check "reply posted" ($r.code -eq 200 -or $r.url -like "*/help/$tid")
Check "in-reach colleague (Anura, institute 3) got a mention notification" ((Q "SELECT COUNT(*) FROM notifications WHERE recipient_id=14 AND recipient_type='user' AND related_entity_type='help_thread' AND related_entity_id=$tid;") -eq '1')
Check "out-of-reach staff (Kamal, institute 1) got nothing" ((Q "SELECT COUNT(*) FROM notifications WHERE recipient_id=2 AND recipient_type='user' AND related_entity_type='help_thread' AND related_entity_id=$tid;") -eq '0')
Check "mentioning herself created no self-notification" ((Q "SELECT COUNT(*) FROM notifications WHERE recipient_id=13 AND recipient_type='user' AND related_entity_type='help_thread' AND related_entity_id=$tid;") -eq '0')

"=== a notice's body, mentioned colleague inside vs. outside the poster's branch ==="
Check "Pannipitiya admin logs in" (Login 'a' 'ADM20260101')
$r = Post 'a' '/notices/create' @{
    title = 'RCTEST-Mentions-Notice'; body = 'Please review with @Anura Senanayake; @Chamath Dahanayake is in Galle so this is FYI only.'
    audience = 'class'; class_id = '7'; for_students = '1'; action = 'publish'
} '/notices/create'
$nid = Q "SELECT id FROM notices WHERE title='RCTEST-Mentions-Notice';"
Check "notice created and published" ($nid -ne '' -and (Q "SELECT status FROM notices WHERE id=$nid;") -eq 'published')
Check "same-branch coordinator (Anura) was mentioned" ((Q "SELECT COUNT(*) FROM notifications WHERE recipient_id=14 AND recipient_type='user' AND related_entity_type='notice' AND related_entity_id=$nid;") -eq '1')
Check "Galle coordinator (Chamath) was not -- outside the poster's branch" ((Q "SELECT COUNT(*) FROM notifications WHERE recipient_id=15 AND recipient_type='user' AND related_entity_type='notice' AND related_entity_id=$nid;") -eq '0')

"=== the notification bell now shows a real count, not a hard-coded zero ==="
$unread = [int](Q "SELECT COUNT(*) FROM notifications WHERE recipient_id=14 AND recipient_type='user' AND is_read=0;")
Check "Anura has at least one real unread notification to show" ($unread -ge 1)
Check "Anura (coordinator) logs in" (Login 'anura' 'CRD20260101')
$r = Get 'anura' '/dashboard'
$shown = [regex]::Match($r.body, 'notification-bell__count">(\d+)<').Groups[1].Value
Check "the bell shows that same real count" ($shown -ne '' -and [int]$shown -eq $unread)

X "DELETE FROM notifications WHERE (related_entity_type='help_thread' AND related_entity_id=$tid) OR (related_entity_type='notice' AND related_entity_id=$nid);"
X "DELETE FROM help_threads WHERE subject = 'RCTEST-Mentions';"
X "DELETE FROM notices WHERE title = 'RCTEST-Mentions-Notice';"
Check "test rows removed" ((Q "SELECT COUNT(*) FROM help_threads WHERE subject='RCTEST-Mentions';") -eq '0')

"$($script:n - $script:fails) of $($script:n) passed"
