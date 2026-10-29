# HoopFlow Intro Splash

Vertical 1080×1920, 30fps, 6.5s intro splash for HoopFlow.

## Commands

| Command | What it does |
|---|---|
| `npm run dev` | Remotion Studio with live scrubbing |
| `npm run render` | Writes `out/hoopflow-splash.mp4` (H.264) |
| `npm run still` | Writes `out/hoopflow-splash-still.png` (frame 194) |
| `npm run assets` | Re-splits the icon PNG into `bracket.png` + `ball.png` |
| `npm run sfx` | Regenerates `public/sfx/*.wav` |
| `npm test` | Unit tests for timeline, ball motion, and the WAV encoder |

## Layout

- `src/timeline.ts` — every frame number lives here.
- `src/theme.ts` — colors, fonts, layout.
- `src/components/BracketMark.tsx` — official icon split into bracket + ball, bracket revealed by an animated stroke mask.
- `tools/prepare-assets.ps1` — splits `../logo/hoopflow icon without background.png` by a disk at `(149, 60)`, radius `49.5`.
- `tools/compare-icon.ps1` — fidelity gates: final lockup must match the source ink, and the reveal mask must cover all bracket ink within 18 units.

## Notes

- The bracket is the official raster art, not a redraw. Do not upscale past ~1.5× without vectorizing (see `../docs/superpowers/specs/2026-10-29-hoopflow-intro-splash-design.md` §13).
- SFX are generated procedurally, so they are license-free. Tune them in `scripts/make-sfx.mjs`.
