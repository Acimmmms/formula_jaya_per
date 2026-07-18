param(
    [string]$DbName = "formula_jaya_per",
    [string]$DbUser = "root",
    [string]$DbPass = "",
    [string]$BackupDir = "D:\\Semester 6\\page\\KKP\\ops\\backups",
    [string]$MySqlBin = "C:\\xampp\\mysql\\bin"
)

$ErrorActionPreference = "Stop"
if (!(Test-Path $BackupDir)) {
    New-Item -Path $BackupDir -ItemType Directory | Out-Null
}

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$file = Join-Path $BackupDir ("{0}-{1}.sql" -f $DbName, $stamp)
$mysqldump = Join-Path $MySqlBin "mysqldump.exe"

if (!(Test-Path $mysqldump)) {
    Write-Error "mysqldump tidak ditemukan di: $mysqldump"
}

if ([string]::IsNullOrEmpty($DbPass)) {
    & $mysqldump -u$DbUser --single-transaction --routines --triggers $DbName > $file
} else {
    & $mysqldump -u$DbUser -p$DbPass --single-transaction --routines --triggers $DbName > $file
}

if (!(Test-Path $file)) {
    Write-Error "Backup gagal dibuat."
}

Write-Host "Backup selesai: $file" -ForegroundColor Green
