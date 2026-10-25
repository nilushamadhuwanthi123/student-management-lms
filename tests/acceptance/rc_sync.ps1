# Mirror the working repo into the isolated release-candidate copy (C:\xampp\htdocs\lms_rc).
# Never touches the real repo, the real .env or the real database.
$src = "C:\xampp\htdocs\student-management-lms"
$dst = "C:\xampp\htdocs\lms_rc"
$null = robocopy $src $dst /MIR /NFL /NDL /NJH /NJS /NP /XD "$src\.git" "$src\storage\uploads\attachments" "$src\storage\uploads\materials" "$src\storage\logs" /XF ".env"
foreach ($d in "$dst\storage\uploads\attachments", "$dst\storage\uploads\materials", "$dst\storage\logs") { New-Item -ItemType Directory -Force -Path $d | Out-Null }
$h = "$dst\.htaccess";        [IO.File]::WriteAllText($h, ([IO.File]::ReadAllText($h) -replace '/student-management-lms/', '/lms_rc/'))
$h = "$dst\public\.htaccess"; [IO.File]::WriteAllText($h, ([IO.File]::ReadAllText($h) -replace '/student-management-lms/', '/lms_rc/'))
$envText = [IO.File]::ReadAllText("$src\.env") -replace '(?m)^DB_NAME=.*$', 'DB_NAME=student_lms_rc' -replace '(?m)^APP_URL=.*$', 'APP_URL=http://localhost/lms_rc'
[IO.File]::WriteAllText("$dst\.env", $envText)
"synced"
