import { AbsoluteFill } from "remotion";
import { COLORS, LAYOUT } from "../theme";

export type CourtBackdropProps = {
  glow: number;
  drift: number;
};

export const CourtBackdrop: React.FC<CourtBackdropProps> = ({ glow, drift }) => (
  <AbsoluteFill style={{ backgroundColor: COLORS.charcoal }}>
    <AbsoluteFill
      style={{
        background: `radial-gradient(ellipse 900px 900px at 50% 47%, ${COLORS.glow}, transparent 70%)`,
        opacity: glow,
        transform: `scale(${1 + 0.06 * glow})`,
      }}
    />
    <svg
      width="100%"
      height="100%"
      viewBox={`0 0 ${LAYOUT.width} ${LAYOUT.height}`}
      style={{ position: "absolute", transform: `translateY(${drift}px)` }}
    >
      <g stroke={COLORS.courtLine} strokeWidth={6} fill="none">
        <rect x={300} y={1240} width={480} height={560} rx={4} />
        <circle cx={540} cy={1520} r={180} />
        <path d="M 60 1240 A 480 480 0 0 1 1020 1240" />
      </g>
    </svg>
  </AbsoluteFill>
);
