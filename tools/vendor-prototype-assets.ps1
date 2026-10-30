$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$vendor = Join-Path $root 'prototype/assets/vendor'
New-Item -ItemType Directory -Force -Path "$vendor/bootstrap" | Out-Null
New-Item -ItemType Directory -Force -Path "$vendor/bootstrap-icons/fonts" | Out-Null
New-Item -ItemType Directory -Force -Path "$vendor/fonts" | Out-Null

$files = [ordered]@{
  'bootstrap/bootstrap.min.css' = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
  'bootstrap/bootstrap.bundle.min.js' = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
  'bootstrap-icons/bootstrap-icons.min.css' = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'
  'bootstrap-icons/fonts/bootstrap-icons.woff2' = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2'
  'bootstrap-icons/fonts/bootstrap-icons.woff' = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff'
}
foreach ($pair in $files.GetEnumerator()) {
  Invoke-WebRequest -Uri $pair.Value -OutFile (Join-Path $vendor $pair.Key) -UseBasicParsing
}

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'
$families = @(
  'Barlow+Condensed:wght@600;700;800',
  'Inter:wght@400;500;600;700'
)
$cssParts = foreach ($family in $families) {
  $url = "https://fonts.googleapis.com/css2?family=$family&display=swap&subset=latin"
  (Invoke-WebRequest -Uri $url -Headers @{ 'User-Agent' = $ua } -UseBasicParsing).Content
}
$fontCss = $cssParts -join "`n"
$script:fontIndex = 0
$fontCss = [regex]::Replace($fontCss, 'url\((https://[^)]+)\)', {
  param($match)
  $script:fontIndex++
  $source = $match.Groups[1].Value
  $extension = if ($source -match '\.woff2') { 'woff2' } else { 'woff' }
  $name = "font-$script:fontIndex.$extension"
  Invoke-WebRequest -Uri $source -OutFile (Join-Path $vendor "fonts/$name")
  "url('./$name')"
})
$fontCss | Set-Content -Path (Join-Path $vendor 'fonts/fonts.css') -Encoding utf8
