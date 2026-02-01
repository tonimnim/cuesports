import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin';
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
    AlertTriangle,
    CheckCircle,
    Star,
    Target,
} from 'lucide-react';

interface GeographicScope {
    id: number;
    name: string;
    level: number;
    level_label: string;
}

interface Organizer {
    id: number;
    organization_name: string;
    logo_url: string | null;
}

interface CanStartInfo {
    can_start: boolean;
    issues: string[];
    participants_count: number;
    scheduled_start_date: string | null;
}

interface Tournament {
    id: number;
    name: string;
    description: string | null;
    status: string;
    status_label: string;
    type: string;
    type_label: string;
    format: string;
    race_to: number | null;
    finals_race_to: number | null;
    confirmation_hours: number | null;
    participants_count: number;
    max_participants: number | null;
    entry_fee: number | null;
    entry_fee_currency: string | null;
    starts_at: string | null;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    venue_name: string | null;
    venue_address: string | null;
    created_at: string;
    is_verified: boolean;
    verified_at: string | null;
    rejection_reason: string | null;
    geographic_scope: GeographicScope | null;
    organizer: Organizer | null;
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

interface Permissions {
    canApprove: boolean;
    canReject: boolean;
    canCancel: boolean;
    canStart: boolean;
}

interface Props {
    tournament: Tournament;
    participants: Participant[];
    recentMatches: Match[];
    activityLog: ActivityLogItem[];
    permissions: Permissions;
}

export default function TournamentShow({
    tournament,
    participants,
    recentMatches,
    activityLog,
    permissions,
}: Props) {
    const [showRejectDialog, setShowRejectDialog] = useState(false);
    const [showCancelDialog, setShowCancelDialog] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const [cancelReason, setCancelReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

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
            case 'pending_review':
                return 'bg-amber-100 text-amber-700 border-amber-200';
            case 'draft':
                return 'bg-gray-100 text-gray-700 border-gray-200';
            case 'registration':
                return 'bg-green-100 text-green-700 border-green-200';
            case 'active':
                return 'bg-purple-100 text-purple-700 border-purple-200';
            case 'completed':
                return 'bg-emerald-100 text-emerald-700 border-emerald-200';
            case 'cancelled':
            case 'rejected':
                return 'bg-red-100 text-red-700 border-red-200';
            default:
                return 'bg-gray-100 text-gray-700 border-gray-200';
        }
    };

    const getTypeColor = (type: string) => {
        switch (type) {
            case 'regular':
                return 'bg-blue-50 text-blue-700 border-blue-200';
            case 'special':
                return 'bg-yellow-50 text-yellow-700 border-yellow-200';
            default:
                return 'bg-gray-50 text-gray-700 border-gray-200';
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
            case 'active':
                return 'bg-blue-100 text-blue-700';
            case 'withdrawn':
                return 'bg-red-100 text-red-700';
            case 'eliminated':
            case 'disqualified':
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

    const formatCurrency = (amount: number | null, currency: string | null) => {
        if (!amount) return '-';
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: currency || 'NGN',
        }).format(amount);
    };

    const handleApprove = () => {
        if (confirm(`Are you sure you want to approve "${tournament.name}"?`)) {
            router.post(`/admin/tournaments/${tournament.id}/approve`, {}, {
                preserveScroll: true,
            });
        }
    };

    const handleReject = () => {
        if (!rejectReason.trim()) return;
        setIsSubmitting(true);
        router.post(
            `/admin/tournaments/${tournament.id}/reject`,
            { reason: rejectReason },
            {
                onSuccess: () => setShowRejectDialog(false),
                onFinish: () => {
                    setIsSubmitting(false);
                    setRejectReason('');
                },
            }
        );
    };

    const handleCancel = () => {
        setIsSubmitting(true);
        router.post(
            `/admin/tournaments/${tournament.id}/cancel`,
            { reason: cancelReason || null },
            {
                onSuccess: () => setShowCancelDialog(false),
                onFinish: () => {
                    setIsSubmitting(false);
                    setCancelReason('');
                },
            }
        );
    };

    return (
        <AdminLayout>
            <Head title={`Tournament: ${tournament.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild className="hover:bg-[#F1F5F9]">
                            <Link href="/admin/tournaments">
                                <ArrowLeft className="size-5" />
                            </Link>
                        </Button>
                        <div className="flex size-12 items-center justify-center rounded-lg bg-[#004E86]/10">
                            <Trophy className="size-6 text-[#004E86]" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-[#0A1628]">{tournament.name}</h1>
                            <p className="text-[#64748B]">ID: {tournament.id}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant="outline" className={getStatusColor(tournament.status)}>
                            {tournament.status_label}
                        </Badge>
                        <Badge variant="outline" className={getTypeColor(tournament.type)}>
                            {tournament.type === 'special' && <Star className="size-3 mr-1" />}
                            {tournament.type_label}
                        </Badge>
                    </div>
                </div>

                {/* Pending Review Alert */}
                {tournament.status === 'pending_review' && (
                    <Card className="border-amber-200 bg-amber-50">
                        <CardContent className="pt-6">
                            <div className="flex items-start gap-4">
                                <div className="size-10 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                                    <Clock className="size-5 text-amber-600" />
                                </div>
                                <div className="flex-1">
                                    <h3 className="font-semibold text-amber-800">Pending Review</h3>
                                    <p className="text-sm text-amber-700 mt-1">
                                        This tournament is awaiting admin approval. Review the details and approve or reject.
                                    </p>
                                    <div className="flex gap-2 mt-4">
                                        {permissions.canApprove && (
                                            <Button
                                                className="bg-green-600 hover:bg-green-700"
                                                onClick={handleApprove}
                                            >
                                                <CheckCircle className="size-4 mr-2" />
                                                Approve Tournament
                                            </Button>
                                        )}
                                        {permissions.canReject && (
                                            <Button
                                                variant="destructive"
                                                onClick={() => setShowRejectDialog(true)}
                                            >
                                                <XCircle className="size-4 mr-2" />
                                                Reject Tournament
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Rejection Reason Display */}
                {tournament.rejection_reason && (
                    <Card className="border-red-200 bg-red-50">
                        <CardContent className="pt-6">
                            <div className="flex items-start gap-4">
                                <div className="size-10 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                                    <XCircle className="size-5 text-red-600" />
                                </div>
                                <div className="flex-1">
                                    <h3 className="font-semibold text-red-800">Rejection Reason</h3>
                                    <p className="text-sm text-red-700 mt-1">
                                        {tournament.rejection_reason}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Tournament Info */}
                        <Card className="border-0 shadow-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-[#0A1628]">
                                    <Trophy className="size-5 text-[#004E86]" />
                                    Tournament Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Stats Grid */}
                                <div className="grid grid-cols-4 gap-4">
                                    <div className="text-center p-3 rounded-lg bg-[#004E86]/5 border border-[#004E86]/10">
                                        <p className="text-2xl font-bold text-[#0A1628]">{tournament.participants_count}</p>
                                        <p className="text-xs text-[#64748B]">
                                            {tournament.max_participants
                                                ? `of ${tournament.max_participants}`
                                                : 'Participants'}
                                        </p>
                                    </div>
                                    <div className="text-center p-3 rounded-lg bg-[#F8FAFC]">
                                        <p className="text-2xl font-bold text-[#0A1628] capitalize">
                                            {tournament.format || '-'}
                                        </p>
                                        <p className="text-xs text-[#64748B]">Format</p>
                                    </div>
                                    <div className="text-center p-3 rounded-lg bg-[#F8FAFC]">
                                        <p className="text-2xl font-bold text-[#0A1628]">
                                            {tournament.race_to ? `R${tournament.race_to}` : '-'}
                                        </p>
                                        <p className="text-xs text-[#64748B]">Race To</p>
                                    </div>
                                    <div className="text-center p-3 rounded-lg bg-[#F8FAFC]">
                                        <p className="text-2xl font-bold text-[#0A1628]">
                                            {tournament.finals_race_to ? `R${tournament.finals_race_to}` : tournament.race_to ? `R${tournament.race_to}` : '-'}
                                        </p>
                                        <p className="text-xs text-[#64748B]">Finals Race</p>
                                    </div>
                                </div>

                                {/* Description */}
                                {tournament.description && (
                                    <div>
                                        <h4 className="text-sm font-medium text-[#64748B] mb-2">
                                            Description
                                        </h4>
                                        <p className="text-sm text-[#0A1628]">{tournament.description}</p>
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
                                <Card className="border-0 shadow-sm">
                                    <CardHeader>
                                        <CardTitle className="text-[#0A1628]">Registered Participants</CardTitle>
                                        <CardDescription>
                                            Players registered for this tournament
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {participants.length === 0 ? (
                                            <div className="text-center py-8 text-[#64748B]">
                                                <Users className="size-12 mx-auto mb-2 text-[#E2E8F0]" />
                                                No participants registered yet
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[400px]">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow className="bg-[#F8FAFC]">
                                                            <TableHead className="font-semibold text-[#0A1628]">Seed</TableHead>
                                                            <TableHead className="font-semibold text-[#0A1628]">Player</TableHead>
                                                            <TableHead className="font-semibold text-[#0A1628]">Rating</TableHead>
                                                            <TableHead className="font-semibold text-[#0A1628]">Status</TableHead>
                                                            <TableHead className="font-semibold text-[#0A1628]">Registered</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {participants.map((participant) => (
                                                            <TableRow key={participant.id} className="hover:bg-[#F8FAFC]">
                                                                <TableCell className="text-[#0A1628]">
                                                                    {participant.seed || '-'}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {participant.player ? (
                                                                        <div className="flex items-center gap-2">
                                                                            <Avatar className="size-7 border border-[#E2E8F0]">
                                                                                <AvatarImage
                                                                                    src={
                                                                                        participant.player.photo_url ||
                                                                                        undefined
                                                                                    }
                                                                                />
                                                                                <AvatarFallback className="text-xs bg-[#004E86]/10 text-[#004E86]">
                                                                                    {participant.player.name
                                                                                        .substring(0, 2)
                                                                                        .toUpperCase()}
                                                                                </AvatarFallback>
                                                                            </Avatar>
                                                                            <span className="font-medium text-[#0A1628]">
                                                                                {participant.player.name}
                                                                            </span>
                                                                        </div>
                                                                    ) : (
                                                                        <span className="text-[#64748B]">
                                                                            Unknown Player
                                                                        </span>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell className="text-[#0A1628] font-mono">
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
                                                                <TableCell className="text-[#64748B]">
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
                                <Card className="border-0 shadow-sm">
                                    <CardHeader>
                                        <CardTitle className="text-[#0A1628]">Recent Matches</CardTitle>
                                        <CardDescription>Recent matches in this tournament</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {recentMatches.length === 0 ? (
                                            <div className="text-center py-8 text-[#64748B]">
                                                <Swords className="size-12 mx-auto mb-2 text-[#E2E8F0]" />
                                                No matches played yet
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[400px]">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow className="bg-[#F8FAFC]">
                                                            <TableHead className="font-semibold text-[#0A1628]">Round</TableHead>
                                                            <TableHead className="font-semibold text-[#0A1628]">Player 1</TableHead>
                                                            <TableHead className="font-semibold text-[#0A1628]">Score</TableHead>
                                                            <TableHead className="font-semibold text-[#0A1628]">Player 2</TableHead>
                                                            <TableHead className="font-semibold text-[#0A1628]">Status</TableHead>
                                                            <TableHead className="font-semibold text-[#0A1628]">Played</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {recentMatches.map((match) => (
                                                            <TableRow key={match.id} className="hover:bg-[#F8FAFC]">
                                                                <TableCell className="text-[#0A1628]">
                                                                    {match.round_name || '-'}
                                                                </TableCell>
                                                                <TableCell className="font-medium text-[#0A1628]">
                                                                    {match.player1_name}
                                                                </TableCell>
                                                                <TableCell>
                                                                    <span className="font-mono text-[#0A1628]">
                                                                        {match.player1_score ?? '-'} :{' '}
                                                                        {match.player2_score ?? '-'}
                                                                    </span>
                                                                </TableCell>
                                                                <TableCell className="font-medium text-[#0A1628]">
                                                                    {match.player2_name}
                                                                </TableCell>
                                                                <TableCell>
                                                                    <Badge
                                                                        className={getMatchStatusColor(match.status)}
                                                                    >
                                                                        {formatStatus(match.status)}
                                                                    </Badge>
                                                                </TableCell>
                                                                <TableCell className="text-[#64748B]">
                                                                    {formatDate(match.played_at)}
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

                            {/* Activity Tab */}
                            <TabsContent value="activity">
                                <Card className="border-0 shadow-sm">
                                    <CardHeader>
                                        <CardTitle className="text-[#0A1628]">Activity Log</CardTitle>
                                        <CardDescription>
                                            Admin/Support actions taken on this tournament
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {activityLog.length === 0 ? (
                                            <div className="text-center py-8 text-[#64748B]">
                                                <History className="size-12 mx-auto mb-2 text-[#E2E8F0]" />
                                                No activity logged yet
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[400px]">
                                                <div className="space-y-3">
                                                    {activityLog.map((entry) => (
                                                        <div
                                                            key={entry.id}
                                                            className="flex items-start gap-3 p-3 rounded-lg border border-[#E2E8F0]"
                                                        >
                                                            <div className="size-8 rounded-full bg-[#004E86]/10 flex items-center justify-center shrink-0">
                                                                <History className="size-4 text-[#004E86]" />
                                                            </div>
                                                            <div className="flex-1 min-w-0">
                                                                <p className="text-sm font-medium text-[#0A1628]">
                                                                    {entry.action_label}
                                                                </p>
                                                                <p className="text-xs text-[#64748B] truncate">
                                                                    {entry.description}
                                                                </p>
                                                                <p className="text-xs text-[#64748B] mt-1">
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
                        <Card className="border-0 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2 text-[#0A1628]">
                                    <Building2 className="size-4 text-[#004E86]" />
                                    Organizer
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {tournament.organizer ? (
                                    <div className="flex items-center gap-3">
                                        <Avatar className="size-10 border border-[#E2E8F0]">
                                            <AvatarImage
                                                src={tournament.organizer.logo_url || undefined}
                                            />
                                            <AvatarFallback className="bg-[#004E86]/10 text-[#004E86]">
                                                <Building2 className="size-5" />
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <p className="font-medium text-[#0A1628]">
                                                {tournament.organizer.organization_name}
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-[#64748B]">Created by System Admin</p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Location Info */}
                        {tournament.geographic_scope && (
                            <Card className="border-0 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-base flex items-center gap-2 text-[#0A1628]">
                                        <MapPin className="size-4 text-[#004E86]" />
                                        Geographic Scope
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="font-medium text-[#0A1628]">{tournament.geographic_scope.name}</p>
                                    <p className="text-sm text-[#64748B]">{tournament.geographic_scope.level_label} Level</p>
                                    {tournament.venue_name && (
                                        <>
                                            <Separator className="my-3" />
                                            <p className="text-sm font-medium text-[#0A1628]">{tournament.venue_name}</p>
                                            {tournament.venue_address && (
                                                <p className="text-xs text-[#64748B]">{tournament.venue_address}</p>
                                            )}
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Schedule Info */}
                        <Card className="border-0 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2 text-[#0A1628]">
                                    <Calendar className="size-4 text-[#004E86]" />
                                    Schedule
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-[#64748B]">Start Date</span>
                                    <span className="font-medium text-[#0A1628]">{formatDate(tournament.starts_at)}</span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-[#64748B]">Reg. Opens</span>
                                    <span className="font-medium text-[#0A1628]">
                                        {formatDate(tournament.registration_opens_at)}
                                    </span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-[#64748B]">Reg. Closes</span>
                                    <span className="font-medium text-[#0A1628]">
                                        {formatDate(tournament.registration_closes_at)}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Match Settings */}
                        <Card className="border-0 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2 text-[#0A1628]">
                                    <Target className="size-4 text-[#004E86]" />
                                    Match Settings
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-[#64748B]">Race To</span>
                                    <span className="font-medium text-[#0A1628]">
                                        {tournament.race_to || '-'}
                                    </span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-[#64748B]">Finals Race To</span>
                                    <span className="font-medium text-[#0A1628]">
                                        {tournament.finals_race_to || tournament.race_to || '-'}
                                    </span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-[#64748B]">Confirm Hours</span>
                                    <span className="font-medium text-[#0A1628]">
                                        {tournament.confirmation_hours || 24}h
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Financial Info */}
                        <Card className="border-0 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2 text-[#0A1628]">
                                    <DollarSign className="size-4 text-[#004E86]" />
                                    Entry Fee
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="font-medium text-[#0A1628]">
                                    {formatCurrency(tournament.entry_fee, tournament.entry_fee_currency)}
                                </p>
                            </CardContent>
                        </Card>

                        {/* Actions */}
                        {permissions.canCancel && !['completed', 'cancelled', 'rejected'].includes(tournament.status) && (
                            <Card className="border-0 shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-base text-[#0A1628]">Actions</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Button
                                        variant="destructive"
                                        className="w-full"
                                        onClick={() => setShowCancelDialog(true)}
                                    >
                                        <XCircle className="size-4 mr-2" />
                                        Cancel Tournament
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {/* Verification Info */}
                        <Card className="border-0 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2 text-[#0A1628]">
                                    <Clock className="size-4 text-[#004E86]" />
                                    Created
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-[#0A1628]">{formatDateTime(tournament.created_at)}</p>
                                {tournament.verified_at && (
                                    <>
                                        <Separator className="my-3" />
                                        <p className="text-xs text-[#64748B]">
                                            Verified: {formatDateTime(tournament.verified_at)}
                                        </p>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Reject Dialog */}
            <Dialog open={showRejectDialog} onOpenChange={setShowRejectDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-red-600">
                            <XCircle className="size-5" />
                            Reject Tournament
                        </DialogTitle>
                        <DialogDescription>
                            Please provide a reason for rejecting this tournament.
                            The organizer will be notified.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="p-4 rounded-lg bg-[#F8FAFC] border border-[#E2E8F0]">
                        <div className="flex items-center gap-3">
                            <div className="size-10 rounded-lg bg-[#004E86]/10 flex items-center justify-center">
                                <Trophy className="size-5 text-[#004E86]" />
                            </div>
                            <div>
                                <p className="font-medium text-[#0A1628]">{tournament.name}</p>
                                <p className="text-sm text-[#64748B]">
                                    {tournament.organizer?.organization_name || 'System'}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="rejectReason">Rejection Reason</Label>
                        <Textarea
                            id="rejectReason"
                            placeholder="Enter reason for rejection..."
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                            rows={4}
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowRejectDialog(false)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={!rejectReason.trim() || isSubmitting}
                        >
                            {isSubmitting ? 'Rejecting...' : 'Reject Tournament'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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
                    <div className="p-4 rounded-lg bg-[#F8FAFC] border border-[#E2E8F0]">
                        <div className="flex items-center gap-3">
                            <div className="size-10 rounded-lg bg-[#004E86]/10 flex items-center justify-center">
                                <Trophy className="size-5 text-[#004E86]" />
                            </div>
                            <div>
                                <p className="font-medium text-[#0A1628]">{tournament.name}</p>
                                <p className="text-sm text-[#64748B]">
                                    {tournament.participants_count} participants registered
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="cancelReason">Cancellation Reason (optional)</Label>
                        <Textarea
                            id="cancelReason"
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
                        <Button
                            variant="destructive"
                            onClick={handleCancel}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Cancelling...' : 'Cancel Tournament'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
