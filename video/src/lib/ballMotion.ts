import { BALL_LIFT_PEAK, BALL_SQUASH_END, BEATS, IMPACT_FRAME } from "../timeline";

const LIFT_START = BEATS.ball[0];
const LIFT_HEIGHT = -16;

const easeOut = (t: number): number => 1 - (1 - t) * (1 - t);
const easeIn = (t: number): number => t * t;
const lerp = (a: number, b: number, t: number): number => a + (b - a) * t;
const clamp = (t: number): number => Math.min(1, Math.max(0, t));

export const ballDrop = (frame: number): number => {
  if (frame <= LIFT_START) return 0;
  if (frame <= BALL_LIFT_PEAK) {
    return lerp(0, LIFT_HEIGHT, easeOut((frame - LIFT_START) / (BALL_LIFT_PEAK - LIFT_START)));
  }
  if (frame <= IMPACT_FRAME) {
    return lerp(LIFT_HEIGHT, 0, easeIn((frame - BALL_LIFT_PEAK) / (IMPACT_FRAME - BALL_LIFT_PEAK)));
  }
  return 0;
};

export const ballSquash = (frame: number): number => {
  if (frame < IMPACT_FRAME || frame > BALL_SQUASH_END) return 0;
  const t = clamp((frame - IMPACT_FRAME) / (BALL_SQUASH_END - IMPACT_FRAME));
  return Math.sin(Math.PI * t);
};
