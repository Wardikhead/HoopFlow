import { BracketMark } from "../components/BracketMark";
import { REVEAL_GROUPS, type RevealGroupName } from "../timeline";

export type IconCompareProps = {
  reveal: number;
  drop: number;
  ballOpacity?: number;
};

export const IconCompare: React.FC<IconCompareProps> = ({ reveal, drop, ballOpacity = 1 }) => {
  const revealMap = REVEAL_GROUPS.reduce(
    (acc, group) => ({ ...acc, [group.name]: reveal }),
    {} as Record<RevealGroupName, number>,
  );

  return (
    <BracketMark
      reveal={revealMap}
      drop={drop}
      squash={0}
      ballOpacity={ballOpacity}
      width={302}
    />
  );
};
