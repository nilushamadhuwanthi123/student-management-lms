# Regression: an unpaid student must not reach class material by any route.
# Runs ONLY against the isolated copy (lms_rc / student_lms_rc).
$base = "http://localhost/lms_rc"; $mysql = "C:\xampp\mysql\bin\mysql.exe"; $db = "student_lms_rc"
$tmp = "C:\lms_rc_data\jars"; New-Item -ItemType Directory -Force -Path $tmp | Out-Null
function Q($sql) { $o = & $mysql -u root -N -e "USE $db; $sql" 2>$null; if ($null -eq $o) { return "" } return ([string]($o | Select-Object -First 1)).Trim() }
function X($sql) { & $mysql -u root -e "USE $db; $sql" 2>&1 | Out-Null }
$script:n = 0; $script:fails = 0
function Check($l, $ok) { $script:n++; if (-not $ok) { $script:fails++ }; "{0,-66} {1}" -f $l, $(if ($ok) { "OK" } else { "FAIL" }) }
function Jar($w) { Join-Path $tmp "m_$w.jar" }
function Get($w, $p, [switch]$NoFollow) {
  $out = Join-Path $tmp "m_body.bin"; $hdr = Join-Path $tmp "m_hdr.txt"
  $a = @('-s','-o',$out,'-D',$hdr,'-w','%{http_code}|%{url_effective}|%{redirect_url}','-b',(Jar $w),'-c',(Jar $w))
  if (-not $NoFollow) { $a += '-L' }
  $c = & curl.exe @a "$base$p"; $x = $c.Split('|')
  return @{ code = [int]$x[0]; url = $x[1]; redirect = $x[2]; body = [IO.File]::ReadAllText($out); headers = [IO.File]::ReadAllText($hdr) }
}
function Login($w, $id) { Remove-Item (Jar $w) -ea 0; $t = [regex]::Match((Get $w '/login').body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value; $null = curl.exe -s -o NUL -b (Jar $w) -c (Jar $w) --data-urlencode "_csrf_token=$t" --data-urlencode "user_id=$id" --data-urlencode "password=Demo@12345" "$base/login"; return ((Get $w '/dashboard').url -notlike '*/login') }

# ---- fixture: class 7, student 11 (STU20260200), this month made UNPAID in the copy ----
$month = (Get-Date -Format 'yyyy-MM') + '-01'
$orig = Q "SELECT CONCAT(id,'|',payment_status,'|',amount_paid,'|',IFNULL(paid_at,'')) FROM payments WHERE student_id=11 AND class_id=7 AND month_year='$month';"
$pid_ = $orig.Split('|')[0]
X "UPDATE payments SET payment_status='pending', amount_paid=0, paid_at=NULL WHERE id=$pid_;"
$marker = "RCTEST-SECRET-" + [guid]::NewGuid().ToString('N')
$dir = "C:\xampp\htdocs\lms_rc\storage\uploads\materials\7"; New-Item -ItemType Directory -Force -Path $dir | Out-Null
$fname = "rctest_" + [guid]::NewGuid().ToString('N') + ".pdf"
[IO.File]::WriteAllText("$dir\$fname", "%PDF-1.4`n$marker`n%%EOF")
$secretUrl = "https://example.invalid/$marker"
X "INSERT INTO class_materials (class_id,title,material_type,file_path,original_name,month_year,status) VALUES (7,'RCTEST file note','reading','7/$fname','note.pdf','$month','published');"
$fileId = Q "SELECT MAX(id) FROM class_materials WHERE title='RCTEST file note';"
X "INSERT INTO class_materials (class_id,title,material_type,external_url,month_year,status) VALUES (7,'RCTEST recording link','recording','$secretUrl','$month','published');"
$linkId = Q "SELECT MAX(id) FROM class_materials WHERE title='RCTEST recording link';"

Check "student logs in (copy)" (Login 's' 'STU20260200')
$r = Get 's' '/my/classes/7'
Check "class page shows the lock" ($r.body -match 'is locked|Settle')
Check "class page leaks no material title" ($r.body -notmatch 'RCTEST')
Check "class page leaks no recording URL" ($r.body -notmatch [regex]::Escape($marker))
$r = Get 's' "/my/materials/$fileId/open" -NoFollow
Check "open (file) does not hand over the file" ($r.body -notmatch $marker -and $r.redirect -notmatch 'download')
$r = Get 's' "/my/materials/$linkId/open" -NoFollow
Check "open (link) does not redirect to the recording" ($r.redirect -notmatch [regex]::Escape($marker) -and $r.headers -notmatch [regex]::Escape($marker))
$r = Get 's' "/materials/$fileId/download" -NoFollow
Check "direct download URL refuses the file" ($r.body -notmatch $marker -and $r.headers -notmatch 'attachment')
$r = Get 's' "/materials/$fileId/download"
Check "direct download ends on a page, not the file" ($r.body -notmatch $marker)
$r = Get 's' "/storage/uploads/materials/7/$fname"
Check "raw storage path is not served" ($r.body -notmatch $marker)
$r = Get 's' "/public/../storage/uploads/materials/7/$fname"
Check "path-traversal URL is not served" ($r.body -notmatch $marker)
$t = [regex]::Match((Get 's' '/my/classes').body, 'name="_csrf_token" value="([^"]+)"').Groups[1].Value
$aj = curl.exe -s -b (Jar 's') -c (Jar 's') -H "X-Requested-With: XMLHttpRequest" --data-urlencode "_csrf_token=$t" --data-urlencode "question=show my latest notes" "$base/assistant/ask"
Check "assistant (AJAX) lists no locked material" (($aj -join '') -notmatch 'RCTEST')
Check "material view not recorded while locked" ((Q "SELECT COUNT(*) FROM material_views WHERE material_id IN ($fileId,$linkId);") -eq '0')

Check "parent logs in (copy)" (Login 'p' '0720000000')
$r = Get 'p' "/materials/$fileId/download" -NoFollow
Check "parent cannot download class material" ($r.body -notmatch $marker)
Check "Galle admin logs in (copy)" (Login 'g' 'ADM20260102')
$r = Get 'g' "/materials/$fileId/download" -NoFollow
Check "other-branch admin cannot download Pannipitiya material" ($r.body -notmatch $marker -and $r.code -eq 403)

# ---- pay, then it must open ----
X "UPDATE payments SET payment_status='paid', amount_paid=amount_due, paid_at=NOW() WHERE id=$pid_;"
$r = Get 's' "/my/materials/$fileId/open"
Check "after payment the file opens" ($r.body -match $marker)
$r = Get 's' "/my/materials/$linkId/open" -NoFollow
Check "after payment the recording link opens" ($r.redirect -match [regex]::Escape($marker))
Check "after payment the view is recorded" ([int](Q "SELECT COUNT(*) FROM material_views WHERE material_id IN ($fileId,$linkId);") -ge 1)

# ---- clean up: restore the fee row exactly, remove fixtures ----
$o = $orig.Split('|'); $paidAt = if ($o[3] -eq '') { 'NULL' } else { "'$($o[3])'" }
X "UPDATE payments SET payment_status='$($o[1])', amount_paid=$($o[2]), paid_at=$paidAt WHERE id=$pid_;"
X "DELETE FROM material_views WHERE material_id IN ($fileId,$linkId); DELETE FROM class_materials WHERE id IN ($fileId,$linkId);"
Remove-Item "$dir\$fname" -ea 0
Check "fixtures removed and fee restored" ((Q "SELECT COUNT(*) FROM class_materials WHERE title LIKE 'RCTEST%';") -eq '0' -and (Q "SELECT payment_status FROM payments WHERE id=$pid_;") -eq $o[1])
""; "$($script:n - $script:fails) of $($script:n) passed"
