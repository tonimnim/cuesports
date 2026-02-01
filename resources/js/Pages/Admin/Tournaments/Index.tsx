import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
    Search,
    MoreHorizontal,
    Eye,
    Filter,
    Trophy,
    Users,
    Calendar,
    Building2,
    Plus,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Clock,
    Star,
    MapPin,
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

interface Tournament {
    id: number;
    name: string;
    status: string;
    status_label: string;
    type: string;
    type_label: string;
    format: string;
    participants_count: number;
    max_participants: number | null;
    starts_at: string | null;
    created_at: string;
    is_verified: boolean;
    verified_at: string | null;
    rejection_reason: string | null;
    geographic_scope: GeographicScope | null;
    organizer: Organizer | null;
}

interface PaginatedTournaments {
    data: Tournament[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Stats {
    total: number;
    pending_review: number;
    active: number;
    by_type: {
        regular: number;
        special: number;
    };
}

interface StatusOption {
    value: string;
    label: string;
}

interface TypeOption {
    value: string;
    label: string;
}

interface Props {
    tournaments: PaginatedTournaments;
    stats: Stats;
    filters: {
        search?: string;
        status?: string;
        type?: string;
    };
    statuses: StatusOption[];
    types: TypeOption[];
    canCreateSpecial: boolean;
}

export default function TournamentsIndex({
    tournaments,
    stats,
    filters,
    statuses,
    types,
    canCreateSpecial,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [selectedTournament, setSelectedTournament] = useState<Tournament | null>(null);
    const [rejectReason, setRejectReason] = useState('');
    const [cancelReason, setCancelReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/tournaments', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key: string, value: string | undefined) => {
        router.get(
            '/admin/tournaments',
            { ...filters, [key]: value, page: 1 },
            { preserveState: true }
        );
    };

    const handleApprove = (tournament: Tournament) => {
        if (confirm(`Are you sure you want to approve "${tournament.name}"?`)) {
            router.post(`/admin/tournaments/${tournament.id}/approve`, {}, {
                preserveScroll: true,
            });
        }
    };

    const openRejectDialog = (tournament: Tournament) => {
        setSelectedTournament(tournament);
        setRejectReason('');
        setRejectDialogOpen(true);
    };

    const handleReject = () => {
        if (!selectedTournament || !rejectReason.trim()) return;
        setIsSubmitting(true);
        router.post(`/admin/tournaments/${selectedTournament.id}/reject`, {
            reason: rejectReason,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setIsSubmitting(false);
                setRejectDialogOpen(false);
                setSelectedTournament(null);
            },
        });
    };

    const openCancelDialog = (tournament: Tournament) => {
        setSelectedTournament(tournament);
        setCancelReason('');
        setCancelDialogOpen(true);
    };

    const handleCancel = () => {
        if (!selectedTournament) return;
        setIsSubmitting(true);
        router.post(`/admin/tournaments/${selectedTournament.id}/cancel`, {
            reason: cancelReason || null,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setIsSubmitting(false);
                setCancelDialogOpen(false);
                setSelectedTournament(null);
            },
        });
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
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
                return 'bg-red-100 text-red-700 border-red-200';
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

    const canApprove = (tournament: Tournament) => tournament.status === 'pending_review';
    const canReject = (tournament: Tournament) => tournament.status === 'pending_review';
    const canCancel = (tournament: Tournament) =>
        !['completed', 'cancelled', 'rejected'].includes(tournament.status);

    return (
        <AdminLayout>
            <Head title="Tournaments - Admin" />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-[#0A1628]">Tournaments</h1>
                        <p className="text-[#64748B]">
                            Manage all tournaments, approve new ones, and oversee operations
                        </p>
                    </div>
                    {canCreateSpecial && (
                        <Link href="/admin/tournaments/create">
                            <Button className="bg-[#004E86] hover:bg-[#003d6b]">
                                <Plus className="size-4 mr-2" />
                                Create Special Tournament
                            </Button>
                        </Link>
                    )}
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <Card className="border-0 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-[#64748B]">Total</CardTitle>
                            <Trophy className="size-4 text-[#004E86]" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-[#0A1628]">{stats.total}</div>
                        </CardContent>
                    </Card>

                    <Card className="border-0 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-[#64748B]">Pending Review</CardTitle>
                            <Clock className="size-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-amber-600">{stats.pending_review}</div>
                        </CardContent>
                    </Card>

                    <Card className="border-0 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-[#64748B]">Active</CardTitle>
                            <AlertTriangle className="size-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">{stats.active}</div>
                        </CardContent>
                    </Card>

                    <Card className="border-0 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-[#64748B]">Regular</CardTitle>
                            <Users className="size-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">{stats.by_type.regular}</div>
                        </CardContent>
                    </Card>

                    <Card className="border-0 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-[#64748B]">Special</CardTitle>
                            <Star className="size-4 text-[#C9A227]" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-[#C9A227]">{stats.by_type.special}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Search & Filters */}
                <Card className="border-0 shadow-sm">
                    <CardContent className="pt-6">
                        <div className="flex flex-col sm:flex-row gap-4">
                            <form onSubmit={handleSearch} className="flex gap-2 flex-1">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#64748B]" />
                                    <Input
                                        placeholder="Search tournaments..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-10 border-[#E2E8F0] focus:border-[#004E86] focus:ring-[#004E86]"
                                    />
                                </div>
                                <Button type="submit" className="bg-[#004E86] hover:bg-[#003d6b]">
                                    Search
                                </Button>
                            </form>

                            <div className="flex gap-2">
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" className="border-[#E2E8F0]">
                                            <Filter className="size-4 mr-2" />
                                            Status
                                            {filters.status && (
                                                <Badge variant="secondary" className="ml-2">
                                                    {statuses.find(s => s.value === filters.status)?.label || filters.status}
                                                </Badge>
                                            )}
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent>
                                        <DropdownMenuItem onClick={() => handleFilter('status', undefined)}>
                                            All Statuses
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        {statuses.map((status) => (
                                            <DropdownMenuItem
                                                key={status.value}
                                                onClick={() => handleFilter('status', status.value)}
                                            >
                                                {status.label}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" className="border-[#E2E8F0]">
                                            <Filter className="size-4 mr-2" />
                                            Type
                                            {filters.type && (
                                                <Badge variant="secondary" className="ml-2">
                                                    {types.find(t => t.value === filters.type)?.label || filters.type}
                                                </Badge>
                                            )}
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent>
                                        <DropdownMenuItem onClick={() => handleFilter('type', undefined)}>
                                            All Types
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        {types.map((type) => (
                                            <DropdownMenuItem
                                                key={type.value}
                                                onClick={() => handleFilter('type', type.value)}
                                            >
                                                {type.label}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tournaments Table */}
                <Card className="border-0 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-[#0A1628]">All Tournaments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tournaments.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Trophy className="size-16 text-[#E2E8F0] mb-4" />
                                <h3 className="text-lg font-semibold text-[#0A1628]">No tournaments found</h3>
                                <p className="text-[#64748B]">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-[#F8FAFC]">
                                        <TableHead className="font-semibold text-[#0A1628]">Tournament</TableHead>
                                        <TableHead className="font-semibold text-[#0A1628]">Organizer</TableHead>
                                        <TableHead className="font-semibold text-[#0A1628]">Status</TableHead>
                                        <TableHead className="font-semibold text-[#0A1628]">Type</TableHead>
                                        <TableHead className="font-semibold text-[#0A1628]">Location</TableHead>
                                        <TableHead className="font-semibold text-[#0A1628]">Participants</TableHead>
                                        <TableHead className="font-semibold text-[#0A1628]">Start Date</TableHead>
                                        <TableHead className="text-right font-semibold text-[#0A1628]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tournaments.data.map((tournament) => (
                                        <TableRow key={tournament.id} className="hover:bg-[#F8FAFC]">
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium text-[#0A1628]">{tournament.name}</p>
                                                    <p className="text-xs text-[#64748B]">
                                                        ID: {tournament.id}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {tournament.organizer ? (
                                                    <div className="flex items-center gap-2">
                                                        <Avatar className="size-7 border border-[#E2E8F0]">
                                                            <AvatarImage
                                                                src={tournament.organizer.logo_url || undefined}
                                                            />
                                                            <AvatarFallback className="text-xs bg-[#004E86]/10 text-[#004E86]">
                                                                <Building2 className="size-3" />
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <span className="text-sm text-[#0A1628]">
                                                            {tournament.organizer.organization_name}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-[#64748B] text-sm">System</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={getStatusColor(tournament.status)}
                                                >
                                                    {tournament.status_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={getTypeColor(tournament.type)}
                                                >
                                                    {tournament.type_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {tournament.geographic_scope ? (
                                                    <div className="flex items-center gap-1 text-sm">
                                                        <MapPin className="size-3 text-[#64748B]" />
                                                        <span className="text-[#0A1628]">
                                                            {tournament.geographic_scope.name}
                                                        </span>
                                                        <span className="text-xs text-[#64748B]">
                                                            ({tournament.geographic_scope.level_label})
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-[#64748B]">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Users className="size-4 text-[#64748B]" />
                                                    <span className="text-[#0A1628]">
                                                        {tournament.participants_count}
                                                        {tournament.max_participants && (
                                                            <span className="text-[#64748B]">
                                                                /{tournament.max_participants}
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1 text-sm">
                                                    <Calendar className="size-3 text-[#64748B]" />
                                                    <span className="text-[#0A1628]">{formatDate(tournament.starts_at)}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm" className="size-8 p-0 hover:bg-[#F1F5F9]">
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                router.get(`/admin/tournaments/${tournament.id}`)
                                                            }
                                                        >
                                                            <Eye className="size-4 mr-2" />
                                                            View Details
                                                        </DropdownMenuItem>
                                                        {canApprove(tournament) && (
                                                            <DropdownMenuItem
                                                                onClick={() => handleApprove(tournament)}
                                                                className="text-green-600"
                                                            >
                                                                <CheckCircle className="size-4 mr-2" />
                                                                Approve
                                                            </DropdownMenuItem>
                                                        )}
                                                        {canReject(tournament) && (
                                                            <DropdownMenuItem
                                                                onClick={() => openRejectDialog(tournament)}
                                                                className="text-red-600"
                                                            >
                                                                <XCircle className="size-4 mr-2" />
                                                                Reject
                                                            </DropdownMenuItem>
                                                        )}
                                                        {canCancel(tournament) && (
                                                            <>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    onClick={() => openCancelDialog(tournament)}
                                                                    className="text-red-600"
                                                                >
                                                                    <AlertTriangle className="size-4 mr-2" />
                                                                    Cancel Tournament
                                                                </DropdownMenuItem>
                                                            </>
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        {/* Pagination */}
                        {tournaments.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t border-[#E2E8F0]">
                                <p className="text-sm text-[#64748B]">
                                    Page {tournaments.current_page} of {tournaments.last_page} (
                                    {tournaments.total} tournaments)
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={tournaments.current_page === 1}
                                        onClick={() =>
                                            router.get('/admin/tournaments', {
                                                ...filters,
                                                page: tournaments.current_page - 1,
                                            })
                                        }
                                        className="border-[#E2E8F0]"
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={tournaments.current_page === tournaments.last_page}
                                        onClick={() =>
                                            router.get('/admin/tournaments', {
                                                ...filters,
                                                page: tournaments.current_page + 1,
                                            })
                                        }
                                        className="border-[#E2E8F0]"
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Reject Dialog */}
            <Dialog open={rejectDialogOpen} onOpenChange={setRejectDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Tournament</DialogTitle>
                        <DialogDescription>
                            Please provide a reason for rejecting "{selectedTournament?.name}".
                            The organizer will be notified.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="rejectReason">Rejection Reason</Label>
                            <Textarea
                                id="rejectReason"
                                placeholder="Enter the reason for rejection..."
                                value={rejectReason}
                                onChange={(e) => setRejectReason(e.target.value)}
                                rows={4}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRejectDialogOpen(false)}
                            disabled={isSubmitting}
                        >
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
            <Dialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel Tournament</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to cancel "{selectedTournament?.name}"?
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="cancelReason">Reason (Optional)</Label>
                            <Textarea
                                id="cancelReason"
                                placeholder="Enter the reason for cancellation..."
                                value={cancelReason}
                                onChange={(e) => setCancelReason(e.target.value)}
                                rows={4}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setCancelDialogOpen(false)}
                            disabled={isSubmitting}
                        >
                            Go Back
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
