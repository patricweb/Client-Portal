param([Parameter(Mandatory=$true)][string]$BackupFile, [switch]$ConfirmRestore)
$ErrorActionPreference = "Stop"
if (-not $ConfirmRestore) { throw "Restore replaces current database data. Re-run with -ConfirmRestore." }
$resolvedProject = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$resolvedBackup = (Resolve-Path -LiteralPath $BackupFile).Path
if (-not $resolvedBackup.StartsWith($resolvedProject, [System.StringComparison]::OrdinalIgnoreCase)) { throw "Backup file must be inside the project directory." }
Get-Content -Raw -LiteralPath $resolvedBackup | docker compose exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
if ($LASTEXITCODE -ne 0) { throw "Database restore failed." }
Write-Host "Database restored from $resolvedBackup"
