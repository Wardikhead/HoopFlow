import { describe, expect, it } from "vitest";
import { beatProgress, clamp01, letterDelays } from "../timeline";

describe("beatProgress", () => {
  it("is 0 at the start and 1 at the end", () => {
    expect(beatProgress(8, 8, 56)).toBe(0);
    expect(beatProgress(56, 8, 56)).toBe(1);
  });

  it("is 0.5 exactly halfway", () => {
    expect(beatProgress(32, 8, 56)).toBe(0.5);
  });

  it("clamps outside the beat", () => {
    expect(beatProgress(0, 8, 56)).toBe(0);
    expect(beatProgress(999, 8, 56)).toBe(1);
  });

  it("treats an empty beat as instant", () => {
    expect(beatProgress(9, 10, 10)).toBe(0);
    expect(beatProgress(10, 10, 10)).toBe(1);
  });
});

describe("letterDelays", () => {
  it("staggers one delay per character", () => {
    expect(letterDelays("HoopFlow", 88, 3)).toEqual([88, 91, 94, 97, 100, 103, 106, 109]);
  });
});

describe("clamp01", () => {
  it("clamps to the unit interval", () => {
    expect(clamp01(-3)).toBe(0);
    expect(clamp01(0.4)).toBe(0.4);
    expect(clamp01(9)).toBe(1);
  });
});
