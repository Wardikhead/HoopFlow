import { Composition } from "remotion";
import { IconCompare } from "./comps/IconCompare";
import { Splash } from "./Splash";
import { LAYOUT } from "./theme";
import { DURATION_IN_FRAMES, FPS } from "./timeline";

export const RemotionRoot: React.FC = () => (
  <>
    <Composition
      id="HoopFlowSplash"
      component={Splash}
      durationInFrames={DURATION_IN_FRAMES}
      fps={FPS}
      width={LAYOUT.width}
      height={LAYOUT.height}
    />
    <Composition
      id="IconCompare"
      component={IconCompare}
      durationInFrames={1}
      fps={FPS}
      width={302}
      height={292}
      defaultProps={{ reveal: 1, drop: 0 }}
    />
  </>
);
