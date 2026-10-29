# Icon fidelity gate: compares out/icon-100.png with the source artwork and
# measures how much of the bracket the 50% reveal covers, plus how far the mask
# polylines stray from real ink (>18 units fails).
#
# Usage (two steps, from the video/ directory):
#   1. powershell -ExecutionPolicy Bypass -File tools/render-icon-compare.ps1
#   2. powershell -ExecutionPolicy Bypass -File tools/compare-icon.ps1
#
# Step 1 writes the git-ignored stills out/icon-100.png and out/icon-50.png from
# the committed props in tools/props/*.json. The preflight below exits 2 with
# guidance if they are missing. Exit codes: 0 pass, 1 gate failure, 2 missing input.
Add-Type -AssemblyName System.Drawing
$ErrorActionPreference = 'Stop'

$missingStills = @('out/icon-100.png', 'out/icon-50.png') | Where-Object { -not (Test-Path $_) }
if ($missingStills.Count -gt 0) {
  Write-Output "missing stills: $($missingStills -join ', ')"
  Write-Output 'run tools/render-icon-compare.ps1 first'
  exit 2
}

$sourcePath = Join-Path (Resolve-Path '..\logo') 'hoopflow icon without background.png'
$maskPath = Join-Path (Get-Location) 'src\geometry\maskPaths.json'
$mask = Get-Content $maskPath -Raw | ConvertFrom-Json

$segments = New-Object System.Collections.ArrayList
foreach ($group in $mask.groups.PSObject.Properties) {
  foreach ($line in $group.Value) {
    for ($i = 0; $i -lt $line.Count - 1; $i++) {
      [void]$segments.Add(@($line[$i][0], $line[$i][1], $line[$i + 1][0], $line[$i + 1][1]))
    }
  }
}

function Get-InkSet([string]$path) {
  $bmp = [System.Drawing.Bitmap]::FromFile((Resolve-Path $path))
  $set = New-Object 'System.Collections.Generic.HashSet[string]'
  for ($y = 0; $y -lt $bmp.Height; $y++) {
    for ($x = 0; $x -lt $bmp.Width; $x++) {
      if ($bmp.GetPixel($x, $y).A -gt 100) { [void]$set.Add("$x,$y") }
    }
  }
  $bmp.Dispose()
  return ,$set
}

function Get-PointDistance([double]$px, [double]$py, $seg) {
  $x1 = $seg[0]; $y1 = $seg[1]; $x2 = $seg[2]; $y2 = $seg[3]
  $dx = $x2 - $x1; $dy = $y2 - $y1
  $lengthSquared = $dx * $dx + $dy * $dy
  if ($lengthSquared -eq 0) { return [Math]::Sqrt([Math]::Pow($px - $x1, 2) + [Math]::Pow($py - $y1, 2)) }
  $t = [Math]::Max(0.0, [Math]::Min(1.0, (($px - $x1) * $dx + ($py - $y1) * $dy) / $lengthSquared))
  return [Math]::Sqrt([Math]::Pow($px - ($x1 + $t * $dx), 2) + [Math]::Pow($py - ($y1 + $t * $dy), 2))
}

function Get-MaskDistance([double]$px, [double]$py) {
  $best = [double]::MaxValue
  foreach ($seg in $segments) {
    $d = Get-PointDistance $px $py $seg
    if ($d -lt $best) { $best = $d; if ($best -le 0.001) { break } }
  }
  return $best
}

$source = Get-InkSet $sourcePath
$render100 = Get-InkSet 'out/icon-100.png'
$bracketInk = Get-InkSet 'public/brand/bracket.png'

$mismatch = 0
foreach ($point in $source) { if (-not $render100.Contains($point)) { $mismatch++ } }
foreach ($point in $render100) { if (-not $source.Contains($point)) { $mismatch++ } }

$sourceInk = $source.Count
$renderInk = $render100.Count

$revealed = Get-InkSet 'out/icon-50.png'
$revealedRatio = $revealed.Count / $bracketInk.Count

$worstList = New-Object System.Collections.ArrayList
$offenders = 0
foreach ($point in $bracketInk) {
  $parts = $point.Split(',')
  $d = Get-MaskDistance ([double]$parts[0]) ([double]$parts[1])
  if ($d -gt 18) {
    $offenders++
    [void]$worstList.Add([pscustomobject]@{ X = [int]$parts[0]; Y = [int]$parts[1]; D = $d })
  }
}

$worst = @($worstList | Sort-Object D -Descending | Select-Object -First 10 | ForEach-Object {
  "($($_.X),$($_.Y)) d=$([Math]::Round($_.D,1))"
})

Write-Output "source ink=$sourceInk render ink=$renderInk mismatch=$mismatch"
Write-Output "bracket ink=$($bracketInk.Count) reveal@0.5 ratio=$([Math]::Round($revealedRatio,3))"
Write-Output "mask offenders (>18 units)=$offenders"
if ($worst.Count -gt 0) { Write-Output ($worst -join '; ') }

$failed = $false
if ($mismatch -gt 0) { Write-Output 'FAIL: final lockup does not match source ink'; $failed = $true }
if ($revealedRatio -lt 0.35 -or $revealedRatio -gt 0.65) { Write-Output 'FAIL: 50% reveal coverage out of range'; $failed = $true }
if ($offenders -gt 0) { Write-Output 'FAIL: mask does not cover all ink'; $failed = $true }
if ($failed) { exit 1 }
Write-Output 'PASS: icon fidelity gates'
