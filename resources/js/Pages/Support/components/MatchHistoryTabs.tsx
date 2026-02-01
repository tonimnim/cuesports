import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { History } from 'lucide-react';
import type { MatchHistoryEntry, MatchPlayer } from '@/types';

interface MatchHistoryTabsProps {
    player1: MatchPlayer | null;
    player2: MatchPlayer | null;
    player1Matches: MatchHistoryEntry[];
    player2Matches: MatchHistoryEntry[];
}

export function MatchHistoryTabs({
    player1,
    player2,
    player1Matches,
    player2Matches,
}: MatchHistoryTabsProps) {
    const getPlayerName = (player: MatchPlayer | null) => {
        if (!player?.player_profile) return 'Unknown';
        const p = player.player_profile;
        return p.nickname || `${p.first_name} ${p.last_name}`;
    };

    const getPlayerInitials = (player: MatchPlayer | null) => {
        if (!player?.player_profile) return 'U';
        const p = player.player_profile;
        return `${p.first_name?.[0] || ''}${p.last_name?.[0] || ''}`.toUpperCase() || 'U';
    };

    const renderMatchList = (matches: MatchHistoryEntry[]) => {
        if (matches.length === 0) {
            return <div className="text-center py-8 text-muted-foreground">No recent matches</div>;
        }

        return (
            <div className="space-y-2">
                {matches.map((match) => (
                    <div
                        key={match.id}
                        className={`flex items-center justify-between p-3 rounded-lg border ${
                            match.won ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'
                        }`}
                    >
                        <div className="flex items-center gap-3">
                            <Badge className={match.won ? 'bg-green-600 hover:bg-green-600' : 'bg-red-600 hover:bg-red-600'}>
                                {match.won ? 'W' : 'L'}
                            </Badge>
                            <div>
                                <p className="font-medium text-sm">vs {match.opponent_name}</p>
                                <p className="text-xs text-muted-foreground">
                                    {match.tournament_name} - {match.round_name}
                                </p>
                            </div>
                        </div>
                        <div className="text-right">
                            <p className="font-mono font-bold">{match.score}</p>
                            <p className={`text-xs font-medium ${match.rating_change >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                {match.rating_change >= 0 ? '+' : ''}{match.rating_change}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <History className="size-5 text-[#004E86]" />
                    Recent Match History
                </CardTitle>
                <CardDescription>View recent performance of both players</CardDescription>
            </CardHeader>
            <CardContent>
                <Tabs defaultValue="player1" className="w-full">
                    <TabsList className="grid w-full grid-cols-2 mb-4">
                        <TabsTrigger value="player1" className="gap-2">
                            <Avatar className="size-5">
                                <AvatarImage src={player1?.player_profile?.photo_url || undefined} />
                                <AvatarFallback className="text-[8px]">{getPlayerInitials(player1)}</AvatarFallback>
                            </Avatar>
                            {getPlayerName(player1).split(' ')[0]}
                        </TabsTrigger>
                        <TabsTrigger value="player2" className="gap-2">
                            <Avatar className="size-5">
                                <AvatarImage src={player2?.player_profile?.photo_url || undefined} />
                                <AvatarFallback className="text-[8px]">{getPlayerInitials(player2)}</AvatarFallback>
                            </Avatar>
                            {getPlayerName(player2).split(' ')[0]}
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="player1">
                        <ScrollArea className="h-[280px]">{renderMatchList(player1Matches)}</ScrollArea>
                    </TabsContent>
                    <TabsContent value="player2">
                        <ScrollArea className="h-[280px]">{renderMatchList(player2Matches)}</ScrollArea>
                    </TabsContent>
                </Tabs>
            </CardContent>
        </Card>
    );
}
