# HoopFlow Intro Splash

Vertical 1080×1920, 30fps, 6.5s intro splash for HoopFlow.

## Commands

| Command | What it does |
|---|---|
| `npm run dev` | Remotion Studio with live scrubbing |
| `npm run render` | Writes `out/hoopflow-splash.mp4` (H.264 + AAC audio) |
| `npm run still` | Writes `out/hoopflow-splash-still.png` (frame 194) |
| `npm run assets` | Re-splits the icon PNG into `public/brand/bracket.png` + `ball.png` and rewrites `src/geometry/icon-metrics.json` |
| `npm run sfx` | Regenerates `public/sfx/*.wav` (needs Node ≥ 22.18) |
| `npm test` | Unit tests for timeline, ball motion, and the WAV encoder |

Run `npm install` in this folder first. The `assets`, `still`, and `compare-icon` tooling shells out to Windows PowerShell with `System.Drawing`.

## Layout

- `src/timeline.ts` — every frame number lives here.
- `src/theme.ts` — colors, fonts, layout.
- `src/components/BracketMark.tsx` — official icon split into bracket + ball, bracket revealed by an animated stroke mask.
- `tools/prepare-assets.ps1` — splits `../logo/hoopflow icon without background.png` by a disk at `(149, 60)`, radius `49.5`.
- `tools/compare-icon.ps1` — fidelity gates: the final lockup must match the source ink, the reveal mask must cover all bracket ink within 18 units, and a 50% reveal must cover 35–65% of bracket ink. Run `tools/render-icon-compare.ps1` first when `out/` is empty.

## Notes

- `out/` is git-ignored: the MP4 and PNG are build artifacts, so render them locally — nothing binary is committed.
- The bracket is the official raster art, not a redraw. Do not upscale past ~1.5× without vectorizing (see `../docs/superpowers/specs/2026-10-29-hoopflow-intro-splash-design.md` §13).
- SFX are generated procedurally, so they are license-free. Tune them in `scripts/make-sfx.mjs`.
