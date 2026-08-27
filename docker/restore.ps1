param([Parameter(Mandatory=$true)][string]$BackupFile, [switch]$ConfirmRestore)
$ErrorActionPreference = "Stop"
if (-not $ConfirmRestore) { throw "Restore replaces current database data. Re-run with -ConfirmRestore." }
$resolvedProject = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$resolvedBackup = (Resolve-Path -LiteralPath $BackupFile).Path
if (-not $resolvedBackup.StartsWith($resolvedProject, [System.StringComparison]::OrdinalIgnoreCase)) { throw "Backup file must be inside the project directory." }
if ([System.IO.Path]::GetExtension($resolvedBackup) -ne ".zip") { throw "Use a full ikira-full-*.zip backup." }

$restoreRoot = Join-Path (Split-Path $resolvedBackup) (".ikira-restore-" + [guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Force -Path $restoreRoot | Out-Null
try {
    Expand-Archive -LiteralPath $resolvedBackup -DestinationPath $restoreRoot
    $databaseFile = Join-Path $restoreRoot "database.sql"
    $storageDirectory = Join-Path $restoreRoot "storage-app"
    if (-not (Test-Path -LiteralPath $databaseFile) -or -not (Test-Path -LiteralPath $storageDirectory)) { throw "Backup is missing database.sql or storage-app." }

    Get-Content -Raw -LiteralPath $databaseFile | docker compose exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
    if ($LASTEXITCODE -ne 0) { throw "Database restore failed." }

    docker compose exec -T app sh -c 'set -eu; test -d /var/www/html/storage/app; find /var/www/html/storage/app -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +'
    if ($LASTEXITCODE -ne 0) { throw "Could not prepare private storage for restore." }
    docker compose cp "$storageDirectory/." app:/var/www/html/storage/app/
    if ($LASTEXITCODE -ne 0) { throw "Private file restore failed." }
    docker compose exec -T app sh -c 'chown -R www-data:www-data /var/www/html/storage/app && chmod -R ug+rwX /var/www/html/storage/app'
    if ($LASTEXITCODE -ne 0) { throw "Could not restore storage permissions." }

    Write-Host "Database and private files restored from $resolvedBackup"
}
finally {
    $resolvedRestore = [System.IO.Path]::GetFullPath($restoreRoot)
    $resolvedBackupDirectory = [System.IO.Path]::GetFullPath((Split-Path $resolvedBackup))
    if ($resolvedRestore.StartsWith($resolvedBackupDirectory, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolvedRestore)) {
        Remove-Item -LiteralPath $resolvedRestore -Recurse -Force
    }
}
