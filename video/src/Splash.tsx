import { AbsoluteFill, useCurrentFrame } from "remotion";
import { CourtBackdrop } from "./components/CourtBackdrop";
import { BEATS, beatProgress } from "./timeline";

const [BACKDROP_FROM, BACKDROP_TO] = BEATS.backdrop;
const END_FRAME = BEATS.hold[1];

export const Splash: React.FC = () => {
  const frame = useCurrentFrame();
  const glow = beatProgress(frame, BACKDROP_FROM, BACKDROP_TO);
  const drift = -40 * beatProgress(frame, BACKDROP_FROM, END_FRAME);

  return (
    <AbsoluteFill>
      <CourtBackdrop glow={glow} drift={drift} />
    </AbsoluteFill>
  );
};
