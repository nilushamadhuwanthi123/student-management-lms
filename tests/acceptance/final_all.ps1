$o = "C:\lms_rc_data\final_all.txt"; "" | Set-Content $o
function Run($name, $file) { "=== $name" | Add-Content $o; & powershell -ExecutionPolicy Bypass -File $file 2>&1 | Select-String 'FAIL|passed|ALL PAGES|mismatch' | % { $_.Line } | Add-Content $o }
& C:\xampp\htdocs\_lms_checks\rc_sync.ps1 | Out-Null
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE student_lms_rc; DELETE FROM login_attempts;" 2>&1 | Out-Null
Run 'copy: learning tools' 'C:\xampp\htdocs\_lms_checks\test_learning_rc.ps1'
Run 'copy: Sinhala pages' 'C:\xampp\htdocs\_lms_checks\test_si_pages.ps1'
Run 'copy: materials' 'C:\xampp\htdocs\_lms_checks\test_materials.ps1'
Run 'copy: auth' 'C:\xampp\htdocs\_lms_checks\test_auth_rc.ps1'
Run 'copy: roles' 'C:\xampp\htdocs\_lms_checks\test_roles_rc.ps1'
"=== live suites" | Add-Content $o
& C:\xampp\htdocs\_lms_checks\run_all.ps1
Get-Content C:\lms_rc_data\all_out.txt | Select-String '===|FAIL|passed|ALL PAGES|ok$|skipped' | % { $_.Line } | Add-Content $o
"=== FINAL DONE" | Add-Content $o
