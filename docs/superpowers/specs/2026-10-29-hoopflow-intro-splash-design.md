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
| D3 | Icon: official PNG bracket revealed by an animated stroke mask; ball as a cropped PNG moved as a unit | The artwork contains 45° diagonal arms, so segment extraction would misrepresent it. The mask gives exact brand fidelity plus true draw-on motion. Full vectorization deferred (§13) |
| D4 | SFX: procedurally generated WAVs via a Node script | License-free, offline, tunable |
| D5 | Template: Remotion **blank** template | No demo cruft; this splash has no matching upstream template |
| D6 | Fonts: Poppins 800 + Poppins Italic 600, bundled as local `.woff2` | Closest match to the logo's geometric grotesque; offline render |
| D7 | Brand colors: charcoal `#171D23`, orange `#F56103` | Measured from `logo/hoopflow lockup horizontal on white.png` |
| D8 | Motto: *"Let the games run themselves."* | From the official lockup |
| D9 | Ending: hard stop on the lockup, no fade-out | Splash must cut/loop cleanly |
| D10 | Icon fidelity gates: final-lockup diff ≤2px against the source PNG; at 50% draw, no ink outside the swept mask path | Measurable acceptance, not subjective |
| D11 | Scaffold `create-video` in a temp directory, then copy sources into `video/` | `create-video --yes` fails when run inside an existing git repo, and this workspace is now a repo |
| D12 | Icon split into two generated assets: `bracket.png` (ball erased) and `ball.png` (ball crop) | The ball needs independent drop/squash motion; the bracket needs a static mask target. Both derived mechanically from the official PNG, so fidelity is exact |

## 5. Canvas and timeline

Composition id: `HoopFlowSplash`. `fps: 30`, `width: 1080`, `height: 1920`, `durationInFrames: 195`.

| Beat | Frames | Time | Content |
|---|---|---|---|
| B0 | 0–7 | 0.00–0.27s | Backdrop only: charcoal + glow ramp 0→1 |
| B1 | 8–56 | 0.27–1.87s | Bracket reveals under the stroke mask; ball fades in at 46–54 once the sweep clears the top |
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

Illustrated as a single `<svg viewBox="0 0 302 292" width={470}>`, so every layer shares the source coordinate system and no scaling math is duplicated.

Layer order, back to front:

1. **Bracket** — `<image href={staticFile("brand/bracket.png")} width={302} height={292} mask="url(#drawMask)" />`
2. **PulseRing** — centered on the ball at `(149, 60)` in source units
3. **Ball** — `<image href={staticFile("brand/ball.png")} width={302} height={292} />` inside `<g transform={ballTransform}>`

Asset split: `tools/prepare-assets.ps1` splits the official PNG by a disk of radius `49.5` centered `(149, 60)` into `bracket.png` (outside the disk) and `ball.png` (inside it). Their union at rest is pixel-identical to the original, so the trunk that meets the ball survives and the split leaves no seam.

Ball transform: `translate(0, drop) translate(149, 60) scale(sx, sy) translate(-149, -60)`, where `drop` is in source units (1 unit ≈ 1.556 display px at 470 width) and `sx`/`sy` produce the squash.

Mask (draw-on):

- `<mask id="drawMask" maskUnits="userSpaceOnUse" x={0} y={0} width={302} height={292}>` with a black `<rect>` backing; white strokes reveal.
- Each reveal path: `fill="none" stroke="white" strokeWidth={30} strokeLinecap="round" strokeLinejoin="round" pathLength={1} strokeDasharray={1} strokeDashoffset={1 - progress}`.
- `strokeWidth 30` exceeds the 12-unit ink plus alignment slack, so a roughly traced centerline reveals the full stroke without exposing edges.
- Reveal paths are **hand-traced approximations** of the centerlines. That is deliberate: the mask never draws, it only gates visibility, so its accuracy requirement is ±9 units rather than ±2px.

Reveal order and stagger across B1: left arm (frames 8–34) → right arm (12–38) → center stem and steps (16–44) → bottom rails (20–52). Each group's progress comes from `beatProgress`, never from a literal frame.

Measured anchors for tracing the mask (from the PNG, 302×292, ink box `x 14–285`, `y 14–275`):

| Feature | Measurement |
|---|---|
| Symmetry axis | `x ≈ 149.5` (runs `14–25` mirror `274–285`) |
| Stroke width | 12 units, uniform |
| Ball disk | outer span `x 103–195`, `y 14–106` → radius ≈ 46, center `(149, 60)` |
| Upper shoulders | horizontals at `y ≈ 74`, running to `x ≈ 59` and `x ≈ 240` |
| 45° diagonal arms | from `(59, 80)` down-right to `(89, 107)`, mirrored; runs shift +1px per row |
| Center stems | verticals at `x 131–141` and `x 157–168`, gap `142–156`, from `y ≈ 136` to `275` |
| Horizontal seam | `y ≈ 57`, spanning `x 104–194` |
| Long lower horizontals | `y ≈ 230` (`x 104–141`, `157–194`) and `y ≈ 250` (`x 16–79`, `219–283`) |
| Outer rails | verticals at `x 16–25`, `y 139–254`, mirrored at `x 274–285` |

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

1. `tools/prepare-assets.ps1` splits `logo/hoopflow icon without background.png` by a disk of radius `49.5` at `(149, 60)` into `video/public/brand/bracket.png` and `video/public/brand/ball.png`, and writes `video/src/geometry/icon-metrics.json` with the measured source size, ink box, and ball center/radius.
2. Render `IconCompare` (302×292) at full reveal with `drop = 0`. Its rasterized ink must match the source PNG within 2px on every edge — expected to be exact, since the assets are the same pixels.
3. Render `IconCompare` at 50% reveal. Every revealed ink pixel must fall within 18 units of a mask polyline, and the revealed area must land between 35% and 65% of total ink. This is what catches a mask that drifts off the artwork.
4. If step 3 fails, adjust the traced polylines and re-run. The mask is allowed to be rough; the gate catches real misses, not cosmetic ones.
5. There is no fidelity fallback branch. Fidelity is structural (same source pixels); only the mask tracing is iterative.

## 8. Audio

`scripts/make-sfx.mjs` writes two 48kHz 16-bit mono WAV files into `public/sfx/` using plain Node (`fs` + manual RIFF header), no dependencies:

| File | Duration | Synthesis | Placement |
|---|---|---|---|
| `whoosh.wav` | 2.2s | White noise → band-pass sweeping 400→2400Hz, attack 150ms, exponential decay to −60dB, peak −14dBFS | Starts at frame 8, ends exactly on the impact frame 74 |
| `swish.wav` | 0.5s | Noise burst through 1.8kHz high-pass + 90Hz sine thump with 60ms decay, peak −10dBFS | Starts at frame 74 (impact) |

Both are mixed at `volume ≈ 0.5` above the visuals.

Audio timing note (verified against Remotion 4.0.539 docs): `startFrom`/`endAt` were renamed `trimBefore`/`trimAfter` in 4.0.319, and `trimAfter` is itself superseded by `durationInFrames`. Trimming removes audio from the start of the *source* — it does **not** delay playback. Delaying is done by wrapping `<Audio>` in `<Sequence from={…}>`. So each SFX is mounted as `<Sequence from={startFrame}><Audio src={staticFile(path)} volume={0.5} /></Sequence>`.

## 9. Architecture

Single source of truth for timing (`timeline.ts`) and styling (`theme.ts`); components are pure functions of props. Only `Splash.tsx` reads `useCurrentFrame()`.

```
video/
  package.json
  tsconfig.json
  remotion.config.ts
  eslint.config.mjs
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
    lib/
      geometry.ts                 # point/segment types + path builders (pure)
      wav.ts                      # RIFF/WAV encoder (pure)
      iconDiff.ts                 # alpha-grid comparison (pure)
    geometry/
      icon-metrics.json           # generated by prepare-assets.ps1
      maskPaths.ts                # hand-traced reveal centerlines (approximate)
    Splash.tsx                    # composition root; frame → props
    components/
      CourtBackdrop.tsx
      BracketMark.tsx
      Wordmark.tsx
      Motto.tsx
      PulseRing.tsx
    comps/IconCompare.tsx         # fidelity harness, 302×292
    fonts.ts                      # loadFont() calls for bundled woff2
    __tests__/                    # vitest unit tests for lib/ and timeline
  tools/
    prepare-assets.ps1            # splits icon PNG into bracket.png + ball.png
    compare-icon.ps1              # render vs PNG alpha diff
  scripts/
    make-sfx.mjs                  # writes public/sfx/*.wav
  out/                            # render artifacts (gitignored)
```

Responsibilities:

- `theme.ts` — colors, font families, sizes, positions. No animation logic.
- `timeline.ts` — beat boundaries, letter stagger, and helpers like `beatProgress(frame, start, end)`.
- `BracketMark.tsx` — asset layering, mask reveal, ball transform. Knows nothing about the splash timeline.
- `maskPaths.ts` — plain data: named reveal groups with centerline point arrays. No imports.
- `Splash.tsx` — computes `reveal`, `ballDrop`, `ballSquash`, per-letter delays from `timeline.ts`, composes the scene, mounts `<Audio>`.

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
| Mask centerlines drift off the artwork, revealing partial strokes | `strokeWidth 30` tolerates ±9 units of error; §7 step 3 asserts every revealed pixel is within 18 units of a mask polyline |
| Ball crop edge shows a seam against the bracket after the drop | Bracket and ball are split by one shared disk radius, so their union is exact at rest; the ball returns to `drop = 0` before the hold |
| Wordmark type doesn't match the logo's custom letterforms | Poppins 800 chosen for closest match; if visibly off, swap to the official lockup PNG for the final hold only, and document the trade-off |
| Google Fonts unavailable at render time | Fonts bundled locally and loaded with `@remotion/fonts` |
| Remotion Audio prop names changed across 4.x | `Sequence` + `trimBefore`/`durationInFrames` verified against 4.0.539 docs; audio isolated in one component |
| Vertical safe-area cropping on social | All content kept inside `y 400–1500`; only backdrop occupies the extremes |

## 12. Out of scope

- 16:9 / 1:1 variants, captions, music, localization.
- Integrating the splash into the HoopFlow web app build.
- Any change to the `HoopFlow/` documentation set.

## 13. Deferred: full vectorization

Vectorizing the bracket into SVG paths (route B) is deferred, not cancelled. It would only matter above ~1.5× display scale, i.e. 4K exports or print. Trigger conditions to pick it up:

- The splash gets reused at 2160p or larger.
- The mark needs recoloring at runtime (white-on-orange, single-color print).
- Someone supplies an official `.ai`/`.svg` from the designer, which makes the work mechanical instead of a trace.

Because the geometry contains 45° arms, tracing requires fitting segments at 0°/45°/90° plus rounded joins and iterating against the §7 gates.
