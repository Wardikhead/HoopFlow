Add-Type -AssemblyName System.Drawing
$ErrorActionPreference = 'Stop'

$sourcePath = Join-Path (Resolve-Path '..\logo') 'hoopflow icon without background.png'
$outDir = Join-Path (Get-Location) 'public\brand'
$metricsDir = Join-Path (Get-Location) 'src\geometry'
New-Item -ItemType Directory -Force $outDir | Out-Null
New-Item -ItemType Directory -Force $metricsDir | Out-Null

$src = [System.Drawing.Bitmap]::FromFile($sourcePath)
$width = $src.Width
$height = $src.Height
$cx = 149.0
$cy = 60.0
$radius = 49.5

$bracket = New-Object System.Drawing.Bitmap $width, $height, ([System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
$ball = New-Object System.Drawing.Bitmap $width, $height, ([System.Drawing.Imaging.PixelFormat]::Format32bppArgb)

$mismatched = 0
for ($y = 0; $y -lt $height; $y++) {
  for ($x = 0; $x -lt $width; $x++) {
    $colour = $src.GetPixel($x, $y)
    $dx = ($x + 0.5) - $cx
    $dy = ($y + 0.5) - $cy
    $inside = ($dx * $dx + $dy * $dy) -le ($radius * $radius)
    if ($inside) {
      $ball.SetPixel($x, $y, $colour)
      $bracket.SetPixel($x, $y, [System.Drawing.Color]::FromArgb(0, 0, 0, 0))
    } else {
      $bracket.SetPixel($x, $y, $colour)
      $ball.SetPixel($x, $y, [System.Drawing.Color]::FromArgb(0, 0, 0, 0))
    }
  }
}

for ($y = 0; $y -lt $height; $y++) {
  for ($x = 0; $x -lt $width; $x++) {
    $sourceAlpha = $src.GetPixel($x, $y).A
    $splitAlpha = [Math]::Max($bracket.GetPixel($x, $y).A, $ball.GetPixel($x, $y).A)
    if ([Math]::Abs($sourceAlpha - $splitAlpha) -gt 1) { $mismatched++ }
  }
}

$bracket.Save((Join-Path $outDir 'bracket.png'), [System.Drawing.Imaging.ImageFormat]::Png)
$ball.Save((Join-Path $outDir 'ball.png'), [System.Drawing.Imaging.ImageFormat]::Png)

@{
  width = $width
  height = $height
  strokeWidth = 12
  ball = @{ cx = $cx; cy = $cy; radius = $radius }
} | ConvertTo-Json -Depth 5 | Set-Content -Encoding utf8 (Join-Path $metricsDir 'icon-metrics.json')

Write-Output "source=${width}x${height} ball=($cx,$cy) r=$radius alphaMismatches=$mismatched"
$src.Dispose(); $bracket.Dispose(); $ball.Dispose()
