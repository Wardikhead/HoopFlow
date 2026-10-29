import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
// Imports TypeScript directly, so this needs Node >= 22.18 (type stripping on
// by default; flag-gated from 22.6 and unavailable on 20.x).
import { encodeWav } from "../src/lib/wav.ts";

const SAMPLE_RATE = 48000;
const OUT_DIR = join(
  dirname(fileURLToPath(import.meta.url)),
  "..",
  "public",
  "sfx",
);

const mulberry32 = (seed) => () => {
  seed |= 0;
  seed = (seed + 0x6d2b79f5) | 0;
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

const peakNormalise = (samples, peakDb) => {
  let peak = 0;
  for (const sample of samples) peak = Math.max(peak, Math.abs(sample));
  if (peak === 0) return samples;
  const target = 10 ** (peakDb / 20);
  const gain = target / peak;
  for (let i = 0; i < samples.length; i++) samples[i] *= gain;
  return samples;
};

const whoosh = () => {
  const duration = 2.2;
  const total = Math.floor(SAMPLE_RATE * duration);
  const samples = new Float32Array(total);
  const random = mulberry32(0x51f1);
  let lowState = 0;
  let highState = 0;

  for (let i = 0; i < total; i++) {
    const progress = i / total;
    const centre = 400 + 2000 * Math.pow(progress, 0.7);
    const cutoff = Math.min(0.99, (2 * Math.PI * centre) / SAMPLE_RATE);
    const noise = random() * 2 - 1;

    lowState += cutoff * (noise - lowState);
    highState += cutoff * (lowState - highState);
    const band = lowState - highState;

    const attack = Math.min(1, i / (SAMPLE_RATE * 0.15));
    const decay = Math.exp(-3.2 * progress);
    samples[i] = band * attack * decay;
  }

  return peakNormalise(samples, -14);
};

const swish = () => {
  const duration = 0.5;
  const total = Math.floor(SAMPLE_RATE * duration);
  const samples = new Float32Array(total);
  const random = mulberry32(0xbadc0de);
  let highState = 0;

  for (let i = 0; i < total; i++) {
    const time = i / SAMPLE_RATE;
    const noise = random() * 2 - 1;
    highState += 0.35 * (noise - highState);
    const hiss = noise - highState;
    const hissEnvelope = Math.exp(-18 * time);
    const thump = Math.sin(2 * Math.PI * 90 * time) * Math.exp(-16.7 * time);
    samples[i] = hiss * hissEnvelope * 0.8 + thump * 0.6;
  }

  return peakNormalise(samples, -10);
};

mkdirSync(OUT_DIR, { recursive: true });
const files = [
  ["whoosh.wav", whoosh()],
  ["swish.wav", swish()],
];

for (const [name, samples] of files) {
  const bytes = encodeWav({ sampleRate: SAMPLE_RATE, samples });
  writeFileSync(join(OUT_DIR, name), bytes);
  console.log(
    `${name}: ${(bytes.byteLength / 1024).toFixed(1)} KB, ${(samples.length / SAMPLE_RATE).toFixed(2)}s`,
  );
}
