# HoopFlow Intro Splash — Design Spec

- **Date:** 2026-10-29
- **Author:** Hank (with Jeward)
- **Status:** Approved for planning
- **Topic:** Animated 9:16 intro splash built in Remotion
- **Project root:** `C:\Users\JewardMahinay\Downloads\hoopflow development`
- **New app directory:** `video/`

## 1. Summary

A 6.5-second vertical splash (1080×1920) for HoopFlow. The bracket-and-ball icon draws itself, the ball drops in with a squash-and-stretch bounce, the wordmark rises letter by letter, the motto settles, and the composition hard-stops on a clean lockup that doubles as the social thumbnail.

Built with Remotion. The icon is hand-authored as SVG so the "draw" beat is a real stroke animation rather than a mask wipe.

## 2. Goals

- Deliver a brand-accurate, loop-friendly 6.5s vertical splash as an H.264 MP4.
- Reuse the official icon geometry (bracket + basketball) faithfully, verified by pixel diff against `logo/hoopflow icon without background.png`.
- Keep every visual element animatable and scalable (no upscaled raster marks in the final lockup).
- Ship license-free SFX generated locally, with no third-party audio downloads.
- Stay renderable offline (bundled fonts, generated audio).

## 3. Non-goals

- No 16:9 or square variants in this spec.
- No music bed, voice-over, or captions.
- No product UI screenshots or feature demo inside the splash.
- No 3D, particle systems, or physics simulation beyond one scripted bounce.
- No change to any existing document in `HoopFlow/`.

## 4. Decisions

| # | Decision | Rationale |
|---|---|---|
| D1 | Format: vertical 1080×1920, 30fps, 195 frames (6.5s) | Chosen for Reels/TikTok/Stories |
| D2 | Motion concept: bracket draws → ball drops → wordmark → motto → hold | Communicates the product; reuses the real icon |
| D3 | Icon: hand-authored SVG paths, PNG only as diff reference | Only stroked paths support `strokeDashoffset`; crisp at any size |
| D4 | SFX: procedurally generated WAVs via a Node script | License-free, offline, tunable |
| D5 | Template: Remotion **blank** template | No demo cruft; this splash has no matching upstream template |
| D6 | Fonts: Poppins 800 + Poppins Italic 600, bundled as local `.woff2` | Closest match to the logo's geometric grotesque; offline render |
| D7 | Brand colors: charcoal `#171D23`, orange `#F56103` | Measured from `logo/hoopflow lockup horizontal on white.png` |
| D8 | Motto: *"Let the games run themselves."* | From the official lockup |
| D9 | Ending: hard stop on the lockup, no fade-out | Splash must cut/loop cleanly |
| D10 | Icon fidelity gate: stroke edges within 2px of the PNG | Measurable acceptance, not subjective |

## 5. Canvas and timeline

Composition id: `HoopFlowSplash`. `fps: 30`, `width: 1080`, `height: 1920`, `durationInFrames: 195`.

| Beat | Frames | Time | Content |
|---|---|---|---|
| B0 | 0–7 | 0.00–0.27s | Backdrop only: charcoal + glow ramp 0→1 |
| B1 | 8–56 | 0.27–1.87s | Bracket paths draw on; ball rim and seams draw last |
| B2 | 57–86 | 1.90–2.87s | Ball lifts, drops, squashes on impact; one pulse ring |
| B3 | 87–125 | 2.90–4.17s | Wordmark letters rise with stagger |
| B4 | 126–149 | 4.20–4.97s | Motto fades up, letter-spacing settles |
| B5 | 150–194 | 5.00–6.47s | Hold; glow breathes once; frame 194 is the export still |

Frame constants live in `src/timeline.ts` and are imported by components. No component hardcodes a frame number.

## 6. Visual design

### 6.1 Backdrop (`CourtBackdrop`)

- Base fill `#171D23`.
- Radial glow: soft orange ellipse centered at `(540, 900)`, radius ~900px, `#F56103` at 12% peak dilution, opacity ramps 0→1 across B0, then scale 1.00→1.06 and back across B5.
- Court lines: white at 5% opacity — a key (paint) rectangle plus a three-point arc — grouped as SVG, translated `0 → -40px` on Y across the full 195 frames for slow parallax.
- No hard frame edges; the glow must tolerate feed-UI cropping at the top and bottom.

### 6.2 Icon (`BracketMark`)

- Placement: `width: 470px` (height ≈ 454 at the 302:292 source ratio), horizontally centered, icon top at `y = 620`.
- Draw: each path gets `strokeDasharray = pathLength`, `strokeDashoffset = pathLength × (1 − progress)`. Path order and stagger: left bracket group → right bracket group → center stems → ball rim → seam arcs, each starting ~4 frames after the previous, all completing by frame 52.
- Overdraw guard: `pathLength` is normalized to 1 so a single `progress` prop drives every path.
- Ball group is separable from the bracket: it receives `ballDrop` (px offset) and `ballSquash` (0→1) so B2 can move it independently of the drawn frame.
- Stroke: `12` units on the 302-wide source viewBox, `strokeLinecap="round"`, `strokeLinejoin="round"`, color `#F56103` by default.

Measured source geometry (from the PNG, 302×292, ink box `x 14–285`, `y 14–275`):

| Feature | Measurement |
|---|---|
| Symmetry axis | `x ≈ 149.5` (runs `14–25` mirror `274–285`) |
| Stroke width | 12px, uniform |
| Ball outer span | `y 15–100`, `x 103–195` → outer radius ≈ 46, center `(149, 57)` |
| Ball shoulder band | solid band at `y 53–62` spanning `x 103–195` |
| Upper shoulders | horizontals at `y 69–77` running to `x ≈ 59` and `x ≈ 240` |
| Center stems | verticals at `x 130–141` and `x 157–168`, gap `142–156` |
| Inner verticals | `x 89–100` (mirror `199–210`), `y 94–150` |
| Left outer rail | `x 16–25`, `y 139–186` and `y 211–254` |
| Long lower horizontals | `y ≈ 250`, `x 16–79` and `x 219–283` |

The full step list is extracted programmatically during implementation; the table above is the acceptance anchor set.

### 6.3 Wordmark (`Wordmark`)

- "Hoop" in `#FFFFFF`, "Flow" in `#F56103`, Poppins 800.
- Font size 132px, letter-spacing `-0.02em`, single line, baseline region centered at `y ≈ 1180`.
- Per-letter: `translateY 46 → 0` with `spring({ damping: 12, stiffness: 120, mass: 0.7 })`, opacity `0 → 1` over 8 frames.
- Stagger: 3 frames between letters, first letter starts at frame 88.
- Letters are individual `<span>`s in flex row so kerning drift stays negligible; total wordmark width is checked visually against the source lockup proportions.

### 6.4 Motto (`Motto`)

- Text: `Let the games run themselves.`
- Poppins Italic 600, 44px, `rgba(255,255,255,0.72)`, centered at `y ≈ 1270`.
- Opacity `0 → 1` across frames 128–146; letter-spacing `0.06em → 0` over the same window, eased with `Easing.out(Easing.cubic)`.

### 6.5 Pulse ring (`PulseRing`)

- Born at ball impact (frame 74), centered on the ball.
- Radius `46 → 120`, stroke `2px`, color `#F56103`, opacity `0.55 → 0` across frames 74–92. Rendered behind the ball.

## 7. Icon fidelity protocol

1. Extract path coordinates from `logo/hoopflow icon without background.png` with a run-length analysis script (horizontal runs per row, vertical runs per column), collapsed into rectilinear segments with rounded joins.
2. Author `BracketMark` on a `0 0 302 292` viewBox at 12-unit stroke.
3. Render a static comparison composition (`IconCompare`, 302×292, all paths at progress 1) to `out/icon-compare.png`.
4. `scripts/icon-diff.mjs` composites the render over the source PNG, measures the per-pixel alpha mismatch, and reports the maximum edge deviation in px.
5. **Gate:** max edge deviation ≤ 2px and no missing or extra structural segment. If the gate fails twice, fall back to: PNG mask-wipe for the bracket body plus SVG for the ball only.

## 8. Audio

`scripts/make-sfx.mjs` writes two 48kHz 16-bit mono WAV files into `public/sfx/` using plain Node (`fs` + manual RIFF header), no dependencies:

| File | Duration | Synthesis | Placement |
|---|---|---|---|
| `whoosh.wav` | 1.4s | White noise → band-pass sweeping 400→2400Hz, attack 150ms, exponential decay to −60dB, peak −14dBFS | Starts at frame 8; tail lands on the ball impact |
| `swish.wav` | 0.5s | Noise burst through 1.8kHz high-pass + 90Hz sine thump with 60ms decay, peak −10dBFS | Starts at frame 74 (impact) |

Both are mixed at `volume ≈ 0.5` above the visuals. The Audio API uses Remotion's current trim props (`trimBefore`/`trimAfter` on 4.0.3xx+; verify against the installed version's docs before wiring).

## 9. Architecture

Single source of truth for timing (`timeline.ts`) and styling (`theme.ts`); components are pure functions of props. Only `Splash.tsx` reads `useCurrentFrame()`.

```
video/
  package.json
  tsconfig.json
  remotion.config.ts
  public/
    fonts/Poppins-ExtraBold.woff2
    fonts/Poppins-SemiBoldItalic.woff2
    sfx/whoosh.wav
    sfx/swish.wav
  src/
    index.ts                      # registerRoot
    Root.tsx                      # <Composition> definitions
    theme.ts                      # colors, fonts, layout constants
    timeline.ts                   # beat frame constants + derived progress helpers
    Splash.tsx                    # composition root; frame → props
    components/
      CourtBackdrop.tsx
      BracketMark.tsx
      Wordmark.tsx
      Motto.tsx
      PulseRing.tsx
    comps/IconCompare.tsx         # fidelity harness, 302×292
    fonts.ts                      # loadFont() calls for bundled woff2
  scripts/
    make-sfx.mjs
    icon-diff.mjs
  out/                            # render artifacts (gitignored)
```

Responsibilities:

- `theme.ts` — colors, font families, sizes, positions. No animation logic.
- `timeline.ts` — beat boundaries, letter stagger, and helpers like `beatProgress(frame, start, end)`.
- `BracketMark.tsx` — geometry and stroke drawing only; knows nothing about the splash timeline.
- `Splash.tsx` — computes `drawProgress`, `ballDrop`, `ballSquash`, per-letter delays from `timeline.ts`, composes the scene, mounts `<Audio>`.

## 10. Verification

1. `npx tsc --noEmit` — no type errors.
2. `npm run dev` → Remotion Studio, scrub frames 0, 30, 60, 75, 95, 140, 194.
3. `npx remotion render HoopFlowSplash out/hoopflow-splash.mp4 --codec=h264` — succeeds.
4. `npx remotion ffprobe out/hoopflow-splash.mp4` — confirm `1080x1920`, `30fps`, duration ≈ 6.5s.
5. `npx remotion still HoopFlowSplash out/hoopflow-splash-still.png --frame=194` — final lockup is clean and centered.
6. Icon diff gate from §7 reports ≤ 2px.
7. Audio check: MP4 has an audio stream; whoosh does not clip before the ball impact; swish lands on contact.

## 11. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Hand-authored SVG drifts from the official mark | Pixel-diff gate at 302×292 with a documented PNG fallback |
| Wordmark type doesn't match the logo's custom letterforms | Poppins 800 chosen for closest match; if visibly off, swap to the official lockup PNG for the final hold only, and document the trade-off |
| Google Fonts unavailable at render time | Fonts bundled locally and loaded with `@remotion/fonts` |
| Remotion Audio prop names changed across 4.x | Check installed version's docs before wiring; keep audio behind one module |
| Vertical safe-area cropping on social | All content kept inside `y 400–1500`; only backdrop occupies the extremes |

## 12. Out of scope

- 16:9 / 1:1 variants, captions, music, localization.
- Integrating the splash into the HoopFlow web app build.
- Any change to the `HoopFlow/` documentation set.
