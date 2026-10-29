import { Easing, interpolate } from "remotion";
import { COLORS, FONTS, LAYOUT } from "../theme";
import { BEATS, MOTTO_FADE, beatProgress } from "../timeline";

const [MOTTO_FROM, MOTTO_TO] = BEATS.motto;

export const Motto: React.FC<{ frame: number }> = ({ frame }) => {
  const progress = beatProgress(frame, MOTTO_FROM, MOTTO_TO);
  const eased = Easing.out(Easing.cubic)(progress);
  const opacity = interpolate(frame, [MOTTO_FROM, MOTTO_FROM + MOTTO_FADE], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  const spacing = 0.06 * (1 - eased);

  return (
    <div
      style={{
        position: "absolute",
        top: LAYOUT.mottoCenterY - LAYOUT.mottoFontSize / 2,
        width: "100%",
        textAlign: "center",
        fontFamily: FONTS.motto,
        fontWeight: 600,
        fontStyle: "italic",
        fontSize: LAYOUT.mottoFontSize,
        color: COLORS.motto,
        letterSpacing: `${spacing}em`,
        opacity,
      }}
    >
      Let the games run themselves.
    </div>
  );
};
