<#
.SYNOPSIS
    Validates Obsidian wikilinks across a folder of notes.

.DESCRIPTION
    Resolves [[Note]], [[Note#Heading]], [[Note#^block]], [[#Heading]] and
    [[#^block]] against the actual files, headings, and block IDs in the folder.
    Also flags leftover web-style anchors [text](#slug), which Obsidian ignores.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools/check-links.ps1
    powershell -ExecutionPolicy Bypass -File tools/check-links.ps1 -Folder HoopFlow
    powershell -ExecutionPolicy Bypass -File tools/check-links.ps1 -Path HOOPFLOW_PROJECT.md
#>
[CmdletBinding()]
param(
    [string]$Folder,
    [string]$Path
)

$ErrorActionPreference = 'Stop'
$utf8 = New-Object System.Text.UTF8Encoding($false)

$baseDir = $PSScriptRoot
if (-not $baseDir) { $baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path }
$baseDir = Split-Path -Parent $baseDir

if (-not $Folder -and -not $Path) { $Folder = $baseDir }
if ($Folder) {
    if (-not (Test-Path -LiteralPath $Folder)) { Write-Host "FAIL  folder not found: $Folder"; exit 2 }
    $files = @(Get-ChildItem -LiteralPath $Folder -Filter *.md -File -Recurse |
        Where-Object { $_.FullName -notmatch '\\_archive\\' })
} else {
    if (-not (Test-Path -LiteralPath $Path)) { Write-Host "FAIL  file not found: $Path"; exit 2 }
    $files = @(Get-Item -LiteralPath $Path)
}
if ($files.Count -eq 0) { Write-Host "FAIL  no Markdown files found"; exit 2 }

# Index every note: headings and block IDs.
$notes = @{}
foreach ($f in $files) {
    $noteLines = [System.IO.File]::ReadAllLines($f.FullName, $utf8)
    $headings = New-Object System.Collections.Generic.HashSet[string]
    $blocks = New-Object System.Collections.Generic.HashSet[string]
    foreach ($line in $noteLines) {
        if ($line -match '^#{1,6}\s+(.+?)\s*$') { [void]$headings.Add($Matches[1]) }
        if ($line -match '\s\^([A-Za-z0-9-]+)\s*$') { [void]$blocks.Add($Matches[1]) }
    }
    $notes[$f.BaseName] = @{ Headings = $headings; Blocks = $blocks; Lines = $noteLines }
}

if ($notes.Count -ne $files.Count) {
    Write-Host "FAIL  duplicate note names - Obsidian resolves by name, so these are ambiguous:"
    $files | Group-Object BaseName | Where-Object Count -gt 1 | ForEach-Object { Write-Host "  $($_.Name)" }
    exit 2
}

$total = 0
$broken = New-Object System.Collections.Generic.List[string]
$webAnchors = New-Object System.Collections.Generic.List[string]
$tablePipes = New-Object System.Collections.Generic.List[string]

# [[note#target|alias]] - note part optional, pipe may be escaped as \| in tables.
$pattern = '\[\[(?<note>[^\]\#\|\\]*)(?:#(?<target>[^\]\|\\]+))?(?:\\?\|(?<alias>[^\]]*))?\]\]'

foreach ($name in $notes.Keys) {
    $lines = $notes[$name].Lines
    for ($i = 0; $i -lt $lines.Count; $i++) {
        foreach ($m in [regex]::Matches($lines[$i], $pattern)) {
            $note = $m.Groups['note'].Value.Trim()
            $target = $m.Groups['target'].Value.Trim()
            if (-not $note -and -not $target) { continue }   # [[|alias]] is not a link we emit
            if (-not $note) { $note = $name }                 # same-note link

            if (-not $notes.ContainsKey($note)) {
                $broken.Add("${name}.md:$($i + 1)  unknown note -> [[$note]]")
                continue
            }
            $total++
            if (-not $target) { continue }

            if ($target.StartsWith('^')) {
                if (-not $notes[$note].Blocks.Contains($target.Substring(1))) {
                    $broken.Add("${name}.md:$($i + 1)  missing block -> [[$note#$target]]")
                }
            }
            elseif (-not $notes[$note].Headings.Contains($target)) {
                $broken.Add("${name}.md:$($i + 1)  missing heading -> [[$note#$target]]")
            }
        }
        foreach ($m in [regex]::Matches($lines[$i], '\[[^\]]+\]\(#[^)]*\)')) {
            $webAnchors.Add("${name}.md:$($i + 1)  $($m.Value)")
        }
        # Inside a table row, an unescaped pipe in a wikilink alias splits the cell.
        if ($lines[$i].StartsWith('|')) {
            foreach ($m in [regex]::Matches($lines[$i], '\[\[[^\]]*\]\]')) {
                if ($m.Value -match '(?<!\\)\|') {
                    $tablePipes.Add("${name}.md:$($i + 1)  unescaped pipe -> $($m.Value)")
                }
            }
        }
    }
}

Write-Host "Notes      : $($notes.Count)"
Write-Host "Wikilinks  : $total"
Write-Host "Broken     : $($broken.Count)"
Write-Host "Web anchors: $($webAnchors.Count)  (should be 0 for Obsidian)"
Write-Host "Table pipes: $($tablePipes.Count)  (unescaped pipes break table cells)"

if ($broken.Count -gt 0) {
    Write-Host ''
    Write-Host 'Broken links:'
    $broken | ForEach-Object { Write-Host "  $_" }
}
if ($webAnchors.Count -gt 0) {
    Write-Host ''
    Write-Host 'Web-style anchors (not clickable in Obsidian):'
    $webAnchors | ForEach-Object { Write-Host "  $_" }
}
if ($tablePipes.Count -gt 0) {
    Write-Host ''
    Write-Host 'Wikilinks inside tables with unescaped pipes:'
    $tablePipes | ForEach-Object { Write-Host "  $_" }
}

# Notes nobody links to (excluding the home note) - structural check.
$inbound = @{}
foreach ($n in $notes.Keys) { $inbound[$n] = 0 }
foreach ($name in $notes.Keys) {
    foreach ($line in $notes[$name].Lines) {
        foreach ($m in [regex]::Matches($line, $pattern)) {
            $note = $m.Groups['note'].Value.Trim()
            if ($note -and $inbound.ContainsKey($note)) { $inbound[$note]++ }
        }
    }
}
$orphans = @($inbound.Keys | Where-Object { $_ -ne '00 HoopFlow' -and $inbound[$_] -eq 0 } | Sort-Object)
Write-Host "Orphans    : $($orphans.Count)  (notes with no inbound link)"
if ($orphans.Count -gt 0) { $orphans | ForEach-Object { Write-Host "  $_" } }

if ($broken.Count -gt 0 -or $webAnchors.Count -gt 0 -or $tablePipes.Count -gt 0) {
    Write-Host ''
    Write-Host 'RESULT: FAIL'
    exit 1
}

Write-Host ''
Write-Host 'RESULT: PASS'
exit 0
