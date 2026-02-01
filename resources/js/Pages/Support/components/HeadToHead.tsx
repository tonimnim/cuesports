import { Separator } from '@/components/ui/separator';
import { Scale } from 'lucide-react';
import type { HeadToHead as HeadToHeadType, MatchPlayer } from '@/types';

interface HeadToHeadProps {
    headToHead: HeadToHeadType;
    player1: MatchPlayer | null;
    player2: MatchPlayer | null;
}

export function HeadToHead({ headToHead, player1, player2 }: HeadToHeadProps) {
    const getPlayerName = (player: MatchPlayer | null) => {
        if (!player?.player_profile) return 'Unknown';
        const p = player.player_profile;
        return p.nickname || `${p.first_name} ${p.last_name}`;
    };

    if (headToHead.total === 0) return null;

    return (
        <>
            <Separator className="my-6" />
            <div className="rounded-lg bg-gradient-to-r from-blue-50 via-slate-50 to-purple-50 p-4">
                <div className="flex items-center justify-center gap-8">
                    <div className="text-center">
                        <p className="text-3xl font-bold text-blue-700">{headToHead.player1_wins}</p>
                        <p className="text-xs text-muted-foreground">
                            {getPlayerName(player1).split(' ')[0]} Wins
                        </p>
                    </div>
                    <div className="text-center">
                        <Scale className="size-6 text-[#C9A227] mx-auto mb-1" />
                        <p className="text-sm font-medium">Head to Head</p>
                        <p className="text-xs text-muted-foreground">{headToHead.total} matches</p>
                    </div>
                    <div className="text-center">
                        <p className="text-3xl font-bold text-purple-700">{headToHead.player2_wins}</p>
                        <p className="text-xs text-muted-foreground">
                            {getPlayerName(player2).split(' ')[0]} Wins
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
