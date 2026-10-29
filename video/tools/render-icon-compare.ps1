# Renders the two IconCompare stills the icon fidelity gate needs.
#
# Usage (two steps, from the video/ directory):
#   1. powershell -ExecutionPolicy Bypass -File tools/render-icon-compare.ps1
#   2. powershell -ExecutionPolicy Bypass -File tools/compare-icon.ps1
#
# The props live in version control (tools/props/*.json) so a fresh clone can
# reproduce the stills; inline --props='{"...":1}' does not survive Windows
# PowerShell quoting, so remotion's JSON props file form is used instead.
# Outputs (out/ is git-ignored): out/icon-100.png, out/icon-50.png.
$ErrorActionPreference = 'Stop'

$outDir = Join-Path (Get-Location) 'out'
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Force $outDir | Out-Null }

$renders = @(
  @{ Output = 'out/icon-100.png'; Props = 'tools/props/icon-100.json' },
  @{ Output = 'out/icon-50.png'; Props = 'tools/props/icon-50.json' }
)

foreach ($render in $renders) {
  Write-Output "rendering $($render.Output) from $($render.Props)"
  & npx remotion still IconCompare $render.Output --frame=0 "--props=$($render.Props)"
  if ($LASTEXITCODE -ne 0) { throw "remotion still failed for $($render.Output) (exit $LASTEXITCODE)" }
}

Write-Output 'done: out/icon-100.png and out/icon-50.png written'
