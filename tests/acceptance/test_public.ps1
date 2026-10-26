$base  = "http://localhost/student-management-lms"
$mysql = "C:\xampp\mysql\bin\mysql.exe"
$tmp   = Join-Path $PSScriptRoot "ft_tmp"; New-Item -ItemType Directory -Force -Path $tmp | Out-Null
function Q($sql) { $o = & $mysql -u root -N -e "USE student_lms; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-64} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "pub_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "pub.html"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { return [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Login($w, $id) { Remove-Item (Jar $w) -ea 0; $t = Tok $w '/login'; $null = curl.exe -s -o NUL -b (Jar $w) -c (Jar $w) --data-urlencode "_csrf_token=$t" --data-urlencode "user_id=$id" --data-urlencode "password=Demo@12345" "$base/login"; return ((Get $w '/dashboard').url -notlike '*/login') }
function Inquire($w, $fields) { $t = Tok $w '/'; $a = @('-s','-o','NUL','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t"); foreach ($k in $fields.Keys) { $a += '--data-urlencode'; $a += "$k=$($fields[$k])" }; $a += "$base/inquiry"; $null = & curl.exe @a }
Get-ChildItem $tmp -Filter "pub_*.jar" -ErrorAction SilentlyContinue | Remove-Item -Force   # fresh sessions: the form allows 3 inquiries per visitor per hour
$start = [int](Q "SELECT COALESCE(MAX(id),0) FROM contact_messages;")

$r = Get 'v' '/'; Check "home page loads with public branches only" ($r.code -eq 200 -and $r.body -match 'Galle Science Centre' -and $r.body -notmatch 'Sepala')
Check "home has no fabricated statistics" ($r.body -notmatch 'Trusted by|99\.9%|Students Managed')
Check "no meeting links on public pages" ($r.body -notmatch 'zoom\.us|meet\.google')
Check "public page is indexable; portal is not" ($r.body -notmatch 'noindex' -and (Get 'v' '/login').body -match 'noindex')
$old = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds() - 60
Inquire 'v' @{ parent_name='TEST Parent'; phone='0771234567'; grade='9'; branch_id='4'; student_name='TEST Kid'; started_at="$old"; website='' }
$ok = Q "SELECT CONCAT(kind,'|',institute_id,'|',grade) FROM contact_messages WHERE id>$start AND full_name='TEST Parent';"
Check "valid inquiry stored for the Galle office" ($ok -eq 'enrollment|4|9')
Inquire 'v2' @{ parent_name='TEST Bot'; phone='0771234567'; grade='9'; branch_id='4'; started_at="$old"; website='http://spam.example' }
Check "honeypot submission silently dropped" ((Q "SELECT COUNT(*) FROM contact_messages WHERE full_name='TEST Bot';") -eq '0')
Inquire 'v3' @{ parent_name='TEST BadPhone'; phone='12345'; grade='9'; branch_id='4'; started_at="$old" }
Check "invalid phone refused" ((Q "SELECT COUNT(*) FROM contact_messages WHERE full_name='TEST BadPhone';") -eq '0')
Inquire 'v4' @{ parent_name='TEST Private'; phone='0771234567'; grade='9'; branch_id='1'; started_at="$old" }
Check "non-public branch refused" ((Q "SELECT COUNT(*) FROM contact_messages WHERE full_name='TEST Private';") -eq '0')
Check "Galle admin logs in" (Login 'ga' 'ADM20260102')
$r = Get 'ga' '/inquiries'; Check "Galle admin sees the Galle inquiry" ($r.body -match 'TEST Parent')
Check "Pannipitiya admin logs in" (Login 'pa' 'ADM20260101')
$r = Get 'pa' '/inquiries'; Check "Pannipitiya admin does not" ($r.body -notmatch 'TEST Parent')
Check "student logs in" (Login 's' 'STU20260200')
$r = Get 's' '/inquiries'; Check "student cannot open inquiries" ($r.body -notmatch 'TEST Parent')
& $mysql -u root -e "USE student_lms; DELETE FROM contact_messages WHERE id>$start AND full_name LIKE 'TEST %';" 2>&1 | Out-Null
Check "test rows removed" ((Q "SELECT COUNT(*) FROM contact_messages WHERE full_name LIKE 'TEST %';") -eq '0')
""; "$($script:n - $script:fails) of $($script:n) passed"
