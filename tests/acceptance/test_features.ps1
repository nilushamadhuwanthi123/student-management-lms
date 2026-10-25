# End-to-end checks for homework, help desk, notices and payment slips.
# Every row it creates is removed at the end, and the one fee it touches
# is put back exactly as it was.
$base  = "http://localhost/student-management-lms"
$mysql = "C:\xampp\mysql\bin\mysql.exe"
$tmp   = Join-Path $PSScriptRoot "ft_tmp"; New-Item -ItemType Directory -Force -Path $tmp | Out-Null
function Q($sql) { $o = & $mysql -u root -N -e "USE student_lms; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-64} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($name) { Join-Path $tmp "$name.jar" }
function Get($who, $p) { $out = Join-Path $tmp "page.html"; $code = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $who) -c (Jar $who) "$base$p"; $parts = $code.Split('|'); return @{ code = [int]$parts[0]; url = $parts[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($who) { foreach ($pg in '/my/help','/family/help','/notices/create') { $r = Get $who $pg; $m = [regex]::Match($r.body, 'name="_csrf_token" value="([^"]+)"'); if ($m.Success) { return $m.Groups[1].Value } }; return '' }
function Post($who, $p, $fields) { $args = @('-s','-o','NUL','-w','%{http_code}|%{redirect_url}','-b',(Jar $who),'-c',(Jar $who)); foreach ($k in $fields.Keys) { $args += '-F'; $args += "$k=$($fields[$k])" }; $args += "$base$p"; return (& curl.exe @args) }
function Login($who, $id) {
    Remove-Item (Jar $who) -ErrorAction SilentlyContinue
    $r = Get $who '/login'; $t = [regex]::Match($r.body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value
    $null = curl.exe -s -o NUL -b (Jar $who) -c (Jar $who) --data-urlencode "_csrf_token=$t" --data-urlencode "user_id=$id" --data-urlencode "password=Demo@12345" "$base/login"
    return ((Get $who '/dashboard').url -notlike '*/login')
}
function Clean($r) { $r.body -notmatch 'Fatal error|Parse error|Warning:</b>|Undefined variable|Undefined array key|SQLSTATE' }

$startNotif = [int](Q "SELECT COALESCE(MAX(id),0) FROM notifications;")
$startAudit = [int](Q "SELECT COALESCE(MAX(id),0) FROM audit_log;")

# A real slip-shaped file: a tiny PNG and a fake (renamed text) "PNG".
[IO.File]::WriteAllBytes("$tmp\slip.png", [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))
[IO.File]::WriteAllText("$tmp\fake.png", "<?php echo 'not an image'; ?>")

Check "teacher logs in" (Login 't' 'TCH20260100')
Check "student logs in" (Login 's' 'STU20260200')
Check "Pannipitiya admin logs in" (Login 'pa' 'ADM20260101')
Check "Galle coordinator logs in" (Login 'gc' 'CRD20260102')
Check "Galle student logs in" (Login 'gs' (Q "SELECT student_id_display FROM students WHERE id=17;"))
Check "parent logs in" (Login 'p' '0720000000')

"=== HOMEWORK ==="
$tt = Tok 't'
$res = Post 't' '/classes/7/assignments/create' @{ '_csrf_token'=$tt; title='TEST HW photosynthesis'; instructions='Answer q1-3'; due_at=(Get-Date).AddDays(3).ToString('yyyy-MM-ddTHH:mm'); total_marks='10'; allow_file='1'; action='publish' }
$aid = Q "SELECT id FROM assignments WHERE title='TEST HW photosynthesis' ORDER BY id DESC LIMIT 1;"
Check "teacher publishes homework" ($aid -ne '')
$told = [int](Q "SELECT COUNT(*) FROM notifications WHERE id>$startNotif AND related_entity_type='assignment' AND related_entity_id=$aid;")
$fam = [int](Q "SELECT (SELECT COUNT(*) FROM enrollments e JOIN students s ON s.id=e.student_id AND s.status='active' WHERE e.class_id=7 AND e.enrollment_status='active') + (SELECT COUNT(*) FROM parents p JOIN enrollments e ON e.student_id=p.student_id AND e.class_id=7 AND e.enrollment_status='active' WHERE p.status='active');")
Check "class and parents notified once ($told of $fam)" ($told -eq $fam -and $told -gt 0)
$r = Get 's' '/my/assignments'; Check "student sees it" ((Clean $r) -and $r.body -match 'TEST HW photosynthesis')
$r = Get 'gs' "/my/assignments/$aid"; Check "Galle student cannot open it" ($r.body -notmatch 'Answer q1-3')
$r = Get 'pa' "/classes/7/assignments/$aid"; Check "Pannipitiya admin opens it" ((Clean $r) -and $r.body -match 'TEST HW')
$r = Get 'gc' "/classes/7/assignments"; Check "Galle coordinator refused" ($r.url -notlike '*/classes/7/assignments')
$ts = Tok 's'
$null = Post 's' "/my/assignments/$aid/submit" @{ '_csrf_token'=$ts; answer_text='Plants make food with light.'; attachment="@$tmp\fake.png;type=image/png" }
Check "renamed non-image attachment refused" ((Q "SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id=$aid;") -eq '0')
$null = Post 's' "/my/assignments/$aid/submit" @{ '_csrf_token'=$ts; answer_text='Plants make food with light.'; attachment="@$tmp\slip.png;type=image/png" }
$sub = Q "SELECT id FROM assignment_submissions WHERE assignment_id=$aid AND student_id=11;"
Check "student hands in with a photo" ($sub -ne '')
$r = Get 's' "/assignments/submissions/$sub/file"; Check "student downloads own file" ($r.code -eq 200)
$r = Get 'gs' "/assignments/submissions/$sub/file"; Check "another student cannot download it" ($r.code -eq 404)
$null = Post 't' "/classes/7/assignments/$aid/mark" @{ '_csrf_token'=$tt; submission_id=$sub; marks='11'; feedback='x' }
Check "marks above the total refused" ((Q "SELECT IFNULL(marks,'null') FROM assignment_submissions WHERE id=$sub;") -eq 'null')
$null = Post 't' "/classes/7/assignments/$aid/mark" @{ '_csrf_token'=$tt; submission_id=$sub; marks='8.5'; feedback='Good work' }
Check "teacher marks 8.5" ((Q "SELECT marks FROM assignment_submissions WHERE id=$sub;") -eq '8.50')
Check "mark change audited" ([int](Q "SELECT COUNT(*) FROM audit_log WHERE id>$startAudit AND action='mark_assignment' AND entity_id=$sub;") -eq 1)
$null = Post 's' "/my/assignments/$aid/submit" @{ '_csrf_token'=$ts; answer_text='changed after marking' }
Check "marked hand-in cannot be changed" ((Q "SELECT answer_text FROM assignment_submissions WHERE id=$sub;") -eq 'Plants make food with light.')
$r = Get 'p' '/family/assignments'; Check "parent sees mark and feedback" ((Clean $r) -and $r.body -match 'Good work' -and $r.body -match '8\.5')

"=== HELP DESK ==="
$null = Post 's' '/my/help' @{ '_csrf_token'=$ts; kind='question'; class_id='7'; subject='TEST Q why are leaves green'; body='Please explain' }
$th = Q "SELECT id FROM help_threads WHERE subject='TEST Q why are leaves green';"
Check "student asks a question" ($th -ne '')
$null = Post 's' '/my/help' @{ '_csrf_token'=$ts; kind='question'; class_id='13'; subject='TEST Q other class'; body='x' }
Check "question about a class not enrolled in refused" ((Q "SELECT COUNT(*) FROM help_threads WHERE subject='TEST Q other class';") -eq '0')
Check "teacher notified" ([int](Q "SELECT COUNT(*) FROM notifications WHERE id>$startNotif AND recipient_type='user' AND recipient_id=13 AND related_entity_id=$th AND related_entity_type='help_thread';") -ge 1)
$r = Get 't' '/help'; Check "teacher sees it in the help desk" ((Clean $r) -and $r.body -match 'TEST Q why are leaves green')
$r = Get 'gc' "/help/$th"; Check "Galle coordinator cannot open it" ($r.body -notmatch 'Please explain')
$r = Get 'gs' "/my/help/$th"; Check "another student cannot open it" ($r.body -notmatch 'Please explain')
$r = Get 'p' "/family/help/$th"; Check "parent cannot read the student's own question" ($r.body -notmatch 'Please explain')
$null = Post 't' "/help/$th/reply" @{ '_csrf_token'=$tt; body='Chlorophyll reflects green light.' }
$r = Get 's' "/my/help/$th"; Check "student sees the teacher's reply" ((Clean $r) -and $r.body -match 'Chlorophyll')
Check "status is answered" ((Q "SELECT status FROM help_threads WHERE id=$th;") -eq 'answered')
$tp = Tok 'p'
$null = Post 'p' '/family/help' @{ '_csrf_token'=$tp; subject='TEST support fee question'; body='When is the fee due?' }
$sth = Q "SELECT id FROM help_threads WHERE subject='TEST support fee question';"
Check "parent opens a support request" ($sth -ne '' -and (Q "SELECT kind FROM help_threads WHERE id=$sth;") -eq 'support')
$r = Get 'pa' '/help'; Check "Pannipitiya admin sees the support request" ($r.body -match 'TEST support fee question')
$r = Get 'gc' '/help'; Check "Galle coordinator does not" ($r.body -notmatch 'TEST support fee question')

"=== NOTICES ==="
$ta = Tok 'pa'
$null = Post 'pa' '/notices/create' @{ '_csrf_token'=$ta; title='TEST NOTICE Pannipitiya'; body='Hall closed on Poya'; audience='branch'; institute_id='3'; for_students='1'; for_parents='1'; action='publish' }
$nid = Q "SELECT id FROM notices WHERE title='TEST NOTICE Pannipitiya';"
Check "branch admin publishes a branch notice" ($nid -ne '')
$null = Post 'pa' '/notices/create' @{ '_csrf_token'=$ta; title='TEST NOTICE Galle hijack'; body='x'; audience='branch'; institute_id='4'; for_students='1'; action='publish' }
Check "posting to another branch refused" ((Q "SELECT COUNT(*) FROM notices WHERE title='TEST NOTICE Galle hijack';") -eq '0')
$null = Post 't' '/notices/create' @{ '_csrf_token'=$tt; title='TEST NOTICE teacher branch'; body='x'; audience='branch'; institute_id='3'; for_students='1'; action='publish' }
Check "teacher cannot post branch-wide" ((Q "SELECT COUNT(*) FROM notices WHERE title='TEST NOTICE teacher branch';") -eq '0')
$r = Get 's' '/my/notices'; Check "Pannipitiya student sees it" ((Clean $r) -and $r.body -match 'Hall closed on Poya')
$r = Get 'gs' '/my/notices'; Check "Galle student does not" ($r.body -notmatch 'Hall closed on Poya')
$r = Get 'p' '/family/notices'; Check "parent sees it" ($r.body -match 'Hall closed on Poya')
$null = Post 'pa' '/notices/create' @{ '_csrf_token'=$ta; title='TEST NOTICE scheduled'; body='later'; audience='branch'; institute_id='3'; for_students='1'; publish_at=(Get-Date).AddDays(2).ToString('yyyy-MM-ddTHH:mm'); action='publish' }
$r = Get 's' '/my/notices'; Check "scheduled notice hidden until its time" ($r.body -notmatch 'TEST NOTICE scheduled')
$snid = Q "SELECT id FROM notices WHERE title='TEST NOTICE scheduled';"
Check "scheduled notice not announced yet" ((Q "SELECT IFNULL(notified_at,'none') FROM notices WHERE id=$snid;") -eq 'none')

"=== PAYMENT SLIPS ==="
$pay = Q "SELECT p.id FROM payments p JOIN classes c ON c.id=p.class_id WHERE c.institute_id=3 AND p.payment_status IN ('pending','overdue') AND p.amount_paid=0 LIMIT 1;"
$payStudent = Q "SELECT student_id FROM payments WHERE id=$pay;"
& $mysql -u root -e "USE student_lms; DELETE FROM payment_slips WHERE payment_id=$pay AND status='pending' AND created_at > NOW() - INTERVAL 1 DAY;" 2>&1 | Out-Null
$before = Q "SELECT CONCAT_WS('|',amount_paid,payment_status,IFNULL(paid_at,'NULL'),IFNULL(marked_by,'NULL'),IFNULL(notes,'NULL')) FROM payments WHERE id=$pay;"
$due = Q "SELECT amount_due FROM payments WHERE id=$pay;"
Check "fixture: an unpaid Pannipitiya fee ($pay, Rs. $due)" ($pay -ne '')
$who = Q "SELECT student_id_display FROM students WHERE id=$payStudent;"
Check "that student logs in" (Login 'ps' $who)
$tps = Tok 'ps'
$null = Post 'ps' '/my/payments/slip' @{ '_csrf_token'=$tps; payment_id=$pay; amount='500'; paid_on=(Get-Date).ToString('yyyy-MM-dd'); method='bank_deposit'; reference='TESTREF001'; slip="@$tmp\slip.png;type=image/png" }
$slip1 = Q "SELECT id FROM payment_slips WHERE payment_id=$pay AND bank_reference='TESTREF001';"
Check "student uploads a slip" ($slip1 -ne '')
Check "upload alone does not change the fee" ((Q "SELECT payment_status FROM payments WHERE id=$pay;") -ne 'paid' -and (Q "SELECT amount_paid FROM payments WHERE id=$pay;") -eq '0.00')
$null = Post 'ps' '/my/payments/slip' @{ '_csrf_token'=$tps; payment_id=$pay; amount='500'; paid_on=(Get-Date).ToString('yyyy-MM-dd'); slip="@$tmp\slip.png;type=image/png" }
Check "second slip while one is pending refused" ((Q "SELECT COUNT(*) FROM payment_slips WHERE payment_id=$pay;") -eq '1')
$null = Post 's' '/my/payments/slip' @{ '_csrf_token'=$ts; payment_id=$pay; amount='500'; paid_on=(Get-Date).ToString('yyyy-MM-dd'); slip="@$tmp\slip.png;type=image/png" }
Check "another student cannot pay someone else's fee" ((Q "SELECT COUNT(*) FROM payment_slips WHERE payment_id=$pay;") -eq '1')
$r = Get 'gc' '/payments/slips'; Check "coordinator has no slip queue" ($r.url -notlike '*/payments/slips*')
Check "Galle admin logs in" (Login 'ga' 'ADM20260102')
$r = Get 'ga' '/payments/slips'; Check "Galle admin does not see the Pannipitiya slip" ($r.body -notmatch 'TESTREF001')
$tga = Tok 'ga'
$null = Post 'ga' "/payments/slips/$slip1/approve" @{ '_csrf_token'=$tga; approved_amount='500' }
Check "Galle admin cannot approve it" ((Q "SELECT status FROM payment_slips WHERE id=$slip1;") -eq 'pending')
$r = Get 'pa' '/payments/slips'; Check "Pannipitiya admin sees it in the queue" ((Clean $r) -and $r.body -match 'TESTREF001')
$null = Post 'pa' "/payments/slips/$slip1/approve" @{ '_csrf_token'=$ta; approved_amount='999999' }
Check "approving more than is due refused" ((Q "SELECT status FROM payment_slips WHERE id=$slip1;") -eq 'pending')
$null = Post 'pa' "/payments/slips/$slip1/approve" @{ '_csrf_token'=$ta; approved_amount='500' }
Check "partial approval recorded" ((Q "SELECT amount_paid FROM payments WHERE id=$pay;") -eq '500.00' -and (Q "SELECT payment_status FROM payments WHERE id=$pay;") -ne 'paid')
$null = Post 'pa' "/payments/slips/$slip1/approve" @{ '_csrf_token'=$ta; approved_amount='500' }
Check "same slip cannot be approved twice" ((Q "SELECT amount_paid FROM payments WHERE id=$pay;") -eq '500.00')
$rest = [decimal]$due - 500
$null = Post 'ps' '/my/payments/slip' @{ '_csrf_token'=$tps; payment_id=$pay; amount="$rest"; paid_on=(Get-Date).ToString('yyyy-MM-dd'); reference='TESTREF001'; slip="@$tmp\slip.png;type=image/png" }
Check "re-used bank reference refused" ((Q "SELECT COUNT(*) FROM payment_slips WHERE payment_id=$pay;") -eq '1')
$null = Post 'ps' '/my/payments/slip' @{ '_csrf_token'=$tps; payment_id=$pay; amount="$rest"; paid_on=(Get-Date).ToString('yyyy-MM-dd'); reference='TESTREF002'; slip="@$tmp\slip.png;type=image/png" }
$slip2 = Q "SELECT id FROM payment_slips WHERE bank_reference='TESTREF002';"
$null = Post 'pa' "/payments/slips/$slip2/reject" @{ '_csrf_token'=$ta; reason='' }
Check "rejection without a reason refused" ((Q "SELECT status FROM payment_slips WHERE id=$slip2;") -eq 'pending')
$null = Post 'pa' "/payments/slips/$slip2/reject" @{ '_csrf_token'=$ta; reason='Slip is blurred, please upload again' }
Check "rejection recorded with reason; fee unchanged" ((Q "SELECT status FROM payment_slips WHERE id=$slip2;") -eq 'rejected' -and (Q "SELECT amount_paid FROM payments WHERE id=$pay;") -eq '500.00')
$r = Get 'ps' '/my/payments'; Check "family sees the rejection reason" ((Clean $r) -and $r.body -match 'Slip is blurred')
$null = Post 'ps' '/my/payments/slip' @{ '_csrf_token'=$tps; payment_id=$pay; amount="$rest"; paid_on=(Get-Date).ToString('yyyy-MM-dd'); reference='TESTREF003'; slip="@$tmp\slip.png;type=image/png" }
$slip3 = Q "SELECT id FROM payment_slips WHERE bank_reference='TESTREF003';"
$null = Post 'pa' "/payments/slips/$slip3/approve" @{ '_csrf_token'=$ta; approved_amount="$rest" }
Check "balance approval settles the fee" ((Q "SELECT payment_status FROM payments WHERE id=$pay;") -eq 'paid' -and (Q "SELECT amount_paid FROM payments WHERE id=$pay;") -eq $due)
$r = Get 'ps' "/my/receipts/$pay"; Check "receipt now available" ($r.code -eq 200 -and (Clean $r) -and $r.body -match 'R-')
Check "approvals and rejection audited" ([int](Q "SELECT COUNT(*) FROM audit_log WHERE id>$startAudit AND action IN ('approve_payment_slip','reject_payment_slip');") -eq 3)

"=== CLEAN-UP ==="
$parts = $before.Split('|')
$paidAt = if ($parts[2] -eq 'NULL') { 'NULL' } else { "'$($parts[2])'" }
$markedBy = if ($parts[3] -eq 'NULL') { 'NULL' } else { $parts[3] }
$notes = if ($parts[4] -eq 'NULL') { 'NULL' } else { "'" + $parts[4].Replace("'", "''") + "'" }
& $mysql -u root -e "USE student_lms; UPDATE payments SET amount_paid=$($parts[0]), payment_status='$($parts[1])', paid_at=$paidAt, marked_by=$markedBy, notes=$notes WHERE id=$pay; DELETE FROM payment_slips WHERE payment_id=$pay AND bank_reference LIKE 'TESTREF%'; DELETE FROM assignments WHERE title LIKE 'TEST HW%'; DELETE FROM help_threads WHERE subject LIKE 'TEST %'; DELETE FROM notices WHERE title LIKE 'TEST NOTICE%'; DELETE FROM notifications WHERE id>$startNotif; DELETE FROM audit_log WHERE id>$startAudit;" 2>&1 | Out-Null
Check "fee restored exactly" ((Q "SELECT CONCAT_WS('|',amount_paid,payment_status,IFNULL(paid_at,'NULL'),IFNULL(marked_by,'NULL'),IFNULL(notes,'NULL')) FROM payments WHERE id=$pay;") -eq $before)
Check "test rows removed" ((Q "SELECT (SELECT COUNT(*) FROM assignments WHERE title LIKE 'TEST HW%')+(SELECT COUNT(*) FROM help_threads WHERE subject LIKE 'TEST %')+(SELECT COUNT(*) FROM notices WHERE title LIKE 'TEST NOTICE%')+(SELECT COUNT(*) FROM payment_slips WHERE bank_reference LIKE 'TESTREF%');") -eq '0')

""; "$($script:n - $script:fails) of $($script:n) passed"
