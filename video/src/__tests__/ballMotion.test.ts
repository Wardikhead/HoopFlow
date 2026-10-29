import { describe, expect, it } from "vitest";
import { ballDrop, ballSquash } from "../lib/ballMotion";

describe("ballDrop", () => {
  it("is at rest before the lift", () => {
    expect(ballDrop(57)).toBe(0);
  });

  it("peaks at the top of the lift", () => {
    expect(ballDrop(66)).toBeCloseTo(-16, 5);
  });

  it("is back at rest exactly on impact", () => {
    expect(ballDrop(74)).toBe(0);
  });

  it("stays at rest after impact", () => {
    expect(ballDrop(80)).toBe(0);
    expect(ballDrop(100)).toBe(0);
  });
});

describe("ballSquash", () => {
  it("is zero before impact", () => {
    expect(ballSquash(73)).toBe(0);
  });

  it("peaks mid-squash", () => {
    expect(ballSquash(79)).toBeGreaterThan(0.9);
  });

  it("recovers to zero", () => {
    expect(ballSquash(84)).toBeCloseTo(0, 5);
  });
});
