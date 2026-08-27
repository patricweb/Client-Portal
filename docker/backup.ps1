param([string]$OutputDirectory = ".\backups")
$ErrorActionPreference = "Stop"
$resolvedProject = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$target = [System.IO.Path]::GetFullPath((Join-Path $resolvedProject $OutputDirectory))
if (-not $target.StartsWith($resolvedProject, [System.StringComparison]::OrdinalIgnoreCase)) { throw "Backup target must remain inside the project directory." }
New-Item -ItemType Directory -Force -Path $target | Out-Null
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$work = Join-Path $target ".ikira-backup-$stamp"
$archive = Join-Path $target "ikira-full-$stamp.zip"
New-Item -ItemType Directory -Force -Path $work | Out-Null

try {
    $databaseFile = Join-Path $work "database.sql"
    docker compose exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' | Set-Content -Encoding utf8 $databaseFile
    if ($LASTEXITCODE -ne 0) { throw "Database backup failed." }

    $storageDirectory = Join-Path $work "storage-app"
    New-Item -ItemType Directory -Force -Path $storageDirectory | Out-Null
    docker compose cp app:/var/www/html/storage/app/. $storageDirectory
    if ($LASTEXITCODE -ne 0) { throw "Private file backup failed." }

    $revision = git -C $resolvedProject rev-parse HEAD
    @{
        created_at = (Get-Date).ToUniversalTime().ToString("o")
        git_revision = $revision
        includes = @("database.sql", "storage-app")
    } | ConvertTo-Json | Set-Content -Encoding utf8 (Join-Path $work "manifest.json")

    Compress-Archive -LiteralPath (Join-Path $work "database.sql"), (Join-Path $work "manifest.json"), $storageDirectory -DestinationPath $archive -CompressionLevel Optimal
    Write-Host "Full backup written to $archive"
}
finally {
    $resolvedWork = [System.IO.Path]::GetFullPath($work)
    if ($resolvedWork.StartsWith($target, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolvedWork)) {
        Remove-Item -LiteralPath $resolvedWork -Recurse -Force
    }
}
