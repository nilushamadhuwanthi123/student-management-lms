# Topics, past papers, class register + absence alerts, learning gaps -- isolated copy only.
$base = "http://localhost/lms_rc"; $mysql = "C:\xampp\mysql\bin\mysql.exe"; $db = "student_lms_rc"
$tmp = "C:\lms_rc_data\jars"; New-Item -ItemType Directory -Force -Path $tmp | Out-Null
function Q($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function QA($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return @() } return @($o | % { ([string]$_).Trim() } | ? { $_ -ne '' }) }
function X($sql) { & $mysql -u root -e "USE $db; $sql" 2>&1 | Out-Null }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-72} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "l_$w.jar" }
function Get($w, $p) { $out = Join-Path $tmp "l_body.bin"; $c = curl.exe -s -o $out -w "%{http_code}|%{url_effective}" -L -b (Jar $w) -c (Jar $w) "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) } }
function Tok($w, $p) { [regex]::Match((Get $w $p).body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value }
function Post($w, $p, $fields, $from) {
  $t = Tok $w $from; $out = Join-Path $tmp "l_body.bin"
  $a = @('-s','-o',$out,'-w','%{http_code}|%{url_effective}','-L','-b',(Jar $w),'-c',(Jar $w),'--data-urlencode',"_csrf_token=$t")
  foreach ($k in $fields.Keys) { foreach ($v in @($fields[$k])) { $a += '--data-urlencode'; $a += "$k=$v" } }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|'); return @{ code = [int]$x[0]; url = $x[1]; body = [IO.File]::ReadAllText($out) }
}
function Login($w, $id) { Remove-Item (Jar $w) -ea 0; $null = Post $w '/login' @{ user_id = $id; password = 'Demo@12345' } '/login'; return ((Get $w '/dashboard').url -notlike '*/login') }
function Clean($r) { $r.body -notmatch 'Fatal error|Parse error|Warning:</b>|Undefined variable|Undefined array key|SQLSTATE' }
X "DELETE FROM login_attempts;"

# Fixture class: the class of STU20260200 (id 11) and its grade.
$C = [int](Q "SELECT class_id FROM enrollments WHERE student_id=11 AND enrollment_status='active' LIMIT 1;")
$G = [int]((Q "SELECT grade FROM classes WHERE id=$C;") -replace '\D', '')
$kids = @(QA "SELECT e.student_id FROM enrollments e JOIN students s ON s.id=e.student_id WHERE e.class_id=$C AND e.enrollment_status='active' AND s.status='active' ORDER BY e.student_id LIMIT 3;")
"fixture: class $C, grade $G, students $($kids -join ',')"
Check "fixture: a class with three active students" ($kids.Count -eq 3)

"=== topics ==="
Check "teacher logs in" (Login 't' 'TCH20260100')
$r = Get 't' "/topics?grade=$G"; Check "topics page renders" ($r.code -eq 200 -and (Clean $r))
$null = Post 't' '/topics' @{ grade = "$G"; subject = 'Science'; unit_no = '91'; title = 'RCTEST Unit A'; title_si = 'RCTEST ඒකකය A' } "/topics?grade=$G"
$null = Post 't' '/topics' @{ grade = "$G"; subject = 'Science'; unit_no = '92'; title = 'RCTEST Unit B' } "/topics?grade=$G"
$A = Q "SELECT id FROM topics WHERE title='RCTEST Unit A';"; $B = Q "SELECT id FROM topics WHERE title='RCTEST Unit B';"
Check "teacher adds two units" ($A -ne '' -and $B -ne '')
$null = Post 't' '/topics' @{ grade = "$G"; subject = 'Science'; title = 'RCTEST Unit A' } "/topics?grade=$G"
Check "duplicate unit name refused" ((Q "SELECT COUNT(*) FROM topics WHERE title='RCTEST Unit A';") -eq '1')
$otherG = if ($G -eq 6) { 7 } else { 6 }
$null = Post 't' '/topics' @{ grade = "$otherG"; subject = 'Science'; title = 'RCTEST Other Grade' } "/topics?grade=$otherG"
$O = Q "SELECT id FROM topics WHERE title='RCTEST Other Grade';"
$r = Get 't' "/classes/$C/materials/create"
Check "material form offers this grade's units only" ($r.body -match 'RCTEST Unit A' -and $r.body -notmatch 'RCTEST Other Grade')
$null = Post 't' "/classes/$C/materials/create" @{ title = 'RCTEST tagged note'; material_type = 'link'; external_url = 'https://example.com/rctest'; month_year = (Get-Date -Format 'yyyy-MM'); status = 'draft'; topic_id = $A } "/classes/$C/materials/create"
Check "material saved with its unit" ((Q "SELECT topic_id FROM class_materials WHERE title='RCTEST tagged note';") -eq $A)
$null = Post 't' "/classes/$C/materials/create" @{ title = 'RCTEST wrong grade'; material_type = 'link'; external_url = 'https://example.com/rctest2'; month_year = (Get-Date -Format 'yyyy-MM'); status = 'draft'; topic_id = $O } "/classes/$C/materials/create"
Check "a unit from another grade is not accepted" ((Q "SELECT IFNULL(topic_id,'none') FROM class_materials WHERE title='RCTEST wrong grade';") -eq 'none')
Check "student logs in" (Login 's' 'STU20260200')
$r = Get 's' '/topics'; Check "student cannot open the topics page" ($r.url -notlike '*/topics*')
Check "coordinator logs in" (Login 'c' 'CRD20260101')
$cnt = Q "SELECT COUNT(*) FROM topics;"; $null = Post 'c' '/topics' @{ grade = "$G"; subject = 'Science'; title = 'RCTEST by coordinator' } '/dashboard'
Check "coordinator cannot add units" ((Q "SELECT COUNT(*) FROM topics;") -eq $cnt)

"=== learning gaps ==="
X "INSERT INTO quizzes (class_id,title,opens_at,closes_at,total_marks,month_year,quiz_status,topic_id) VALUES ($C,'RCTEST gap quiz',NOW()-INTERVAL 3 DAY,NOW()-INTERVAL 1 DAY,20,DATE_FORMAT(CURDATE(),'%Y-%m-01'),'closed',$A);"
$Z = Q "SELECT id FROM quizzes WHERE title='RCTEST gap quiz';"
X "INSERT INTO quiz_questions (quiz_id,question_number,question_text,question_type,marks,option_a,option_b,correct_option,topic_id) VALUES ($Z,1,'RCTEST q1','mcq',10,'x','y','a',NULL),($Z,2,'RCTEST q2 hardest','mcq',10,'x','y','a',$B);"
$q1 = Q "SELECT id FROM quiz_questions WHERE quiz_id=$Z AND question_number=1;"; $q2 = Q "SELECT id FROM quiz_questions WHERE quiz_id=$Z AND question_number=2;"
$marks = @(@(10, 0), @(10, 5), @($null, 0))
for ($i = 0; $i -lt 3; $i++) {
  $sid = $kids[$i]
  X "INSERT INTO quiz_attempts (quiz_id,student_id,attempt_number,started_at,submitted_at,attempt_status) VALUES ($Z,$sid,1,NOW()-INTERVAL 2 DAY,NOW()-INTERVAL 2 DAY,'submitted');"
  $att = Q "SELECT id FROM quiz_attempts WHERE quiz_id=$Z AND student_id=$sid;"
  if ($null -ne $marks[$i][0]) { X "INSERT INTO quiz_answers (attempt_id,question_id,selected_option,is_correct,marks_awarded) VALUES ($att,$q1,'a',$(if ($marks[$i][0] -ge 10) {1} else {0}),$($marks[$i][0]));" }
  X "INSERT INTO quiz_answers (attempt_id,question_id,selected_option,is_correct,marks_awarded) VALUES ($att,$q2,'b',$(if ($marks[$i][1] -ge 10) {1} else {0}),$($marks[$i][1]));"
}
$null = Login 't' 'TCH20260100'
$r = Get 't' "/classes/$C/learning-gaps"
Check "learning-gaps page renders for the class teacher" ($r.code -eq 200 -and (Clean $r) -and $r.url -like '*/learning-gaps')
$rowB = [regex]::Match($r.body, 'RCTEST Unit B[\s\S]{0,400}?(\d+)%'); $rowA = [regex]::Match($r.body, 'RCTEST Unit A[\s\S]{0,400}?(\d+)%')
Check "unit B average = 5 of 30 marks = 17%" ($rowB.Success -and $rowB.Groups[1].Value -eq '17')
Check "unit A average = 20 of 30 marks = 67% (unanswered counts as zero)" ($rowA.Success -and $rowA.Groups[1].Value -eq '67')
Check "hardest unit listed first" ($r.body.IndexOf('RCTEST Unit B') -lt $r.body.IndexOf('RCTEST Unit A'))
Check "question 2 listed as most often wrong (0 of 3 full marks)" ($r.body -match 'RCTEST q2 hardest' -and $r.body -match '0 of 3')
$enrolledN = [int](Q "SELECT COUNT(*) FROM enrollments e JOIN students s ON s.id=e.student_id WHERE e.class_id=$C AND e.enrollment_status='active' AND s.status='active';")
Check "students who did not sit the quiz counted ($($enrolledN - 3))" ($r.body -match ("RCTEST gap quiz</a> &mdash; " + ($enrolledN - 3)))
Check "Galle admin logs in" (Login 'g' 'ADM20260102')
$r = Get 'g' "/classes/$C/learning-gaps"; Check "another branch's admin is refused" ($r.url -notlike '*/learning-gaps' -or $r.code -eq 403)
$null = Login 's' 'STU20260200'
$r = Get 's' "/classes/$C/learning-gaps"; Check "a student is refused" ($r.url -notlike '*/learning-gaps')

"=== past papers ==="
$null = Login 't' 'TCH20260100'
$marker = "RCPAPER-" + [guid]::NewGuid().ToString('N'); $amarker = "RCANS-" + [guid]::NewGuid().ToString('N')
$pf = Join-Path $tmp 'paper.pdf'; $af = Join-Path $tmp 'answers.pdf'
[IO.File]::WriteAllText($pf, "%PDF-1.4`n$marker`n%%EOF"); [IO.File]::WriteAllText($af, "%PDF-1.4`n$amarker`n%%EOF")
$t = Tok 't' "/past-papers/create?grade=$G"
$null = curl.exe -s -o NUL -b (Jar 't') -c (Jar 't') -F "_csrf_token=$t" -F "grade=$G" -F "year=2025" -F "paper_type=term2" -F "medium=sinhala" -F "title=RCTEST paper" -F "topic_id=$A" -F "action=publish" -F "paper_file=@$pf;type=application/pdf" -F "answers_file=@$af;type=application/pdf" "$base/past-papers"
$P = Q "SELECT id FROM past_papers WHERE title='RCTEST paper';"
Check "teacher publishes a paper with a marking scheme" ($P -ne '' -and (Q "SELECT CONCAT(status,show_answers,topic_id) FROM past_papers WHERE id=$P;") -eq "published0$A")
$t = Tok 't' "/past-papers/create?grade=$G"
$null = curl.exe -s -o NUL -b (Jar 't') -c (Jar 't') -F "_csrf_token=$t" -F "grade=$G" -F "year=2024" -F "paper_type=term1" -F "medium=sinhala" -F "title=RCTEST draft paper" -F "action=draft" -F "paper_file=@$pf;type=application/pdf" "$base/past-papers"
$D = Q "SELECT id FROM past_papers WHERE title='RCTEST draft paper';"
$fake = Join-Path $tmp 'fake.pdf'; [IO.File]::WriteAllText($fake, "MZ not a pdf")
$t = Tok 't' "/past-papers/create?grade=$G"; $cntP = Q "SELECT COUNT(*) FROM past_papers;"
$null = curl.exe -s -o NUL -b (Jar 't') -c (Jar 't') -F "_csrf_token=$t" -F "grade=$G" -F "year=2024" -F "paper_type=term1" -F "medium=sinhala" -F "title=RCTEST fake" -F "action=publish" -F "paper_file=@$fake;type=application/pdf" "$base/past-papers"
Check "a file that is not really a PDF is refused" ((Q "SELECT COUNT(*) FROM past_papers;") -eq $cntP)
$null = Login 's' 'STU20260200'
$r = Get 's' '/my/papers'
Check "student sees the published paper for their grade" ((Clean $r) -and $r.body -match 'RCTEST paper' -and $r.body -notmatch 'RCTEST draft paper')
$r = Get 's' "/past-papers/$P/file/paper"; Check "paid student downloads the paper" ($r.body -match $marker)
$r = Get 's' "/past-papers/$P/file/answers"; Check "marking scheme refused until released" ($r.body -notmatch $amarker -and $r.code -eq 403)
$r = Get 's' "/past-papers/$D/file/paper"; Check "draft paper cannot be downloaded" ($r.body -notmatch $marker)
$null = Post 't' "/past-papers/$P/status" @{ to = 'answers_on' } '/past-papers'
$r = Get 's' "/past-papers/$P/file/answers"; Check "released marking scheme opens" ($r.body -match $amarker)
$month = (Get-Date -Format 'yyyy-MM') + '-01'
$payRows = @(QA "SELECT CONCAT(p.id,'|',p.payment_status,'|',p.amount_paid,'|',IFNULL(p.paid_at,'')) FROM payments p JOIN classes c ON c.id=p.class_id WHERE p.student_id=11 AND p.month_year='$month';")
X "UPDATE payments SET payment_status='pending', amount_paid=0 WHERE student_id=11 AND month_year='$month';"
$r = Get 's' "/past-papers/$P/file/paper"; Check "unpaid month: download refused" ($r.body -notmatch $marker)
$r = Get 's' '/my/papers'; Check "unpaid month: paper shown as locked" ($r.body -match 'RCTEST paper' -and $r.body -notmatch "/past-papers/$P/file/paper")
foreach ($row in $payRows) { $o = $row.Split('|'); $pa = if ($o[3] -eq '') { 'NULL' } else { "'$($o[3])'" }; X "UPDATE payments SET payment_status='$($o[1])', amount_paid=$($o[2]), paid_at=$pa WHERE id=$($o[0]);" }
$other = Q "SELECT s.student_id_display FROM students s JOIN enrollments e ON e.student_id=s.id JOIN classes c ON c.id=e.class_id WHERE c.subject='Science' AND c.grade<>'Grade $G' AND e.enrollment_status='active' AND s.status='active' AND NOT EXISTS (SELECT 1 FROM enrollments e2 JOIN classes c2 ON c2.id=e2.class_id WHERE e2.student_id=s.id AND c2.grade='Grade $G') LIMIT 1;"
if ($other -ne '') { $null = Login 'o' $other; $r = Get 'o' "/past-papers/$P/file/paper"; Check "student of another grade cannot download it" ($r.body -notmatch $marker) }
$null = Login 'p' '0720000000'; $r = Get 'p' "/past-papers/$P/file/paper"; Check "parent account cannot download papers" ($r.body -notmatch $marker)

"=== class register and absence alerts ==="
$today = Get-Date -Format 'yyyy-MM-dd'; $old = (Get-Date).AddDays(-5).ToString('yyyy-MM-dd')
$attBefore = [int](Q "SELECT IFNULL(MAX(id),0) FROM attendance;"); $waBefore = [int](Q "SELECT IFNULL(MAX(id),0) FROM whatsapp_messages;"); $nBefore = [int](Q "SELECT IFNULL(MAX(id),0) FROM notifications;")
X "DELETE FROM attendance WHERE class_id=$C AND date IN ('$today','$old') AND student_id IN ($($kids -join ','));"
$null = Login 't' 'TCH20260100'
$r = Get 't' "/attendance/register?class_id=$C&date=$today"; Check "register lists the class's students" ((Clean $r) -and ($r.body -split 'name="status\[').Count -1 -ge 3)
$s1, $s2, $s3 = $kids
$fields = @{ class_id = "$C"; date = $today; "status[$s1]" = 'present'; "status[$s2]" = 'absent'; "status[$s3]" = 'late'; "status[999999]" = 'absent' }
$r = Post 't' '/attendance/register' $fields "/attendance/register?class_id=$C&date=$today"
Check "register saved: three marks" ((Q "SELECT COUNT(*) FROM attendance WHERE class_id=$C AND date='$today' AND student_id IN ($s1,$s2,$s3);") -eq '3')
Check "a student not in the class is ignored" ((Q "SELECT COUNT(*) FROM attendance WHERE student_id=999999;") -eq '0')
Check "one absence alert recorded" ((Q "SELECT COUNT(*) FROM absence_alerts WHERE student_id=$s2 AND class_id=$C AND date='$today';") -eq '1')
$parents = [int](Q "SELECT COUNT(*) FROM parents WHERE student_id=$s2 AND status='active';")
Check "family notified in the portal ($parents parent(s) + student)" ([int](Q "SELECT COUNT(*) FROM notifications WHERE id>$nBefore AND related_entity_type='absence';") -eq ($parents + 1))
Check "a WhatsApp message prepared (queued, not sent)" ((Q "SELECT COUNT(*) FROM whatsapp_messages WHERE id>$waBefore AND template_key='absence_alert' AND send_status='queued' AND student_id=$s2;") -eq '1' -or (Q "SELECT COUNT(*) FROM students s LEFT JOIN parents p ON p.student_id=s.id WHERE s.id=$s2 AND COALESCE(p.whatsapp,p.phone1,s.whatsapp,s.phone1) IS NOT NULL;") -eq '0')
$r = Post 't' '/attendance/register' $fields "/attendance/register?class_id=$C&date=$today"
Check "saving again does not alert twice" ((Q "SELECT COUNT(*) FROM absence_alerts WHERE student_id=$s2 AND date='$today';") -eq '1' -and [int](Q "SELECT COUNT(*) FROM notifications WHERE id>$nBefore AND related_entity_type='absence';") -eq ($parents + 1))
Check "saving again does not duplicate attendance rows" ((Q "SELECT COUNT(*) FROM attendance WHERE class_id=$C AND date='$today' AND student_id IN ($s1,$s2,$s3);") -eq '3')
$fields2 = @{ class_id = "$C"; date = $today; "status[$s2]" = 'present' }
$null = Post 't' '/attendance/register' $fields2 "/attendance/register?class_id=$C&date=$today"
Check "a correction updates the same row" ((Q "SELECT status FROM attendance WHERE class_id=$C AND date='$today' AND student_id=$s2;") -eq 'present')
$null = Post 't' '/attendance/register' @{ class_id = "$C"; date = $old; "status[$s1]" = 'absent' } "/attendance/register?class_id=$C&date=$old"
Check "an older date is saved but sends no alert" ((Q "SELECT COUNT(*) FROM attendance WHERE class_id=$C AND date='$old' AND student_id=$s1;") -eq '1' -and (Q "SELECT COUNT(*) FROM absence_alerts WHERE date='$old';") -eq '0')
$future = (Get-Date).AddDays(2).ToString('yyyy-MM-dd'); $null = Post 't' '/attendance/register' @{ class_id = "$C"; date = $future; "status[$s1]" = 'absent' } "/attendance/register?class_id=$C"
Check "a future date is refused" ((Q "SELECT COUNT(*) FROM attendance WHERE date='$future';") -eq '0')
$null = Login 'g' 'ADM20260102'
$null = Post 'g' '/attendance/register' @{ class_id = "$C"; date = $today; "status[$s1]" = 'absent' } '/attendance/register'
Check "another branch's admin cannot mark this class" ((Q "SELECT status FROM attendance WHERE class_id=$C AND date='$today' AND student_id=$s1;") -eq 'present')
$null = Login 'pa' '0720000000'; $r = Get 'pa' '/dashboard'
Check "parent portal still renders" ((Clean $r) -and $r.code -eq 200)

"=== clean up ==="
X "DELETE FROM notifications WHERE id>$nBefore AND related_entity_type='absence'; DELETE FROM whatsapp_messages WHERE id>$waBefore AND template_key='absence_alert'; DELETE FROM absence_alerts WHERE class_id=$C AND date IN ('$today','$old'); DELETE FROM attendance WHERE id>$attBefore;"
X "DELETE ans FROM quiz_answers ans JOIN quiz_attempts a ON a.id=ans.attempt_id WHERE a.quiz_id=$Z; DELETE FROM quiz_attempts WHERE quiz_id=$Z; DELETE FROM quiz_questions WHERE quiz_id=$Z; DELETE FROM quizzes WHERE id=$Z;"
X "DELETE FROM class_materials WHERE title LIKE 'RCTEST%'; DELETE FROM past_papers WHERE title LIKE 'RCTEST%'; DELETE FROM topics WHERE title LIKE 'RCTEST%';"
Check "test rows removed" ((Q "SELECT (SELECT COUNT(*) FROM topics WHERE title LIKE 'RCTEST%')+(SELECT COUNT(*) FROM past_papers WHERE title LIKE 'RCTEST%')+(SELECT COUNT(*) FROM quizzes WHERE title LIKE 'RCTEST%');") -eq '0')
""; "$($script:n - $script:fails) of $($script:n) passed"
