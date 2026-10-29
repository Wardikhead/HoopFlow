import { Audio, Sequence, staticFile } from "remotion";
import { BEATS, IMPACT_FRAME } from "../timeline";

// The sources peak at -14 dBFS (whoosh) and -10 dBFS (swish). A music-less
// splash is its own soundtrack, so it must be mastered near -18 LUFS, not left
// at the conservative source level: 3.0 puts the delivered peak near -3.6 dBFS.
// Values above 1 need the Web Audio API, hence `useWebAudioApi` below.
const VOLUME = 3;

export const Sfx: React.FC = () => (
  <>
    <Sequence from={BEATS.reveal[0]}>
      <Audio
        src={staticFile("sfx/whoosh.wav")}
        volume={VOLUME}
        useWebAudioApi
        crossOrigin="anonymous"
      />
    </Sequence>
    <Sequence from={IMPACT_FRAME}>
      <Audio
        src={staticFile("sfx/swish.wav")}
        volume={VOLUME}
        useWebAudioApi
        crossOrigin="anonymous"
      />
    </Sequence>
  </>
);
