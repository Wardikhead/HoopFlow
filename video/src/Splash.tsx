import { AbsoluteFill, useCurrentFrame } from "remotion";
import { BracketMark } from "./components/BracketMark";
import { CourtBackdrop } from "./components/CourtBackdrop";
import { Motto } from "./components/Motto";
import { PulseRing } from "./components/PulseRing";
import { Sfx } from "./components/Sfx";
import { Wordmark } from "./components/Wordmark";
import "./fonts";
import { ballDrop, ballSquash } from "./lib/ballMotion";
import { LAYOUT } from "./theme";
import { BALL_FADE, BEATS, REVEAL_GROUPS, beatProgress, type RevealGroupName } from "./timeline";

const [BACKDROP_FROM, BACKDROP_TO] = BEATS.backdrop;
const END_FRAME = BEATS.hold[1];

export const Splash: React.FC = () => {
  const frame = useCurrentFrame();
  const glow = beatProgress(frame, BACKDROP_FROM, BACKDROP_TO);
  const drift = -40 * beatProgress(frame, BACKDROP_FROM, END_FRAME);

  const reveal = REVEAL_GROUPS.reduce(
    (acc, group) => ({ ...acc, [group.name]: beatProgress(frame, group.from, group.to) }),
    {} as Record<RevealGroupName, number>,
  );

  const ballOpacity = beatProgress(frame, BALL_FADE[0], BALL_FADE[1]);

  return (
    <AbsoluteFill>
      <CourtBackdrop glow={glow} drift={drift} />
      <AbsoluteFill
        style={{
          justifyContent: "flex-start",
          alignItems: "center",
          paddingTop: LAYOUT.iconTop,
        }}
      >
        <div style={{ position: "relative" }}>
          <BracketMark
            reveal={reveal}
            drop={ballDrop(frame)}
            squash={ballSquash(frame)}
            ballOpacity={ballOpacity}
          />
          <div style={{ position: "absolute", inset: 0 }}>
            <PulseRing frame={frame} />
          </div>
        </div>
      </AbsoluteFill>
      <Wordmark frame={frame} />
      <Motto frame={frame} />
      <Sfx />
    </AbsoluteFill>
  );
};
