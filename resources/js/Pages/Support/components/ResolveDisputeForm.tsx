import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { CheckCircle } from 'lucide-react';
import type { MatchPlayer } from '@/types';

interface ResolveDisputeFormProps {
    disputeId: number;
    player1: MatchPlayer | null;
    player2: MatchPlayer | null;
    initialPlayer1Score: number;
    initialPlayer2Score: number;
    bestOf: number;
}

export function ResolveDisputeForm({
    disputeId,
    player1,
    player2,
    initialPlayer1Score,
    initialPlayer2Score,
    bestOf,
}: ResolveDisputeFormProps) {
    const [player1Score, setPlayer1Score] = useState(initialPlayer1Score);
    const [player2Score, setPlayer2Score] = useState(initialPlayer2Score);
    const [notes, setNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const maxScore = Math.ceil(bestOf / 2);

    const isValidScore =
        (player1Score === maxScore || player2Score === maxScore) &&
        player1Score !== player2Score &&
        player1Score >= 0 &&
        player2Score >= 0 &&
        player1Score <= maxScore &&
        player2Score <= maxScore;

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

    const handleResolve = () => {
        setIsSubmitting(true);
        router.post(
            `/support/disputes/${disputeId}/resolve`,
            { player1_score: player1Score, player2_score: player2Score, notes: notes || null },
            { onFinish: () => setIsSubmitting(false) }
        );
    };

    return (
        <Card className="border-[#004E86]/20 bg-gradient-to-b from-[#004E86]/5 to-transparent">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <CheckCircle className="size-4 text-[#004E86]" />
                    Resolve Dispute
                </CardTitle>
                <CardDescription>Enter the correct final score</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div className="space-y-2">
                        <Label htmlFor="player1_score" className="text-xs flex items-center gap-1">
                            <Avatar className="size-4">
                                <AvatarImage src={player1?.player_profile?.photo_url || undefined} />
                                <AvatarFallback className="text-[6px]">{getPlayerInitials(player1)}</AvatarFallback>
                            </Avatar>
                            {getPlayerName(player1).split(' ')[0]}
                        </Label>
                        <Input
                            id="player1_score"
                            type="number"
                            min={0}
                            max={maxScore}
                            value={player1Score}
                            onChange={(e) => setPlayer1Score(parseInt(e.target.value) || 0)}
                            className="text-center text-xl font-bold h-14"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="player2_score" className="text-xs flex items-center gap-1">
                            <Avatar className="size-4">
                                <AvatarImage src={player2?.player_profile?.photo_url || undefined} />
                                <AvatarFallback className="text-[6px]">{getPlayerInitials(player2)}</AvatarFallback>
                            </Avatar>
                            {getPlayerName(player2).split(' ')[0]}
                        </Label>
                        <Input
                            id="player2_score"
                            type="number"
                            min={0}
                            max={maxScore}
                            value={player2Score}
                            onChange={(e) => setPlayer2Score(parseInt(e.target.value) || 0)}
                            className="text-center text-xl font-bold h-14"
                        />
                    </div>
                </div>

                <p className="text-xs text-muted-foreground text-center">
                    Best of {bestOf} — First to {maxScore} wins
                </p>

                <div className="space-y-2">
                    <Label htmlFor="notes" className="text-xs">Resolution Notes</Label>
                    <Textarea
                        id="notes"
                        placeholder="Explain the resolution decision..."
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        rows={3}
                        className="resize-none"
                    />
                </div>

                <Button
                    className="w-full bg-[#004E86] hover:bg-[#003D6B]"
                    onClick={handleResolve}
                    disabled={!isValidScore || isSubmitting}
                >
                    {isSubmitting ? (
                        <>
                            <span className="animate-spin mr-2">⏳</span>
                            Resolving...
                        </>
                    ) : (
                        <>
                            <CheckCircle className="size-4 mr-2" />
                            Resolve Dispute
                        </>
                    )}
                </Button>

                {!isValidScore && (
                    <p className="text-xs text-red-500 text-center">
                        Invalid score — one player must reach {maxScore} to win
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
