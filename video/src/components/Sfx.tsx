import { Audio, Sequence, staticFile } from "remotion";
import { BEATS, IMPACT_FRAME } from "../timeline";

const VOLUME = 0.5;

export const Sfx: React.FC = () => (
  <>
    <Sequence from={BEATS.reveal[0]}>
      <Audio src={staticFile("sfx/whoosh.wav")} volume={VOLUME} />
    </Sequence>
    <Sequence from={IMPACT_FRAME}>
      <Audio src={staticFile("sfx/swish.wav")} volume={VOLUME} />
    </Sequence>
  </>
);
