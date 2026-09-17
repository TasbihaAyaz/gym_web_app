# Re-export Fit Generation (app zip + SQL + INSTALL)
# Usage:
#   powershell -ExecutionPolicy Bypass -File c:\xampp\htdocs\fit-generation\scripts\export-package.ps1

$ErrorActionPreference = 'Stop'
$projectRoot = 'c:\xampp\htdocs\fit-generation'
$stamp = Get-Date -Format 'yyyyMMdd-HHmm'
$exportsRoot = 'c:\xampp\htdocs\exports'
$exportRoot = Join-Path $exportsRoot "fit-generation-$stamp"
$stage = Join-Path $exportRoot 'fit-generation'
New-Item -ItemType Directory -Force -Path $stage | Out-Null

Write-Host 'Dumping database...'
$sql = Join-Path $exportRoot 'fit_generation.sql'
& 'C:\xampp\mysql\bin\mysqldump.exe' --user=root --host=127.0.0.1 --port=3306 --single-transaction --routines --triggers --result-file=$sql --databases fit_generation

Write-Host 'Copying application files...'
& robocopy $projectRoot $stage /E /NFL /NDL /NJH /NJS /nc /ns /np /XD .git node_modules _ui_prototype /XF .env .env.backup *.log | Out-Null

@('storage\logs', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views') | ForEach-Object {
    $p = Join-Path $stage $_
    if (Test-Path $p) {
        Get-ChildItem $p -Force -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -ne '.gitignore' } |
            Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
    }
}

@'
Fit Generation — Install / Move to another PC (XAMPP)
====================================================

Package contents
----------------
- fit-generation-app.zip   Full application (includes vendor)
- fit_generation.sql       Database dump
- INSTALL.txt              This file

Requirements
------------
- XAMPP (Apache + MySQL/MariaDB + PHP 8.1+)

Install steps
-------------
1. Extract fit-generation-app.zip into C:\xampp\htdocs\
   so you have C:\xampp\htdocs\fit-generation\

2. Start Apache and MySQL in XAMPP.

3. Import database:
   C:\xampp\mysql\bin\mysql.exe -u root < fit_generation.sql
   Or use phpMyAdmin → Import → fit_generation.sql

4. Inside fit-generation, copy .env.example to .env
   Set APP_URL=http://localhost/fit-generation

5. Generate key + storage link:
   cd C:\xampp\htdocs\fit-generation
   C:\xampp\php\php.exe artisan key:generate
   C:\xampp\php\php.exe artisan storage:link

6. Open http://localhost/fit-generation
   Login: admin@fitgeneration.com / password
   Change the password after first login.

Biometric (ZKTeco)
------------------
ADMS URL example:
  http://YOUR-PC-LAN-IP/fit-generation/iclock/cdata
Keep the app open in the browser for live check-in popup + sound.
'@ | Set-Content -Path (Join-Path $exportRoot 'INSTALL.txt') -Encoding UTF8

Write-Host 'Creating zip files...'
$appZip = Join-Path $exportRoot 'fit-generation-app.zip'
if (Test-Path $appZip) { Remove-Item $appZip -Force }
Compress-Archive -Path $stage -DestinationPath $appZip -CompressionLevel Optimal

$fullZip = Join-Path $exportsRoot "FitGeneration-Export-$stamp.zip"
if (Test-Path $fullZip) { Remove-Item $fullZip -Force }
Compress-Archive -Path $sql, (Join-Path $exportRoot 'INSTALL.txt'), $appZip -DestinationPath $fullZip -CompressionLevel Optimal

Write-Host ''
Write-Host "Done."
Write-Host "  Folder: $exportRoot"
Write-Host "  Zip:    $fullZip"
