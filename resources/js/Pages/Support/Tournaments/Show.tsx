import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    ArrowLeft,
    Calendar,
    Trophy,
    Users,
    Building2,
    Swords,
    History,
    XCircle,
    MapPin,
    DollarSign,
    Clock,
    Layers,
    AlertTriangle,
    Play,
    Loader2,
} from 'lucide-react';

interface CanStartInfo {
    can_start: boolean;
    status: string;
    participants_count: number;
    min_participants: number;
    reasons: string[];
}

interface Tournament {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    rules: string | null;
    status: string;
    type: string;
    format: string;
    race_to: number | null;
    finals_race_to: number | null;
    participants_count: number;
    max_participants: number | null;
    prize_pool: number | null;
    entry_fee: number | null;
    starts_at: string | null;
    ends_at: string | null;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    venue_name: string | null;
    venue_address: string | null;
    created_at: string;
    organizer: {
        id: number;
        organization_name: string;
        logo_url: string | null;
    } | null;
    location: {
        id: number;
        name: string;
    } | null;
    stages: {
        id: number;
        name: string;
        type: string;
        status: string;
        order: number;
    }[];
    can_start?: CanStartInfo;
}

interface Participant {
    id: number;
    seed: number | null;
    status: string;
    player: {
        id: number;
        name: string;
        photo_url: string | null;
        rating: number;
    } | null;
    registered_at: string;
}

interface Match {
    id: number;
    player1_name: string;
    player2_name: string;
    player1_score: number | null;
    player2_score: number | null;
    status: string;
    match_type: string | null;
    round_name: string | null;
    played_at: string | null;
}

interface ActivityLogItem {
    id: number;
    action: string;
    action_label: string;
    description: string;
    performed_by: { id: number; email: string } | null;
    created_at: string;
}

interface Props {
    tournament: Tournament;
    participants: Participant[];
    recentMatches: Match[];
    activityLog: ActivityLogItem[];
}

export default function TournamentShow({
    tournament,
    participants,
    recentMatches,
    activityLog,
}: Props) {
    const [showCancelDialog, setShowCancelDialog] = useState(false);
    const [showStartDialog, setShowStartDialog] = useState(false);
    const [cancelReason, setCancelReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isStarting, setIsStarting] = useState(false);

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const formatDateTime = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'draft':
                return 'bg-gray-100 text-gray-700 border-gray-200';
            case 'upcoming':
                return 'bg-blue-100 text-blue-700 border-blue-200';
            case 'registration_open':
                return 'bg-green-100 text-green-700 border-green-200';
            case 'registration_closed':
                return 'bg-yellow-100 text-yellow-700 border-yellow-200';
            case 'in_progress':
                return 'bg-purple-100 text-purple-700 border-purple-200';
            case 'completed':
                return 'bg-emerald-100 text-emerald-700 border-emerald-200';
            case 'cancelled':
                return 'bg-red-100 text-red-700 border-red-200';
            default:
                return 'bg-gray-100 text-gray-700 border-gray-200';
        }
    };

    const getMatchStatusColor = (status: string) => {
        switch (status) {
            case 'scheduled':
                return 'bg-blue-100 text-blue-700';
            case 'pending_confirmation':
                return 'bg-yellow-100 text-yellow-700';
            case 'completed':
                return 'bg-green-100 text-green-700';
            case 'disputed':
                return 'bg-red-100 text-red-700';
            default:
                return 'bg-gray-100 text-gray-700';
        }
    };

    const getParticipantStatusColor = (status: string) => {
        switch (status) {
            case 'registered':
                return 'bg-green-100 text-green-700';
            case 'checked_in':
                return 'bg-blue-100 text-blue-700';
            case 'withdrawn':
                return 'bg-red-100 text-red-700';
            case 'eliminated':
                return 'bg-gray-100 text-gray-700';
            default:
                return 'bg-gray-100 text-gray-700';
        }
    };

    const formatStatus = (status: string) => {
        return status.split('_').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    };

    const formatCurrency = (amount: number | null) => {
        if (!amount) return '-';
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN',
        }).format(amount);
    };

    const handleCancel = () => {
        setIsSubmitting(true);
        router.post(
            `/support/tournaments/${tournament.id}/cancel`,
            { reason: cancelReason },
            {
                onSuccess: () => setShowCancelDialog(false),
                onFinish: () => {
                    setIsSubmitting(false);
                    setCancelReason('');
                },
            }
        );
    };

    const handleStart = () => {
        setIsStarting(true);
        router.post(
            `/support/tournaments/${tournament.id}/start`,
            {},
            {
                onSuccess: () => setShowStartDialog(false),
                onFinish: () => setIsStarting(false),
            }
        );
    };

    const canCancel = !['cancelled', 'completed'].includes(tournament.status);
    const canStart = tournament.can_start?.can_start ?? false;

    return (
        <SupportLayout>
            <Head title={`Tournament: ${tournament.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/support/tournaments">
                                <ArrowLeft className="size-5" />
                            </Link>
                        </Button>
                        <div className="flex size-12 items-center justify-center rounded-lg bg-primary/10">
                            <Trophy className="size-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{tournament.name}</h1>
                            <p className="text-muted-foreground">/{tournament.slug}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant="outline" className={getStatusColor(tournament.status)}>
                            {formatStatus(tournament.status)}
                        </Badge>
                        <Badge variant="outline" className="capitalize">
                            {tournament.type}
                        </Badge>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Tournament Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Trophy className="size-5 text-primary" />
                                    Tournament Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Stats Grid */}
                                <div className="grid grid-cols-4 gap-4">
                                    <div className="text-center p-3 rounded-lg bg-primary/5 border border-primary/10">
                                        <p className="text-2xl font-bold">{tournament.participants_count}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {tournament.max_participants
                                                ? `of ${tournament.max_participants}`
                                                : 'Participants'}
                                        </p>
                                    </div>
                                    <div className="text-center p-3 rounded-lg bg-slate-50">
                                        <p className="text-2xl font-bold capitalize">
                                            {tournament.format?.replace('_', ' ') || '-'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">Format</p>
                                    </div>
                                    <div className="text-center p-3 rounded-lg bg-slate-50">
                                        <p className="text-2xl font-bold">
                                            {tournament.best_of ? `Bo${tournament.best_of}` : '-'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">Match Type</p>
                                    </div>
                                    <div className="text-center p-3 rounded-lg bg-slate-50">
                                        <p className="text-2xl font-bold">{tournament.stages.length}</p>
                                        <p className="text-xs text-muted-foreground">Stages</p>
                                    </div>
                                </div>

                                {/* Description */}
                                {tournament.description && (
                                    <div>
                                        <h4 className="text-sm font-medium text-muted-foreground mb-2">
                                            Description
                                        </h4>
                                        <p className="text-sm">{tournament.description}</p>
                                    </div>
                                )}

                                {/* Rules */}
                                {tournament.rules && (
                                    <div>
                                        <h4 className="text-sm font-medium text-muted-foreground mb-2">
                                            Rules
                                        </h4>
                                        <p className="text-sm whitespace-pre-wrap">{tournament.rules}</p>
                                    </div>
                                )}

                                {/* Stages */}
                                {tournament.stages.length > 0 && (
                                    <div>
                                        <h4 className="text-sm font-medium text-muted-foreground mb-2">
                                            Tournament Stages
                                        </h4>
                                        <div className="flex flex-wrap gap-2">
                                            {tournament.stages
                                                .sort((a, b) => a.order - b.order)
                                                .map((stage) => (
                                                    <Badge
                                                        key={stage.id}
                                                        variant="outline"
                                                        className={getStatusColor(stage.status)}
                                                    >
                                                        <Layers className="size-3 mr-1" />
                                                        {stage.name} ({stage.type})
                                                    </Badge>
                                                ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Tabs */}
                        <Tabs defaultValue="participants" className="space-y-4">
                            <TabsList className="grid w-full grid-cols-3">
                                <TabsTrigger value="participants" className="gap-1">
                                    <Users className="size-4" />
                                    Participants ({participants.length})
                                </TabsTrigger>
                                <TabsTrigger value="matches" className="gap-1">
                                    <Swords className="size-4" />
                                    Matches
                                </TabsTrigger>
                                <TabsTrigger value="activity" className="gap-1">
                                    <History className="size-4" />
                                    Activity
                                </TabsTrigger>
                            </TabsList>

                            {/* Participants Tab */}
                            <TabsContent value="participants">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Registered Participants</CardTitle>
                                        <CardDescription>
                                            Players registered for this tournament
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {participants.length === 0 ? (
                                            <div className="text-center py-8 text-muted-foreground">
                                                <Users className="size-12 mx-auto mb-2 opacity-50" />
                                                No participants registered yet
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[400px]">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Seed</TableHead>
                                                            <TableHead>Player</TableHead>
                                                            <TableHead>Rating</TableHead>
                                                            <TableHead>Status</TableHead>
                                                            <TableHead>Registered</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {participants.map((participant) => (
                                                            <TableRow key={participant.id}>
                                                                <TableCell>
                                                                    {participant.seed || '-'}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {participant.player ? (
                                                                        <div className="flex items-center gap-2">
                                                                            <Avatar className="size-7">
                                                                                <AvatarImage
                                                                                    src={
                                                                                        participant.player.photo_url ||
                                                                                        undefined
                                                                                    }
                                                                                />
                                                                                <AvatarFallback className="text-xs">
                                                                                    {participant.player.name
                                                                                        .substring(0, 2)
                                                                                        .toUpperCase()}
                                                                                </AvatarFallback>
                                                                            </Avatar>
                                                                            <span className="font-medium">
                                                                                {participant.player.name}
                                                                            </span>
                                                                        </div>
                                                                    ) : (
                                                                        <span className="text-muted-foreground">
                                                                            Unknown Player
                                                                        </span>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {participant.player?.rating || '-'}
                                                                </TableCell>
                                                                <TableCell>
                                                                    <Badge
                                                                        variant="outline"
                                                                        className={getParticipantStatusColor(
                                                                            participant.status
                                                                        )}
                                                                    >
                                                                        {formatStatus(participant.status)}
                                                                    </Badge>
                                                                </TableCell>
                                                                <TableCell>
                                                                    {formatDate(participant.registered_at)}
                                                                </TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            </ScrollArea>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Matches Tab */}
                            <TabsContent value="matches">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Recent Matches</CardTitle>
                                        <CardDescription>Recent matches in this tournament</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {recentMatches.length === 0 ? (
                                            <div className="text-center py-8 text-muted-foreground">
                                                <Swords className="size-12 mx-auto mb-2 opacity-50" />
                                                No matches played yet
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[400px]">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Round</TableHead>
                                                            <TableHead>Player 1</TableHead>
                                                            <TableHead>Score</TableHead>
                                                            <TableHead>Player 2</TableHead>
                                                            <TableHead>Status</TableHead>
                                                            <TableHead>Played</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {recentMatches.map((match) => {
                                                            const isBye = match.match_type === 'bye';
                                                            return (
                                                                <TableRow key={match.id}>
                                                                    <TableCell>
                                                                        {match.round_name || '-'}
                                                                    </TableCell>
                                                                    <TableCell className="font-medium">
                                                                        {match.player1_name}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {isBye ? (
                                                                            <Badge variant="outline" className="bg-gray-100 text-gray-600">
                                                                                BYE
                                                                            </Badge>
                                                                        ) : (
                                                                            <span className="font-mono">
                                                                                {match.player1_score ?? 0} :{' '}
                                                                                {match.player2_score ?? 0}
                                                                            </span>
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell className="font-medium">
                                                                        {isBye ? (
                                                                            <span className="text-muted-foreground">-</span>
                                                                        ) : (
                                                                            match.player2_name
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <Badge
                                                                            className={getMatchStatusColor(match.status)}
                                                                        >
                                                                            {isBye ? 'Walkover' : formatStatus(match.status)}
                                                                        </Badge>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {formatDate(match.played_at)}
                                                                    </TableCell>
                                                                </TableRow>
                                                            );
                                                        })}
                                                    </TableBody>
                                                </Table>
                                            </ScrollArea>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Activity Tab */}
                            <TabsContent value="activity">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Activity Log</CardTitle>
                                        <CardDescription>
                                            Support actions taken on this tournament
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {activityLog.length === 0 ? (
                                            <div className="text-center py-8 text-muted-foreground">
                                                <History className="size-12 mx-auto mb-2 opacity-50" />
                                                No activity logged yet
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[400px]">
                                                <div className="space-y-3">
                                                    {activityLog.map((entry) => (
                                                        <div
                                                            key={entry.id}
                                                            className="flex items-start gap-3 p-3 rounded-lg border"
                                                        >
                                                            <div className="size-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                                                                <History className="size-4 text-primary" />
                                                            </div>
                                                            <div className="flex-1 min-w-0">
                                                                <p className="text-sm font-medium">
                                                                    {entry.action_label}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground truncate">
                                                                    {entry.description}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground mt-1">
                                                                    {entry.performed_by?.email || 'System'} ·{' '}
                                                                    {formatDateTime(entry.created_at)}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </ScrollArea>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Organizer Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Building2 className="size-4" />
                                    Organizer
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {tournament.organizer ? (
                                    <div className="flex items-center gap-3">
                                        <Avatar className="size-10">
                                            <AvatarImage
                                                src={tournament.organizer.logo_url || undefined}
                                            />
                                            <AvatarFallback className="bg-primary/10 text-primary">
                                                <Building2 className="size-5" />
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <p className="font-medium">
                                                {tournament.organizer.organization_name}
                                            </p>
                                            <Link
                                                href={`/support/organizers/${tournament.organizer.id}`}
                                                className="text-xs text-primary hover:underline"
                                            >
                                                View organizer
                                            </Link>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No organizer assigned</p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Schedule Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Calendar className="size-4" />
                                    Schedule
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Start Date</span>
                                    <span className="font-medium">{formatDate(tournament.starts_at)}</span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">End Date</span>
                                    <span className="font-medium">{formatDate(tournament.ends_at)}</span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Reg. Opens</span>
                                    <span className="font-medium">
                                        {formatDate(tournament.registration_opens_at)}
                                    </span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Reg. Closes</span>
                                    <span className="font-medium">
                                        {formatDate(tournament.registration_closes_at)}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Financial Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <DollarSign className="size-4" />
                                    Financials
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Entry Fee</span>
                                    <span className="font-medium">
                                        {formatCurrency(tournament.entry_fee)}
                                    </span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Prize Pool</span>
                                    <span className="font-medium text-green-600">
                                        {formatCurrency(tournament.prize_pool)}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Location Info */}
                        {tournament.location && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base flex items-center gap-2">
                                        <MapPin className="size-4" />
                                        Location
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="font-medium">{tournament.location.name}</p>
                                </CardContent>
                            </Card>
                        )}

                        {/* Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {/* Start Tournament Button */}
                                {canStart && (
                                    <Button
                                        className="w-full bg-green-600 hover:bg-green-700"
                                        onClick={() => setShowStartDialog(true)}
                                    >
                                        <Play className="size-4 mr-2" />
                                        Start Tournament
                                    </Button>
                                )}

                                {/* Show why tournament can't be started */}
                                {tournament.status === 'registration' && !canStart && tournament.can_start && (
                                    <div className="p-3 rounded-lg bg-yellow-50 border border-yellow-200">
                                        <p className="text-sm font-medium text-yellow-800 mb-1">Cannot start yet:</p>
                                        <ul className="text-xs text-yellow-700 list-disc list-inside">
                                            {tournament.can_start.reasons.map((reason, i) => (
                                                <li key={i}>{reason}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {/* Cancel Button */}
                                {canCancel ? (
                                    <Button
                                        variant="destructive"
                                        className="w-full"
                                        onClick={() => setShowCancelDialog(true)}
                                    >
                                        <XCircle className="size-4 mr-2" />
                                        Cancel Tournament
                                    </Button>
                                ) : (
                                    <div className="p-3 rounded-lg bg-muted text-center">
                                        <p className="text-sm text-muted-foreground">
                                            {tournament.status === 'cancelled'
                                                ? 'This tournament has been cancelled.'
                                                : tournament.status === 'active'
                                                ? 'Tournament is in progress.'
                                                : 'This tournament has been completed.'}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Created At */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Clock className="size-4" />
                                    Created
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm">{formatDateTime(tournament.created_at)}</p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Cancel Dialog */}
            <Dialog open={showCancelDialog} onOpenChange={setShowCancelDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-red-600">
                            <AlertTriangle className="size-5" />
                            Cancel Tournament
                        </DialogTitle>
                        <DialogDescription>
                            This action cannot be undone. The tournament will be marked as cancelled
                            and all participants will be notified.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="p-4 rounded-lg bg-muted">
                        <div className="flex items-center gap-3">
                            <div className="size-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                <Trophy className="size-5 text-primary" />
                            </div>
                            <div>
                                <p className="font-medium">{tournament.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {tournament.participants_count} participants registered
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Cancellation Reason (optional)</label>
                        <Textarea
                            placeholder="Enter reason for cancellation..."
                            value={cancelReason}
                            onChange={(e) => setCancelReason(e.target.value)}
                            rows={3}
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowCancelDialog(false)}>
                            Keep Tournament
                        </Button>
                        <Button variant="destructive" onClick={handleCancel} disabled={isSubmitting}>
                            {isSubmitting ? 'Cancelling...' : 'Cancel Tournament'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Start Tournament Dialog */}
            <Dialog open={showStartDialog} onOpenChange={setShowStartDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-green-600">
                            <Play className="size-5" />
                            Start Tournament
                        </DialogTitle>
                        <DialogDescription>
                            This will generate the tournament bracket and notify all {tournament.participants_count} participants.
                            The tournament status will change to "Active".
                        </DialogDescription>
                    </DialogHeader>
                    <div className="p-4 rounded-lg bg-green-50 border border-green-200">
                        <div className="flex items-center gap-3">
                            <div className="size-10 rounded-lg bg-green-100 flex items-center justify-center">
                                <Trophy className="size-5 text-green-600" />
                            </div>
                            <div>
                                <p className="font-medium">{tournament.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {tournament.participants_count} participants will compete
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="space-y-2 text-sm">
                        <p className="font-medium">What will happen:</p>
                        <ul className="list-disc list-inside text-muted-foreground space-y-1">
                            <li>Bracket will be generated based on player ratings</li>
                            <li>All participants will be notified</li>
                            <li>Match schedule will be created</li>
                            <li>Tournament status will change to "Active"</li>
                        </ul>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowStartDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            className="bg-green-600 hover:bg-green-700"
                            onClick={handleStart}
                            disabled={isStarting}
                        >
                            {isStarting ? (
                                <>
                                    <Loader2 className="size-4 mr-2 animate-spin" />
                                    Starting...
                                </>
                            ) : (
                                <>
                                    <Play className="size-4 mr-2" />
                                    Start Tournament
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SupportLayout>
    );
}
