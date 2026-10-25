$mysql = "C:\xampp\mysql\bin\mysql.exe"; $php = "C:\xampp\php\php.exe"; $rc = "C:\xampp\htdocs\lms_rc"
& $mysql -u root -e "DROP DATABASE IF EXISTS student_lms_fresh; CREATE DATABASE student_lms_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$envOrig = [IO.File]::ReadAllText("$rc\.env")
[IO.File]::WriteAllText("$rc\.env", ($envOrig -replace '(?m)^DB_NAME=.*$', 'DB_NAME=student_lms_fresh'))
"--- migrate on an empty database"
& $php "$rc\bin\migrate.php" 2>&1
"--- second run"
& $php "$rc\bin\migrate.php" 2>&1 | Select-Object -Last 1
"--- tables: " + (& $mysql -u root -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='student_lms_fresh';")
"--- create_admin"
$out = & $php "$rc\bin\create_admin.php" --name="Fresh Test Admin" 2>&1
($out | Select-String 'created|Login ID') -replace '\s+', ' '
$id = ([regex]::Match(($out -join "`n"), 'Login ID:\s+(\S+)')).Groups[1].Value
$pw = ([regex]::Match(($out -join "`n"), 'Temporary password:\s+(\S+)')).Groups[1].Value
"--- login with the printed credentials"
$jar = "C:\lms_rc_data\jars\fresh.jar"; Remove-Item $jar -ea 0
$t = [regex]::Match(((curl.exe -s -b $jar -c $jar "http://localhost/lms_rc/login") -join "`n"), 'name="_csrf_token" value="([^"]+)"').Groups[1].Value
$null = curl.exe -s -o NUL -b $jar -c $jar --data-urlencode "_csrf_token=$t" --data-urlencode "user_id=$id" --data-urlencode "password=$pw" "http://localhost/lms_rc/login"
"after login lands on: " + (curl.exe -s -o NUL -w "%{url_effective}" -L -b $jar -c $jar "http://localhost/lms_rc/dashboard")
"--- accounts audit"
& $php "$rc\bin\accounts_audit.php" 2>&1
"--- public pages on an empty database"
foreach ($p in '/', '/timetable', '/login', '/reset-password') { "{0,-16} {1}" -f $p, (curl.exe -s -o NUL -w "%{http_code}" "http://localhost/lms_rc$p") }
[IO.File]::WriteAllText("$rc\.env", $envOrig)
& $mysql -u root -e "DROP DATABASE IF EXISTS student_lms_fresh;"
"--- private files over the web (live app)"
foreach ($p in '/.env', '/.env.example', '/storage/uploads/.htaccess', '/database/migrations/001_initial_schema.sql', '/bin/migrate.php', '/config/app.php', '/composer.json', '/.git/HEAD', '/public/../.env') { "{0,-46} {1}" -f $p, (curl.exe -s -o NUL -w "%{http_code}" "http://localhost/student-management-lms$p") }
