import metrics from "../geometry/icon-metrics.json";
import { COLORS } from "../theme";
import { IMPACT_FRAME, PULSE_RING_FRAMES, beatProgress } from "../timeline";

const RADIUS_FROM = 46;
const RADIUS_TO = 120;

export const PulseRing: React.FC<{ frame: number }> = ({ frame }) => {
  const progress = beatProgress(frame, IMPACT_FRAME, IMPACT_FRAME + PULSE_RING_FRAMES);
  if (progress <= 0 || progress >= 1) return null;
  const radius = RADIUS_FROM + (RADIUS_TO - RADIUS_FROM) * progress;

  return (
    <svg
      width="100%"
      height="100%"
      viewBox={`0 0 ${metrics.width} ${metrics.height}`}
      style={{ overflow: "visible" }}
    >
      <circle
        cx={metrics.ball.cx}
        cy={metrics.ball.cy}
        r={radius}
        fill="none"
        stroke={COLORS.orange}
        strokeWidth={2}
        opacity={0.55 * (1 - progress)}
      />
    </svg>
  );
};
