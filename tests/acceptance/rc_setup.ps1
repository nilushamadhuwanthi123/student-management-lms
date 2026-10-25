# One-time: snapshot the live DB (read-only dump), load it into student_lms_rc,
# and create a TEST super admin that exists only in the copy.
$mysql = "C:\xampp\mysql\bin\mysql.exe"; $dump = "C:\xampp\mysql\bin\mysqldump.exe"
$dir = "C:\lms_rc_data"; New-Item -ItemType Directory -Force -Path $dir | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$snap = "$dir\live_snapshot_$stamp.sql"
& $dump -u root --single-transaction --routines --triggers --result-file=$snap student_lms
if ($LASTEXITCODE -ne 0) { "dump failed"; exit 1 }
& $mysql -u root -e "DROP DATABASE IF EXISTS student_lms_rc; CREATE DATABASE student_lms_rc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "`"$mysql`" -u root student_lms_rc < `"$snap`""
if ($LASTEXITCODE -ne 0) { "import failed"; exit 1 }
# random test password, kept in a local file only (never printed)
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'
$pw = -join (1..14 | % { $chars[(Get-Random -Maximum $chars.Length)] }) + '#9a'
[IO.File]::WriteAllText("$dir\test_sa_password.txt", $pw)
$hash = & C:\xampp\php\php.exe -r "echo password_hash(file_get_contents('$($dir.Replace('\','/'))/test_sa_password.txt'), PASSWORD_DEFAULT);"
& $mysql -u root -e "USE student_lms_rc; INSERT INTO users (user_id_display, role, full_name, password_hash, password_changed_at, status) VALUES ('SATEST0001','super_admin','TEST Super Admin (rc only)','$hash',NOW(),'active');"
"snapshot: $snap"
& $mysql -u root -N -e "SELECT CONCAT('rc users: ', COUNT(*)) FROM student_lms_rc.users; SELECT CONCAT('live users: ', COUNT(*)) FROM student_lms.users; SELECT CONCAT('live has SATEST: ', COUNT(*)) FROM student_lms.users WHERE user_id_display='SATEST0001';"
