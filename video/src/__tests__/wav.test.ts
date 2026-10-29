import { describe, expect, it } from "vitest";
import { encodeWav } from "../lib/wav";

const readAscii = (bytes: Uint8Array, offset: number, length: number): string =>
  String.fromCharCode(...Array.from(bytes.slice(offset, offset + length)));

describe("encodeWav", () => {
  const samples = new Float32Array([0, 0.5, -0.5, 1, -1]);
  const bytes = encodeWav({ sampleRate: 48000, samples });

  it("writes a RIFF/WAVE header", () => {
    expect(readAscii(bytes, 0, 4)).toBe("RIFF");
    expect(readAscii(bytes, 8, 4)).toBe("WAVE");
    expect(readAscii(bytes, 12, 4)).toBe("fmt ");
    expect(readAscii(bytes, 36, 4)).toBe("data");
  });

  it("declares mono 16-bit PCM at the given rate", () => {
    const view = new DataView(bytes.buffer);
    expect(view.getUint16(20, true)).toBe(1);
    expect(view.getUint16(22, true)).toBe(1);
    expect(view.getUint32(24, true)).toBe(48000);
    expect(view.getUint16(34, true)).toBe(16);
  });

  it("sizes the payload as twice the sample count", () => {
    const view = new DataView(bytes.buffer);
    expect(bytes.byteLength).toBe(44 + samples.length * 2);
    expect(view.getUint32(40, true)).toBe(samples.length * 2);
    expect(view.getUint32(4, true)).toBe(36 + samples.length * 2);
  });

  it("clamps and scales samples to int16 range", () => {
    const view = new DataView(bytes.buffer);
    expect(view.getInt16(44, true)).toBe(0);
    expect(view.getInt16(46, true)).toBe(16383);
    expect(view.getInt16(50, true)).toBe(32767);
  });
});
