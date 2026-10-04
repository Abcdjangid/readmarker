# Build a production ZIP directly from an allowlist. No staging copy or dependencies.
$ErrorActionPreference = 'Stop'
$pluginRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$outputDirectory = Join-Path $pluginRoot '.release'
[IO.Directory]::CreateDirectory($outputDirectory) | Out-Null
$zipPath = Join-Path $outputDirectory 'readflow.zip'
$files = @('readflow.php', 'readme.txt', 'uninstall.php') | ForEach-Object { Get-Item -LiteralPath (Join-Path $pluginRoot $_) }
foreach ($folder in @('admin', 'includes', 'assets', 'blocks', 'languages')) {
    $files += Get-ChildItem -LiteralPath (Join-Path $pluginRoot $folder) -Recurse -File |
        Where-Object { $_.Extension -in @('.php', '.js', '.css', '.json', '.mo', '.po', '.pot') -and $_.Name -notmatch '^\.' }
}
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$stream = [IO.File]::Open($zipPath, [IO.FileMode]::Create)
try {
    $zip = New-Object IO.Compression.ZipArchive($stream, [IO.Compression.ZipArchiveMode]::Create, $true)
    try {
        $zip.CreateEntry('readflow/languages/') | Out-Null
        foreach ($file in $files | Sort-Object FullName) {
            $relative = $file.FullName.Substring($pluginRoot.Length + 1).Replace('\', '/')
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, ('readflow/' + $relative)) | Out-Null
        }
    } finally { $zip.Dispose() }
} finally { $stream.Dispose() }
Write-Output $zipPath
