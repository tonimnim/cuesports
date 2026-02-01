import { Head, Link } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { TooltipProvider } from '@/components/ui/tooltip';
import {
    ArrowLeft,
    AlertTriangle,
    Trophy,
    Clock,
    FileText,
    TrendingUp,
    TrendingDown,
    AlertCircle,
    ChevronRight,
    Swords,
} from 'lucide-react';
import {
    PlayerCard,
    EvidenceGallery,
    MatchHistoryTabs,
    ResolveDisputeForm,
    HeadToHead,
} from '../components';
import type {
    Dispute,
    MatchEvidence,
    PlayerDisputeStats,
    HeadToHead as HeadToHeadType,
    MatchHistoryEntry,
} from '@/types';

interface RatingPreview {
    player1: { current_rating: number; new_rating: number; change: number };
    player2: { current_rating: number; new_rating: number; change: number };
}

interface Props {
    dispute: Dispute;
    evidence: MatchEvidence[];
    ratingPreview: RatingPreview | null;
    tournament: { id: number; name: string; best_of: number };
    player1DisputeStats: PlayerDisputeStats;
    player2DisputeStats: PlayerDisputeStats;
    headToHead: HeadToHeadType;
    player1RecentMatches: MatchHistoryEntry[];
    player2RecentMatches: MatchHistoryEntry[];
}

export default function DisputeShow({
    dispute,
    evidence,
    ratingPreview,
    tournament,
    player1DisputeStats,
    player2DisputeStats,
    headToHead,
    player1RecentMatches,
    player2RecentMatches,
}: Props) {
    const getPlayerName = (player: Dispute['player1']) => {
        if (!player?.player_profile) return 'Unknown';
        const p = player.player_profile;
        return p.nickname || `${p.first_name} ${p.last_name}`;
    };

    const getPlayerInitials = (player: Dispute['player1']) => {
        if (!player?.player_profile) return 'U';
        const p = player.player_profile;
        return `${p.first_name?.[0] || ''}${p.last_name?.[0] || ''}`.toUpperCase() || 'U';
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    };

    const formatRelativeTime = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        const diffMs = Date.now() - new Date(dateString).getTime();
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        if (diffHours < 1) return 'Just now';
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffHours < 168) return `${Math.floor(diffHours / 24)}d ago`;
        return formatDate(dateString);
    };

    const maxScore = Math.ceil(tournament.best_of / 2);
    const isOldDispute = dispute.disputed_at && Date.now() - new Date(dispute.disputed_at).getTime() > 86400000;

    return (
        <SupportLayout>
            <Head title={`Dispute #${dispute.id}`} />
            <TooltipProvider>
                <div className="space-y-6">
                    {/* Header */}
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/support/disputes"><ArrowLeft className="size-5" /></Link>
                        </Button>
                        <div className="flex-1">
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight">Dispute #{dispute.id}</h1>
                                <Badge variant="outline" className="bg-orange-50 text-orange-700 border-orange-200">
                                    <AlertTriangle className="size-3 mr-1" />
                                    Pending Resolution
                                </Badge>
                            </div>
                            <p className="text-muted-foreground">{dispute.tournament.name} - {dispute.round_name}</p>
                        </div>
                        <div className="text-right text-sm text-muted-foreground">
                            <p>Disputed {formatRelativeTime(dispute.disputed_at)}</p>
                        </div>
                    </div>

                    {/* Urgency Alert */}
                    {isOldDispute && (
                        <Alert className="border-orange-200 bg-orange-50">
                            <AlertCircle className="size-4 text-orange-600" />
                            <AlertDescription className="text-orange-800">
                                This dispute has been pending for over 24 hours. Please prioritize resolution.
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="grid gap-6 xl:grid-cols-4">
                        {/* Main Content */}
                        <div className="xl:col-span-3 space-y-6">
                            {/* Players Card */}
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="flex items-center gap-2">
                                        <Swords className="size-5 text-[#004E86]" />
                                        Match Participants
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-8 md:grid-cols-2">
                                        <PlayerCard
                                            player={dispute.player1}
                                            disputeStats={player1DisputeStats}
                                            isSubmitter={dispute.submitted_by?.id === dispute.player1?.id}
                                            isDisputer={dispute.disputed_by?.id === dispute.player1?.id}
                                            variant="blue"
                                        />
                                        <PlayerCard
                                            player={dispute.player2}
                                            disputeStats={player2DisputeStats}
                                            isSubmitter={dispute.submitted_by?.id === dispute.player2?.id}
                                            isDisputer={dispute.disputed_by?.id === dispute.player2?.id}
                                            variant="purple"
                                        />
                                    </div>
                                    <HeadToHead headToHead={headToHead} player1={dispute.player1} player2={dispute.player2} />
                                </CardContent>
                            </Card>

                            {/* Dispute Details */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <AlertTriangle className="size-5 text-orange-500" />
                                        Dispute Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {/* Submitted Score */}
                                    <div className="rounded-lg border-2 border-dashed border-slate-200 p-6">
                                        <p className="text-sm font-medium text-muted-foreground mb-3 text-center">
                                            Contested Score (submitted by {getPlayerName(dispute.submitted_by)})
                                        </p>
                                        <div className="flex items-center justify-center gap-8">
                                            <div className="text-center">
                                                <Avatar className="size-10 mx-auto mb-2">
                                                    <AvatarImage src={dispute.player1?.player_profile?.photo_url || undefined} />
                                                    <AvatarFallback className="bg-blue-100 text-blue-700">
                                                        {getPlayerInitials(dispute.player1)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <p className="text-4xl font-bold">{dispute.player1_score}</p>
                                                <p className="text-xs text-muted-foreground mt-1">{getPlayerName(dispute.player1).split(' ')[0]}</p>
                                            </div>
                                            <div className="text-3xl text-muted-foreground font-light">—</div>
                                            <div className="text-center">
                                                <Avatar className="size-10 mx-auto mb-2">
                                                    <AvatarImage src={dispute.player2?.player_profile?.photo_url || undefined} />
                                                    <AvatarFallback className="bg-purple-100 text-purple-700">
                                                        {getPlayerInitials(dispute.player2)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <p className="text-4xl font-bold">{dispute.player2_score}</p>
                                                <p className="text-xs text-muted-foreground mt-1">{getPlayerName(dispute.player2).split(' ')[0]}</p>
                                            </div>
                                        </div>
                                        <p className="text-xs text-center text-muted-foreground mt-4">
                                            Submitted on {formatDate(dispute.submitted_at)}
                                        </p>
                                    </div>

                                    {/* Dispute Reason */}
                                    <div className="rounded-lg border border-orange-200 bg-orange-50 p-4">
                                        <div className="flex items-start gap-3">
                                            <AlertTriangle className="size-5 text-orange-600 mt-0.5 flex-shrink-0" />
                                            <div className="flex-1">
                                                <p className="text-sm font-semibold text-orange-800 mb-1">Reason for Dispute</p>
                                                <p className="text-orange-900">{dispute.dispute_reason || 'No reason provided'}</p>
                                                <p className="text-xs text-orange-700 mt-3">
                                                    Filed by {getPlayerName(dispute.disputed_by)} on {formatDate(dispute.disputed_at)}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Rating Impact Preview */}
                                    {ratingPreview && (
                                        <div className="rounded-lg border p-4 bg-slate-50">
                                            <p className="text-sm font-medium text-muted-foreground mb-3">
                                                Rating Impact (if submitted score is accepted)
                                            </p>
                                            <div className="grid gap-3 md:grid-cols-2">
                                                {[
                                                    { player: dispute.player1, preview: ratingPreview.player1 },
                                                    { player: dispute.player2, preview: ratingPreview.player2 },
                                                ].map(({ player, preview }, i) => (
                                                    <div key={i} className="flex items-center justify-between rounded-lg bg-white p-3 border">
                                                        <span className="text-sm font-medium">{getPlayerName(player).split(' ')[0]}</span>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-sm text-muted-foreground">{preview.current_rating}</span>
                                                            <ChevronRight className="size-4 text-muted-foreground" />
                                                            <span className="font-bold">{preview.new_rating}</span>
                                                            <Badge className={preview.change >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}>
                                                                {preview.change >= 0 ? <TrendingUp className="size-3 mr-1" /> : <TrendingDown className="size-3 mr-1" />}
                                                                {preview.change >= 0 ? '+' : ''}{preview.change}
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <EvidenceGallery evidence={evidence} />

                            <MatchHistoryTabs
                                player1={dispute.player1}
                                player2={dispute.player2}
                                player1Matches={player1RecentMatches}
                                player2Matches={player2RecentMatches}
                            />
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Match Info */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Trophy className="size-4 text-[#C9A227]" />
                                        Match Info
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Tournament</span>
                                        <span className="font-medium text-right max-w-[140px] truncate">{dispute.tournament.name}</span>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Round</span>
                                        <span className="font-medium">{dispute.round_name}</span>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Match Type</span>
                                        <Badge variant="outline" className="capitalize">{dispute.match_type?.replace('_', ' ')}</Badge>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Format</span>
                                        <span className="font-medium">Best of {tournament.best_of}</span>
                                    </div>
                                    <Separator />
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">First to</span>
                                        <span className="font-bold text-[#004E86]">{maxScore}</span>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Timeline */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Clock className="size-4" />
                                        Timeline
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex gap-3">
                                            <div className="flex flex-col items-center">
                                                <div className="flex size-8 items-center justify-center rounded-full bg-blue-100">
                                                    <FileText className="size-4 text-blue-600" />
                                                </div>
                                                <div className="h-full w-px bg-slate-200" />
                                            </div>
                                            <div className="pb-4">
                                                <p className="font-medium text-sm">Score Submitted</p>
                                                <p className="text-xs text-muted-foreground">{formatDate(dispute.submitted_at)}</p>
                                                <p className="text-xs text-muted-foreground mt-1">by {getPlayerName(dispute.submitted_by)}</p>
                                            </div>
                                        </div>
                                        <div className="flex gap-3">
                                            <div className="flex size-8 items-center justify-center rounded-full bg-orange-100">
                                                <AlertTriangle className="size-4 text-orange-600" />
                                            </div>
                                            <div>
                                                <p className="font-medium text-sm">Disputed</p>
                                                <p className="text-xs text-muted-foreground">{formatDate(dispute.disputed_at)}</p>
                                                <p className="text-xs text-muted-foreground mt-1">by {getPlayerName(dispute.disputed_by)}</p>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <ResolveDisputeForm
                                disputeId={dispute.id}
                                player1={dispute.player1}
                                player2={dispute.player2}
                                initialPlayer1Score={dispute.player1_score ?? 0}
                                initialPlayer2Score={dispute.player2_score ?? 0}
                                bestOf={tournament.best_of}
                            />
                        </div>
                    </div>
                </div>
            </TooltipProvider>
        </SupportLayout>
    );
}
