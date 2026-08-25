param([string]$OutputDirectory = ".\backups")
$ErrorActionPreference = "Stop"
$resolvedProject = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$target = [System.IO.Path]::GetFullPath((Join-Path $resolvedProject $OutputDirectory))
if (-not $target.StartsWith($resolvedProject, [System.StringComparison]::OrdinalIgnoreCase)) { throw "Backup target must remain inside the project directory." }
New-Item -ItemType Directory -Force -Path $target | Out-Null
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$file = Join-Path $target "ikira-$stamp.sql"
docker compose exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' | Set-Content -Encoding utf8 $file
if ($LASTEXITCODE -ne 0) { throw "Database backup failed." }
Write-Host "Backup written to $file"
