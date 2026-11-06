$files = @(
    "C:\xampp\htdocs\student-management-lms\app\Services\SiteContentService.php",
    "C:\xampp\htdocs\student-management-lms\app\Services\PublicSite.php",
    "C:\xampp\htdocs\student-management-lms\app\Views\public\tuition-home.php",
    "C:\xampp\htdocs\student-management-lms\app\Views\public\about.php",
    "C:\xampp\htdocs\student-management-lms\app\Views\public\contact.php",
    "C:\xampp\htdocs\student-management-lms\app\Controllers\SettingsController.php",
    "C:\xampp\htdocs\student-management-lms\app\Views\settings\site-content.php",
    "C:\xampp\htdocs\student-management-lms\routes\web.php",
    "C:\xampp\htdocs\student-management-lms\app\Views\layouts\admin.php"
)
$fail = $false
foreach ($f in $files) {
    $out = & php -l $f 2>&1
    Write-Output "== $f =="
    Write-Output $out
    if ($LASTEXITCODE -ne 0) { $fail = $true }
}
if ($fail) { Write-Output "LINT FAILED" } else { Write-Output "LINT OK" }
