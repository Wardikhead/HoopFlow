import { spring, useVideoConfig } from "remotion";
import { COLORS, FONTS, LAYOUT } from "../theme";
import { LETTER_STAGGER, WORDMARK_START, WORDMARK_TEXT, letterDelays } from "../timeline";

const letters = Array.from(WORDMARK_TEXT);
const HOOP_LENGTH = 4;

export const Wordmark: React.FC<{ frame: number }> = ({ frame }) => {
  const { fps } = useVideoConfig();
  const delays = letterDelays(WORDMARK_TEXT, WORDMARK_START, LETTER_STAGGER);

  return (
    <div
      style={{
        position: "absolute",
        top: LAYOUT.wordmarkCenterY - LAYOUT.wordmarkFontSize / 2,
        width: "100%",
        display: "flex",
        justifyContent: "center",
        fontFamily: FONTS.display,
        fontWeight: 800,
        fontSize: LAYOUT.wordmarkFontSize,
        letterSpacing: "-0.02em",
        lineHeight: 1,
      }}
    >
      {letters.map((letter, index) => {
        const progress = spring({
          frame: frame - delays[index],
          fps,
          config: { damping: 12, stiffness: 120, mass: 0.7 },
        });
        return (
          <span
            key={`${letter}-${index}`}
            style={{
              color: index < HOOP_LENGTH ? COLORS.white : COLORS.orange,
              display: "inline-block",
              transform: `translateY(${(1 - progress) * 46}px)`,
              opacity: Math.min(1, Math.max(0, progress * 2)),
            }}
          >
            {letter}
          </span>
        );
      })}
    </div>
  );
};
