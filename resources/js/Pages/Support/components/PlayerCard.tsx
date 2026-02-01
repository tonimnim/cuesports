import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { FileText, AlertTriangle, Shield } from 'lucide-react';
import type { MatchPlayer, PlayerDisputeStats } from '@/types';

interface PlayerCardProps {
    player: MatchPlayer | null;
    disputeStats: PlayerDisputeStats;
    isSubmitter?: boolean;
    isDisputer?: boolean;
    variant?: 'blue' | 'purple';
}

export function PlayerCard({
    player,
    disputeStats,
    isSubmitter,
    isDisputer,
    variant = 'blue',
}: PlayerCardProps) {
    const getPlayerName = () => {
        if (!player?.player_profile) return 'Unknown';
        const p = player.player_profile;
        return p.nickname || `${p.first_name} ${p.last_name}`;
    };

    const getPlayerInitials = () => {
        if (!player?.player_profile) return 'U';
        const p = player.player_profile;
        return `${p.first_name?.[0] || ''}${p.last_name?.[0] || ''}`.toUpperCase() || 'U';
    };

    const getRatingCategoryColor = (category: string | undefined) => {
        switch (category?.toLowerCase()) {
            case 'pro':
                return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'advanced':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'intermediate':
                return 'bg-green-100 text-green-800 border-green-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const getDisputeRiskLevel = () => {
        const total = disputeStats.disputes_filed + disputeStats.disputes_against;
        if (total === 0) return { label: 'No history', color: 'text-green-600' };
        if (disputeStats.disputes_filed > 5 || disputeStats.disputes_lost > 3)
            return { label: 'Frequent disputer', color: 'text-red-600' };
        if (disputeStats.disputes_filed > 2)
            return { label: 'Some disputes', color: 'text-yellow-600' };
        return { label: 'Clean record', color: 'text-green-600' };
    };

    const risk = getDisputeRiskLevel();
    const colors = variant === 'blue'
        ? { ring: 'ring-blue-200', bg: 'bg-blue-100', text: 'text-blue-700' }
        : { ring: 'ring-purple-200', bg: 'bg-purple-100', text: 'text-purple-700' };

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-4">
                <div className="relative">
                    <Avatar className={`size-16 ring-2 ${colors.ring}`}>
                        <AvatarImage
                            src={player?.player_profile?.photo_url || undefined}
                            alt={getPlayerName()}
                        />
                        <AvatarFallback className={`${colors.bg} ${colors.text} text-lg`}>
                            {getPlayerInitials()}
                        </AvatarFallback>
                    </Avatar>
                    {isSubmitter && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="absolute -bottom-1 -right-1 rounded-full bg-blue-600 p-1">
                                    <FileText className="size-3 text-white" />
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>Score submitter</TooltipContent>
                        </Tooltip>
                    )}
                    {isDisputer && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="absolute -bottom-1 -right-1 rounded-full bg-orange-600 p-1">
                                    <AlertTriangle className="size-3 text-white" />
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>Dispute initiator</TooltipContent>
                        </Tooltip>
                    )}
                </div>
                <div>
                    <HoverCard>
                        <HoverCardTrigger asChild>
                            <p className="font-semibold text-lg cursor-pointer hover:text-[#004E86]">
                                {getPlayerName()}
                            </p>
                        </HoverCardTrigger>
                        <HoverCardContent className="w-80">
                            <div className="space-y-2">
                                <h4 className="font-semibold">Player Profile</h4>
                                <div className="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span className="text-muted-foreground">Matches:</span>{' '}
                                        {player?.player_profile?.total_matches || 0}
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">W/L:</span>{' '}
                                        {player?.player_profile?.wins || 0}/{player?.player_profile?.losses || 0}
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Best:</span>{' '}
                                        {player?.player_profile?.best_rating || '-'}
                                    </div>
                                </div>
                            </div>
                        </HoverCardContent>
                    </HoverCard>
                    <div className="flex items-center gap-2 mt-1">
                        <span className="text-2xl font-bold text-[#004E86]">
                            {player?.player_profile?.rating || 0}
                        </span>
                        <Badge
                            variant="outline"
                            className={getRatingCategoryColor(player?.player_profile?.rating_category)}
                        >
                            {player?.player_profile?.rating_category || 'Beginner'}
                        </Badge>
                    </div>
                </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-3 gap-2">
                <div className="rounded-lg bg-slate-50 p-3 text-center">
                    <p className="text-lg font-bold">{player?.player_profile?.total_matches || 0}</p>
                    <p className="text-xs text-muted-foreground">Matches</p>
                </div>
                <div className="rounded-lg bg-green-50 p-3 text-center">
                    <p className="text-lg font-bold text-green-700">{player?.player_profile?.wins || 0}</p>
                    <p className="text-xs text-muted-foreground">Wins</p>
                </div>
                <div className="rounded-lg bg-red-50 p-3 text-center">
                    <p className="text-lg font-bold text-red-700">{player?.player_profile?.losses || 0}</p>
                    <p className="text-xs text-muted-foreground">Losses</p>
                </div>
            </div>

            {/* Dispute History */}
            <div className="rounded-lg border p-3">
                <div className="flex items-center justify-between mb-2">
                    <span className="text-sm font-medium flex items-center gap-1">
                        <Shield className="size-4" />
                        Dispute History
                    </span>
                    <span className={`text-xs font-medium ${risk.color}`}>{risk.label}</span>
                </div>
                <div className="grid grid-cols-2 gap-2 text-xs">
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Filed:</span>
                        <span className="font-medium">{disputeStats.disputes_filed}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Won:</span>
                        <span className="font-medium text-green-600">{disputeStats.disputes_won}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Against:</span>
                        <span className="font-medium">{disputeStats.disputes_against}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">Lost:</span>
                        <span className="font-medium text-red-600">{disputeStats.disputes_lost}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}
