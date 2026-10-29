import type { CSSProperties } from "react";
import { Img, staticFile } from "remotion";
import maskData from "../geometry/maskPaths.json";
import metrics from "../geometry/icon-metrics.json";
import { LAYOUT } from "../theme";
import type { RevealGroupName } from "../timeline";

const VIEW_W = 302;
const VIEW_H = 292;
const MASK_STROKE = maskData.strokeWidth;

export type BracketMarkProps = {
  reveal: Record<RevealGroupName, number>;
  drop: number;
  squash: number;
  ballOpacity: number;
  width?: number;
};

const polylinesFor = (name: RevealGroupName): number[][][] =>
  (maskData.groups as Record<string, number[][][]>)[name] ?? [];

const toPath = (points: number[][]): string =>
  points.map(([x, y], index) => `${index === 0 ? "M" : "L"} ${x} ${y}`).join(" ");

const buildMask = (reveal: Record<RevealGroupName, number>): string => {
  const paths = (Object.keys(reveal) as RevealGroupName[])
    .flatMap((name) =>
      polylinesFor(name).map(
        (points) =>
          `<path d="${toPath(points)}" fill="none" stroke="white" stroke-width="${MASK_STROKE}" stroke-linecap="round" stroke-linejoin="round" pathLength="1" stroke-dasharray="1" stroke-dashoffset="${1 - reveal[name]}"/>`,
      ),
    )
    .join("");
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${VIEW_W} ${VIEW_H}" preserveAspectRatio="none">${paths}</svg>`;
  return `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`;
};

export const BracketMark: React.FC<BracketMarkProps> = ({
  reveal,
  drop,
  squash,
  ballOpacity,
  width = LAYOUT.iconWidth,
}) => {
  const height = (width * VIEW_H) / VIEW_W;
  const unit = width / VIEW_W;
  const { cx, cy } = metrics.ball;
  const scaleX = 1 + 0.1 * squash;
  const scaleY = 1 - 0.14 * squash;
  const maskImage = buildMask(reveal);

  const maskStyle: CSSProperties = {
    position: "absolute",
    inset: 0,
    maskImage,
    WebkitMaskImage: maskImage,
    maskSize: "100% 100%",
    WebkitMaskSize: "100% 100%",
    maskRepeat: "no-repeat",
    WebkitMaskRepeat: "no-repeat",
  };

  return (
    <div style={{ position: "relative", width, height }}>
      <div style={maskStyle}>
        <Img
          src={staticFile("brand/bracket.png")}
          style={{ width: "100%", height: "100%", display: "block" }}
        />
      </div>
      <div
        style={{
          position: "absolute",
          inset: 0,
          opacity: ballOpacity,
          transform: `translateY(${drop * unit}px) scale(${scaleX}, ${scaleY})`,
          transformOrigin: `${(cx / VIEW_W) * 100}% ${(cy / VIEW_H) * 100}%`,
        }}
      >
        <Img
          src={staticFile("brand/ball.png")}
          style={{ width: "100%", height: "100%", display: "block" }}
        />
      </div>
    </div>
  );
};
