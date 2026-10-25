# Role and branch matrix, run against the isolated copy only (lms_rc / student_lms_rc).
# Expected scope is read from the explicit relations (user_institutes, institutes.coordinator_id,
# classes.teacher_id, user_classes) -- never from account names or seed order.
$base = "http://localhost/lms_rc"; $mysql = "C:\xampp\mysql\bin\mysql.exe"; $db = "student_lms_rc"
$tmp = "C:\lms_rc_data\jars"; New-Item -ItemType Directory -Force -Path $tmp | Out-Null
$saPw = [IO.File]::ReadAllText("C:\lms_rc_data\test_sa_password.txt")
function Q($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function QA($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return @() } return @($o | % { ([string]$_).Trim() } | ? { $_ -ne '' }) }
$script:n = 0; $script:fails = 0
& $mysql -u root -e "USE $db; DELETE FROM login_attempts;" 2>&1 | Out-Null
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-70} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "r_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "r_body.html"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Login($w, $id, $pw = 'Demo@12345') { Remove-Item (Jar $w) -ea 0; $t = [regex]::Match((Get $w '/login').body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value; $null = curl.exe -s -o NUL -b (Jar $w) -c (Jar $w) --data-urlencode "_csrf_token=$t" --data-urlencode "user_id=$id" --data-urlencode "password=$pw" "$base/login"; return ((Get $w '/dashboard').url -notlike '*/login') }
function Clean($r) { $r.body -notmatch 'Fatal error|Parse error|Warning:</b>|Undefined variable|Undefined array key|SQLSTATE' }
function Refused($r, $path) { -not ($r.url -like "*$path") -or $r.code -eq 403 -or $r.code -eq 404 }

# One student per branch who is enrolled ONLY in that branch, and one class per branch.
$stu = @{}; $cls = @{}; $nm = @{}
foreach ($i in 1..5) {
  $stu[$i] = Q "SELECT s.id FROM students s JOIN enrollments e ON e.student_id=s.id JOIN classes c ON c.id=e.class_id WHERE c.institute_id=$i AND e.enrollment_status='active' AND NOT EXISTS (SELECT 1 FROM enrollments e2 JOIN classes c2 ON c2.id=e2.class_id WHERE e2.student_id=s.id AND c2.institute_id<>$i) LIMIT 1;"
  if ($stu[$i] -ne '') { $nm[$i] = Q "SELECT student_id_display FROM students WHERE id=$($stu[$i]);" }
  $cls[$i] = Q "SELECT id FROM classes WHERE institute_id=$i AND status='active' LIMIT 1;"
}
$have = @(1..5 | ? { $stu[$_] -ne '' })
"fixtures: students per branch " + (($have | % { "$_=$($nm[$_])" }) -join ', ')
Check "fixtures: a student in at least three branches" ($have.Count -ge 3)

# ---------------- office roles: admin, coordinator, staff ----------------
$office = QA "SELECT CONCAT(id,'|',user_id_display,'|',role) FROM users WHERE role IN ('admin','coordinator','staff') AND status='active' ORDER BY role, id;"
foreach ($row in $office) {
  $uid, $disp, $role = $row.Split('|')
  $mine = @(QA "SELECT institute_id FROM user_institutes WHERE user_id=$uid UNION SELECT id FROM institutes WHERE coordinator_id=$uid;" | % { [int]$_ })
  "=== $role $disp  branches: $($mine -join ',') ==="
  if (-not (Login 'o' $disp)) { Check "$disp logs in" $false; continue }
  $ok = $true; $okDirect = $true
  foreach ($i in $have) {
    $list = Get 'o' "/students?search=$($nm[$i])"
    $visible = $list.body -match ('/students/' + $stu[$i] + '"')
    if ($visible -ne ($mine -contains $i)) { $ok = $false; "    list mismatch for branch $i" }
    $d = Get 'o' "/students/$($stu[$i])"
    $opened = ($d.url -like "*/students/$($stu[$i])") -and $d.code -eq 200
    if ($opened -ne ($mine -contains $i)) { $okDirect = $false; "    direct-id mismatch for branch $i" }
  }
  Check "$disp student list = own branches only" $ok
  Check "$disp direct student id = own branches only" $okDirect
  $okc = $true
  foreach ($i in 1..5) { if ($cls[$i] -eq '') { continue }
    $c = Get 'o' "/classes/$($cls[$i])/edit"; $opened = ($c.url -like '*/edit') -and $c.code -eq 200
    $should = ($mine -contains $i) -and $role -ne 'staff' -and $role -ne 'coordinator'
    if ($role -eq 'admin' -and $opened -ne $should) { $okc = $false; "    class edit mismatch for branch $i" }
    if ($role -ne 'admin' -and $opened -and -not ($mine -contains $i)) { $okc = $false; "    out-of-branch class edit opened, branch $i" }
  }
  Check "$disp class edit never outside own branches" $okc
  $a = Get 'o' '/audit'; Check "$disp full audit log refused" (Refused $a '/audit')
  $s = Get 'o' '/settings'; Check "$disp system settings refused" (Refused $s '/settings')
  $u = Get 'o' '/users/1/edit'; Check "$disp cannot edit super admin" (Refused $u '/edit')
  $d = Get 'o' '/dashboard'; Check "$disp dashboard renders cleanly" ((Clean $d) -and $d.code -eq 200)
  $ins = Get 'o' '/reports/insights'
  $foreign = @(QA "SELECT name FROM institutes WHERE id NOT IN ($(if ($mine.Count) { $mine -join ',' } else { '0' }));" | ? { $ins.body -match [regex]::Escape($_) })
  Check "$disp insights show no other branch by name" ((Clean $ins) -and $foreign.Count -eq 0)
  foreach ($i in 1..5) { if ($cls[$i] -eq '' -or ($mine -contains $i)) { continue }
    $api = Get 'o' "/api/classes-by-institute/$i"; if ($api.body -match '"class_name"') { Check "$disp API refuses branch $i class list" $false } }
}

# ---------------- teachers ----------------
$teachers = QA "SELECT CONCAT(id,'|',user_id_display) FROM users WHERE role='teacher' AND status='active';"
foreach ($row in $teachers) {
  $uid, $disp = $row.Split('|')
  $own = @(QA "SELECT id FROM classes WHERE teacher_id=$uid UNION SELECT class_id FROM user_classes WHERE user_id=$uid;" | % { [int]$_ })
  "=== teacher $disp  classes: $($own -join ',') ==="
  if (-not (Login 't' $disp)) { Check "$disp logs in" $false; continue }
  $ok = $true
  foreach ($cid in (QA "SELECT id FROM classes WHERE status='active';" | % { [int]$_ })) {
    $r = Get 't' "/classes/$cid/materials"; $opened = ($r.url -like '*/materials') -and $r.code -eq 200
    if ($opened -ne ($own -contains $cid)) { $ok = $false; "    class $cid mismatch (opened=$opened)" }
  }
  Check "$disp opens exactly their own classes' materials" $ok
  $p = Get 't' '/payments'; Check "$disp payments page refused" (Refused $p '/payments')
  $a = Get 't' '/audit'; Check "$disp audit refused" (Refused $a '/audit')
}

# ---------------- super admin (test account in the copy only) ----------------
"=== super admin SATEST0001 (copy only) ==="
Check "test super admin logs in" (Login 'sa' 'SATEST0001' $saPw)
Check "super admin sees students of every branch" (@($have | ? { (Get 'sa' "/students?search=$($nm[$_])").body -match ('/students/' + $stu[$_] + '"') }).Count -eq $have.Count)
$r = Get 'sa' '/reports/insights'; Check "super admin insights show all three centres" ($r.body -match 'Galle Science Centre' -and $r.body -match 'Kandy Science Centre' -and $r.body -match 'Pannipitiya Science Centre')
$r = Get 'sa' '/users/17/edit'; Check "super admin sees branch picker" ($r.body -match 'name="branches\[\]"')
$r = Get 'sa' '/audit'; Check "super admin opens audit log" ($r.url -like '*/audit' -and (Clean $r))
$r = Get 'sa' '/settings'; Check "super admin opens settings" ($r.url -like '*/settings' -and (Clean $r))
$r = Get 'sa' '/institutes/create'; Check "super admin can open create-branch" ($r.url -like '*/institutes/create' -and (Clean $r))
$r = Get 'sa' '/inquiries'; Check "super admin opens inquiries" ($r.url -like '*/inquiries' -and (Clean $r))
$r = Get 'sa' '/payments/slips'; Check "super admin opens payment slip queue" ((Clean $r) -and $r.code -eq 200)
$r = Get 'sa' '/reports/payments'; Check "super admin payment report shows all centres" ((Clean $r) -and $r.body -match 'Galle' -and $r.body -match 'Kandy')
$r = Get 'sa' '/dashboard'; Check "super admin dashboard renders cleanly" ((Clean $r) -and $r.code -eq 200)

# ---------------- student and parent ----------------
"=== student STU20260200 / parent 0720000000 ==="
Check "student logs in" (Login 'st' 'STU20260200')
foreach ($p in '/students', '/payments', '/users', '/inquiries', '/reports/payments', '/audit', "/classes/$($cls[3])/materials") { $r = Get 'st' $p; Check "student refused $p" (Refused $r $p) }
$other = Q "SELECT student_id_display FROM students WHERE id<>11 AND id=$($stu[4]);"
$r = Get 'st' '/my/progress'; Check "student progress shows no other student's ID" ($r.body -notmatch [regex]::Escape($other))
Check "parent logs in" (Login 'pa' '0720000000')
$childIds = @(QA "SELECT s.student_id_display FROM students s JOIN parents p ON p.id=s.parent_id WHERE p.phone1='0720000000' OR p.phone2='0720000000';")
$r = Get 'pa' '/family'; Check "parent overview renders" ((Clean $r) -and $r.code -eq 200)
$strangers = @(QA "SELECT student_id_display FROM students WHERE parent_id IS NULL OR parent_id NOT IN (SELECT id FROM parents WHERE phone1='0720000000' OR phone2='0720000000') LIMIT 30;")
$leak = @($strangers | ? { $r.body -match [regex]::Escape($_) })
Check "parent overview shows no other family's child" ($leak.Count -eq 0)
foreach ($p in '/students', '/my/classes', '/payments', '/users', '/inquiries') { $x = Get 'pa' $p; Check "parent refused $p" (Refused $x $p) }
$otherThread = Q "SELECT id FROM help_threads WHERE student_id<>11 LIMIT 1;"
if ($otherThread -ne '') { $x = Get 'pa' "/family/help/$otherThread"; Check "parent cannot open another family's help thread" (Refused $x "/family/help/$otherThread") }

""; "$($script:n - $script:fails) of $($script:n) passed"
