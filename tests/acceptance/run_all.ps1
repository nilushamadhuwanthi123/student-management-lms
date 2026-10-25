$o = "C:\lms_rc_data\all_out.txt"; "" | Set-Content $o
function Run($name, $cmd) { "=== $name" | Add-Content $o; try { & $cmd 2>&1 | Select-String 'FAIL|passed|ALL PAGES|ok$|skipped' | % { $_.Line } | Add-Content $o } catch { "ERROR $_" | Add-Content $o } }
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE student_lms; DELETE FROM login_attempts;" 2>&1 | Out-Null
Set-Location C:\xampp\htdocs\_lms_checks\baseline
Run 'verify_all' { powershell -ExecutionPolicy Bypass -File .\verify_all.ps1 }
Run 'test_portals' { powershell -ExecutionPolicy Bypass -File .\test_portals.ps1 }
Run 'test_grade_isolation' { powershell -ExecutionPolicy Bypass -File .\test_grade_isolation.ps1 }
Set-Location C:\xampp\htdocs\_lms_checks
Run 'test_branch_isolation (live, no SA password)' { powershell -ExecutionPolicy Bypass -File .\test_branch_isolation.ps1 }
Run 'test_features' { powershell -ExecutionPolicy Bypass -File .\test_features.ps1 }
Run 'test_public' { powershell -ExecutionPolicy Bypass -File .\test_public.ps1 }
Run 'assistant intents' { C:\xampp\php\php.exe intent_check.php }
Run 'scope cli' { C:\xampp\php\php.exe scope_cli_test.php }
"=== DONE" | Add-Content $o
