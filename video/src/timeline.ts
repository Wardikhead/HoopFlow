export const FPS = 30;
export const DURATION_IN_FRAMES = 195;
export const IMPACT_FRAME = 74;

export const BEATS = {
  backdrop: [0, 7],
  reveal: [8, 56],
  ball: [57, 86],
  wordmark: [87, 125],
  motto: [126, 149],
  hold: [150, 194],
} as const;

export const REVEAL_GROUPS = [
  { name: "leftArm", from: 8, to: 34 },
  { name: "rightArm", from: 12, to: 38 },
  { name: "center", from: 16, to: 44 },
  { name: "rails", from: 20, to: 52 },
] as const;

export type RevealGroupName = (typeof REVEAL_GROUPS)[number]["name"];

export const BALL_FADE = [46, 54] as const;
export const BALL_LIFT_PEAK = 66;
export const BALL_SQUASH_END = 84;
export const PULSE_RING_FRAMES = 18;
export const WORDMARK_TEXT = "HoopFlow";
export const WORDMARK_START = 88;
export const LETTER_STAGGER = 3;
export const MOTTO_FADE = 18;

export const clamp01 = (value: number): number => Math.min(1, Math.max(0, value));

export const beatProgress = (frame: number, from: number, to: number): number => {
  if (to <= from) {
    return frame >= to ? 1 : 0;
  }
  return clamp01((frame - from) / (to - from));
};

export const letterDelays = (text: string, start: number, stagger: number): number[] =>
  Array.from(text).map((_, index) => start + index * stagger);
