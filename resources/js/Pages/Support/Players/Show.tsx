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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    Mail,
    Phone,
    Calendar,
    CheckCircle,
    XCircle,
    Trophy,
    Star,
    TrendingUp,
    TrendingDown,
    AlertTriangle,
    MessageSquare,
    History,
    Swords,
    PinIcon,
    Plus,
    Globe,
} from 'lucide-react';

interface Player {
    id: number;
    first_name: string;
    last_name: string;
    nickname: string | null;
    photo_url: string | null;
    rating: number;
    rating_category: string | null;
    best_rating: number | null;
    total_matches: number;
    wins: number;
    losses: number;
    tournaments_played: number;
    tournaments_won: number;
    lifetime_frames_won: number;
    lifetime_frames_lost: number;
    created_at: string;
    user: {
        id: number;
        email: string;
        phone_number: string | null;
        is_active: boolean;
        email_verified_at: string | null;
        created_at: string;
        country: { id: number; name: string } | null;
    };
    warning_count: number;
}

interface Note {
    id: number;
    content: string;
    type: string;
    type_label: string;
    is_pinned: boolean;
    created_by: { id: number; email: string } | null;
    created_at: string;
}

interface MatchHistoryItem {
    id: number;
    opponent_name: string;
    won: boolean;
    score: string;
    rating_before: number;
    rating_after: number;
    rating_change: number;
    tournament_name: string;
    match_type: string;
    round_name: string | null;
    played_at: string | null;
}

interface DisputeHistoryItem {
    id: number;
    tournament_name: string;
    opponent_name: string;
    was_disputer: boolean;
    status: string;
    dispute_reason: string | null;
    resolution_notes: string | null;
    disputed_at: string | null;
    resolved_at: string | null;
}

interface RatingHistoryItem {
    id: number;
    old_rating: number;
    new_rating: number;
    change: number;
    reason: string;
    created_at: string;
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
    player: Player;
    notes: Note[];
    matchHistory: MatchHistoryItem[];
    disputeHistory: DisputeHistoryItem[];
    ratingHistory: RatingHistoryItem[];
    activityLog: ActivityLogItem[];
}

export default function PlayerShow({
    player,
    notes,
    matchHistory,
    disputeHistory,
    ratingHistory,
    activityLog,
}: Props) {
    const [showNoteDialog, setShowNoteDialog] = useState(false);
    const [showDeactivateDialog, setShowDeactivateDialog] = useState(false);
    const [noteContent, setNoteContent] = useState('');
    const [noteType, setNoteType] = useState<string>('general');
    const [notePinned, setNotePinned] = useState(false);
    const [deactivateReason, setDeactivateReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const getDisplayName = () => {
        return player.nickname || `${player.first_name} ${player.last_name}`;
    };

    const getInitials = () => {
        const first = player.first_name?.[0] || '';
        const last = player.last_name?.[0] || '';
        return (first + last).toUpperCase() || 'P';
    };

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

    const getRatingColor = (category: string | null) => {
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

    const getNoteTypeColor = (type: string) => {
        switch (type) {
            case 'warning':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'ban_reason':
                return 'bg-red-100 text-red-800 border-red-200';
            case 'verification':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    const winRate = player.total_matches
        ? ((player.wins / player.total_matches) * 100).toFixed(1)
        : '0.0';

    const handleAddNote = () => {
        if (!noteContent.trim()) return;

        setIsSubmitting(true);
        router.post(
            `/support/players/${player.id}/notes`,
            {
                content: noteContent,
                type: noteType,
                is_pinned: notePinned,
            },
            {
                onSuccess: () => {
                    setShowNoteDialog(false);
                    setNoteContent('');
                    setNoteType('general');
                    setNotePinned(false);
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const handleReactivate = () => {
        router.post(`/support/players/${player.id}/reactivate`);
    };

    const handleDeactivate = () => {
        setIsSubmitting(true);
        router.post(
            `/support/players/${player.id}/deactivate`,
            { reason: deactivateReason },
            {
                onSuccess: () => setShowDeactivateDialog(false),
                onFinish: () => {
                    setIsSubmitting(false);
                    setDeactivateReason('');
                },
            }
        );
    };

    const pinnedNotes = notes.filter((n) => n.is_pinned);
    const regularNotes = notes.filter((n) => !n.is_pinned);

    return (
        <SupportLayout>
            <Head title={`Player: ${getDisplayName()}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/support/players">
                                <ArrowLeft className="size-5" />
                            </Link>
                        </Button>
                        <Avatar className="size-14 border-2 border-primary/20">
                            <AvatarImage src={player.photo_url || undefined} alt={getDisplayName()} />
                            <AvatarFallback className="bg-primary/10 text-primary text-lg">
                                {getInitials()}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{getDisplayName()}</h1>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <span>{player.user.email}</span>
                                {player.warning_count > 0 && (
                                    <Badge variant="destructive" className="text-xs">
                                        {player.warning_count} warning{player.warning_count > 1 ? 's' : ''}
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge
                            className={
                                player.user.is_active
                                    ? 'bg-green-100 text-green-700 hover:bg-green-100'
                                    : 'bg-red-100 text-red-700 hover:bg-red-100'
                            }
                        >
                            {player.user.is_active ? (
                                <>
                                    <CheckCircle className="size-3 mr-1" />
                                    Active
                                </>
                            ) : (
                                <>
                                    <XCircle className="size-3 mr-1" />
                                    Inactive
                                </>
                            )}
                        </Badge>
                        <Badge variant="outline" className={getRatingColor(player.rating_category)}>
                            <Star className="size-3 mr-1" />
                            {player.rating} - {player.rating_category || 'Beginner'}
                        </Badge>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Player Stats */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Trophy className="size-5 text-primary" />
                                    Player Statistics
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Rating Section */}
                                <div className="flex items-center justify-between p-4 rounded-lg bg-primary/5 border border-primary/10">
                                    <div className="flex items-center gap-4">
                                        <div className="flex size-12 items-center justify-center rounded-full bg-primary/10">
                                            <Star className="size-6 text-primary" />
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">Current Rating</p>
                                            <p className="text-3xl font-bold">{player.rating}</p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <Badge className={getRatingColor(player.rating_category)}>
                                            {player.rating_category || 'Beginner'}
                                        </Badge>
                                        {player.best_rating && (
                                            <p className="text-sm text-muted-foreground mt-1">
                                                Best: {player.best_rating}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Match Stats Grid */}
                                <div>
                                    <h4 className="text-sm font-medium text-muted-foreground mb-3">
                                        Match Performance
                                    </h4>
                                    <div className="grid grid-cols-4 gap-4">
                                        <div className="text-center p-3 rounded-lg bg-slate-50">
                                            <p className="text-2xl font-bold">{player.total_matches}</p>
                                            <p className="text-xs text-muted-foreground">Total</p>
                                        </div>
                                        <div className="text-center p-3 rounded-lg bg-green-50">
                                            <p className="text-2xl font-bold text-green-700">{player.wins}</p>
                                            <p className="text-xs text-muted-foreground">Wins</p>
                                        </div>
                                        <div className="text-center p-3 rounded-lg bg-red-50">
                                            <p className="text-2xl font-bold text-red-700">{player.losses}</p>
                                            <p className="text-xs text-muted-foreground">Losses</p>
                                        </div>
                                        <div className="text-center p-3 rounded-lg bg-blue-50">
                                            <p className="text-2xl font-bold text-blue-700">{winRate}%</p>
                                            <p className="text-xs text-muted-foreground">Win Rate</p>
                                        </div>
                                    </div>
                                </div>

                                {/* Tournament Stats */}
                                <div>
                                    <h4 className="text-sm font-medium text-muted-foreground mb-3">
                                        Tournament Performance
                                    </h4>
                                    <div className="grid grid-cols-3 gap-4">
                                        <div className="text-center p-3 rounded-lg bg-slate-50">
                                            <p className="text-2xl font-bold">{player.tournaments_played}</p>
                                            <p className="text-xs text-muted-foreground">Played</p>
                                        </div>
                                        <div className="text-center p-3 rounded-lg bg-yellow-50">
                                            <p className="text-2xl font-bold text-yellow-700">
                                                {player.tournaments_won}
                                            </p>
                                            <p className="text-xs text-muted-foreground">Won</p>
                                        </div>
                                        <div className="text-center p-3 rounded-lg bg-purple-50">
                                            <p className="text-2xl font-bold text-purple-700">
                                                {player.lifetime_frames_won} - {player.lifetime_frames_lost}
                                            </p>
                                            <p className="text-xs text-muted-foreground">Frames W/L</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Tabs for History */}
                        <Tabs defaultValue="matches" className="space-y-4">
                            <TabsList className="grid w-full grid-cols-4">
                                <TabsTrigger value="matches" className="gap-1">
                                    <Swords className="size-4" />
                                    Matches
                                </TabsTrigger>
                                <TabsTrigger value="disputes" className="gap-1">
                                    <AlertTriangle className="size-4" />
                                    Disputes
                                </TabsTrigger>
                                <TabsTrigger value="rating" className="gap-1">
                                    <TrendingUp className="size-4" />
                                    Rating
                                </TabsTrigger>
                                <TabsTrigger value="activity" className="gap-1">
                                    <History className="size-4" />
                                    Activity
                                </TabsTrigger>
                            </TabsList>

                            {/* Match History Tab */}
                            <TabsContent value="matches">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Match History</CardTitle>
                                        <CardDescription>Recent matches played by this player</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {matchHistory.length === 0 ? (
                                            <div className="text-center py-8 text-muted-foreground">
                                                <Swords className="size-12 mx-auto mb-2 opacity-50" />
                                                No match history found
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[400px]">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Result</TableHead>
                                                            <TableHead>Opponent</TableHead>
                                                            <TableHead>Score</TableHead>
                                                            <TableHead>Rating Change</TableHead>
                                                            <TableHead>Tournament</TableHead>
                                                            <TableHead>Date</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {matchHistory.map((match) => (
                                                            <TableRow key={match.id}>
                                                                <TableCell>
                                                                    <Badge
                                                                        className={
                                                                            match.won
                                                                                ? 'bg-green-100 text-green-700'
                                                                                : 'bg-red-100 text-red-700'
                                                                        }
                                                                    >
                                                                        {match.won ? 'Won' : 'Lost'}
                                                                    </Badge>
                                                                </TableCell>
                                                                <TableCell className="font-medium">
                                                                    {match.opponent_name}
                                                                </TableCell>
                                                                <TableCell>{match.score}</TableCell>
                                                                <TableCell>
                                                                    <span
                                                                        className={
                                                                            match.rating_change >= 0
                                                                                ? 'text-green-600'
                                                                                : 'text-red-600'
                                                                        }
                                                                    >
                                                                        {match.rating_change >= 0 ? '+' : ''}
                                                                        {match.rating_change}
                                                                    </span>
                                                                    <span className="text-xs text-muted-foreground ml-1">
                                                                        ({match.rating_before} → {match.rating_after})
                                                                    </span>
                                                                </TableCell>
                                                                <TableCell>
                                                                    <div className="text-sm">
                                                                        <p>{match.tournament_name}</p>
                                                                        {match.round_name && (
                                                                            <p className="text-xs text-muted-foreground">
                                                                                {match.round_name}
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                </TableCell>
                                                                <TableCell>{formatDate(match.played_at)}</TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            </ScrollArea>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Dispute History Tab */}
                            <TabsContent value="disputes">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Dispute History</CardTitle>
                                        <CardDescription>Disputes involving this player</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {disputeHistory.length === 0 ? (
                                            <div className="text-center py-8 text-muted-foreground">
                                                <AlertTriangle className="size-12 mx-auto mb-2 opacity-50" />
                                                No dispute history found
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[400px]">
                                                <div className="space-y-4">
                                                    {disputeHistory.map((dispute) => (
                                                        <div
                                                            key={dispute.id}
                                                            className="p-4 rounded-lg border bg-card"
                                                        >
                                                            <div className="flex items-start justify-between mb-2">
                                                                <div>
                                                                    <p className="font-medium">
                                                                        vs {dispute.opponent_name}
                                                                    </p>
                                                                    <p className="text-sm text-muted-foreground">
                                                                        {dispute.tournament_name}
                                                                    </p>
                                                                </div>
                                                                <div className="flex items-center gap-2">
                                                                    {dispute.was_disputer && (
                                                                        <Badge variant="outline">Disputer</Badge>
                                                                    )}
                                                                    <Badge
                                                                        className={
                                                                            dispute.status === 'disputed'
                                                                                ? 'bg-yellow-100 text-yellow-700'
                                                                                : 'bg-green-100 text-green-700'
                                                                        }
                                                                    >
                                                                        {dispute.status === 'disputed'
                                                                            ? 'Pending'
                                                                            : 'Resolved'}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                            {dispute.dispute_reason && (
                                                                <p className="text-sm text-muted-foreground mb-2">
                                                                    <strong>Reason:</strong> {dispute.dispute_reason}
                                                                </p>
                                                            )}
                                                            {dispute.resolution_notes && (
                                                                <p className="text-sm text-muted-foreground mb-2">
                                                                    <strong>Resolution:</strong>{' '}
                                                                    {dispute.resolution_notes}
                                                                </p>
                                                            )}
                                                            <p className="text-xs text-muted-foreground">
                                                                Disputed: {formatDateTime(dispute.disputed_at)}
                                                                {dispute.resolved_at &&
                                                                    ` | Resolved: ${formatDateTime(dispute.resolved_at)}`}
                                                            </p>
                                                        </div>
                                                    ))}
                                                </div>
                                            </ScrollArea>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Rating History Tab */}
                            <TabsContent value="rating">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Rating History</CardTitle>
                                        <CardDescription>Rating changes over time</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {ratingHistory.length === 0 ? (
                                            <div className="text-center py-8 text-muted-foreground">
                                                <TrendingUp className="size-12 mx-auto mb-2 opacity-50" />
                                                No rating history found
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[400px]">
                                                <div className="space-y-2">
                                                    {ratingHistory.map((entry) => (
                                                        <div
                                                            key={entry.id}
                                                            className="flex items-center justify-between p-3 rounded-lg border"
                                                        >
                                                            <div className="flex items-center gap-3">
                                                                {entry.change >= 0 ? (
                                                                    <div className="size-8 rounded-full bg-green-100 flex items-center justify-center">
                                                                        <TrendingUp className="size-4 text-green-600" />
                                                                    </div>
                                                                ) : (
                                                                    <div className="size-8 rounded-full bg-red-100 flex items-center justify-center">
                                                                        <TrendingDown className="size-4 text-red-600" />
                                                                    </div>
                                                                )}
                                                                <div>
                                                                    <p className="text-sm font-medium">
                                                                        {entry.old_rating} → {entry.new_rating}
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {entry.reason}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div className="text-right">
                                                                <p
                                                                    className={`font-semibold ${
                                                                        entry.change >= 0
                                                                            ? 'text-green-600'
                                                                            : 'text-red-600'
                                                                    }`}
                                                                >
                                                                    {entry.change >= 0 ? '+' : ''}
                                                                    {entry.change}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
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

                            {/* Activity Log Tab */}
                            <TabsContent value="activity">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Activity Log</CardTitle>
                                        <CardDescription>
                                            Support actions taken on this player
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
                        {/* Account Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Account Info</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <div className="flex items-center gap-2">
                                    <Mail className="size-4 text-muted-foreground" />
                                    <span className="truncate">{player.user.email}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Phone className="size-4 text-muted-foreground" />
                                    <span>{player.user.phone_number || 'Not provided'}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Globe className="size-4 text-muted-foreground" />
                                    <span>{player.user.country?.name || 'Unknown'}</span>
                                </div>
                                <Separator />
                                <div className="flex items-center gap-2">
                                    <Calendar className="size-4 text-muted-foreground" />
                                    <span>Joined {formatDate(player.user.created_at)}</span>
                                </div>
                                {player.user.email_verified_at && (
                                    <div className="flex items-center gap-2 text-green-600">
                                        <CheckCircle className="size-4" />
                                        <span>Email verified</span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Notes Section */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-base flex items-center gap-2">
                                    <MessageSquare className="size-4" />
                                    Notes
                                </CardTitle>
                                <Button size="sm" variant="outline" onClick={() => setShowNoteDialog(true)}>
                                    <Plus className="size-4 mr-1" />
                                    Add
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {notes.length === 0 ? (
                                    <p className="text-sm text-muted-foreground text-center py-4">
                                        No notes yet
                                    </p>
                                ) : (
                                    <ScrollArea className="h-[300px]">
                                        <div className="space-y-3">
                                            {pinnedNotes.map((note) => (
                                                <div
                                                    key={note.id}
                                                    className="p-3 rounded-lg border-2 border-primary/20 bg-primary/5"
                                                >
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <PinIcon className="size-3 text-primary" />
                                                        <Badge
                                                            variant="outline"
                                                            className={`text-xs ${getNoteTypeColor(note.type)}`}
                                                        >
                                                            {note.type_label}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm">{note.content}</p>
                                                    <p className="text-xs text-muted-foreground mt-2">
                                                        {note.created_by?.email || 'System'} ·{' '}
                                                        {formatDate(note.created_at)}
                                                    </p>
                                                </div>
                                            ))}
                                            {regularNotes.map((note) => (
                                                <div key={note.id} className="p-3 rounded-lg border">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <Badge
                                                            variant="outline"
                                                            className={`text-xs ${getNoteTypeColor(note.type)}`}
                                                        >
                                                            {note.type_label}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm">{note.content}</p>
                                                    <p className="text-xs text-muted-foreground mt-2">
                                                        {note.created_by?.email || 'System'} ·{' '}
                                                        {formatDate(note.created_at)}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    </ScrollArea>
                                )}
                            </CardContent>
                        </Card>

                        {/* Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Account Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {player.user.is_active ? (
                                    <Button
                                        variant="destructive"
                                        className="w-full"
                                        onClick={() => setShowDeactivateDialog(true)}
                                    >
                                        <XCircle className="size-4 mr-2" />
                                        Deactivate Account
                                    </Button>
                                ) : (
                                    <Button
                                        className="w-full bg-green-600 hover:bg-green-700"
                                        onClick={handleReactivate}
                                    >
                                        <CheckCircle className="size-4 mr-2" />
                                        Reactivate Account
                                    </Button>
                                )}
                                <p className="text-xs text-muted-foreground text-center">
                                    {player.user.is_active
                                        ? 'Deactivating will log the player out of all devices.'
                                        : 'Reactivating will allow the player to log in again.'}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Add Note Dialog */}
            <Dialog open={showNoteDialog} onOpenChange={setShowNoteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Note</DialogTitle>
                        <DialogDescription>
                            Add a note to this player's profile for future reference.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Note Type</Label>
                            <Select value={noteType} onValueChange={setNoteType}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="general">General</SelectItem>
                                    <SelectItem value="warning">Warning</SelectItem>
                                    <SelectItem value="verification">Verification</SelectItem>
                                    <SelectItem value="ban_reason">Ban Reason</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Content</Label>
                            <Textarea
                                placeholder="Enter note content..."
                                value={noteContent}
                                onChange={(e) => setNoteContent(e.target.value)}
                                rows={4}
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="pinned"
                                checked={notePinned}
                                onChange={(e) => setNotePinned(e.target.checked)}
                                className="rounded"
                            />
                            <Label htmlFor="pinned" className="text-sm font-normal cursor-pointer">
                                Pin this note
                            </Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowNoteDialog(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleAddNote} disabled={isSubmitting || !noteContent.trim()}>
                            {isSubmitting ? 'Adding...' : 'Add Note'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Deactivate Dialog */}
            <Dialog open={showDeactivateDialog} onOpenChange={setShowDeactivateDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Deactivate Player</DialogTitle>
                        <DialogDescription>
                            This will revoke all access tokens and prevent the player from logging in.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex items-center gap-3 p-4 rounded-lg bg-muted">
                        <Avatar>
                            <AvatarImage src={player.photo_url || undefined} />
                            <AvatarFallback>{getInitials()}</AvatarFallback>
                        </Avatar>
                        <div>
                            <p className="font-medium">{getDisplayName()}</p>
                            <p className="text-sm text-muted-foreground">{player.user.email}</p>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Reason (optional)</Label>
                        <Textarea
                            placeholder="Enter reason for deactivation..."
                            value={deactivateReason}
                            onChange={(e) => setDeactivateReason(e.target.value)}
                            rows={3}
                        />
                        <p className="text-xs text-muted-foreground">
                            If provided, this will be saved as a pinned ban reason note.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDeactivateDialog(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDeactivate} disabled={isSubmitting}>
                            {isSubmitting ? 'Deactivating...' : 'Deactivate'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SupportLayout>
    );
}
