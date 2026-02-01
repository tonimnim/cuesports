import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
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
import { Textarea } from '@/components/ui/textarea';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Search,
    MoreHorizontal,
    UserCheck,
    UserX,
    Eye,
    Filter,
    Users,
    Trophy,
    Star,
} from 'lucide-react';

interface Player {
    id: number;
    first_name: string;
    last_name: string;
    nickname: string | null;
    photo_url: string | null;
    rating: number;
    rating_category: string | null;
    total_matches: number;
    wins: number;
    losses: number;
    created_at: string;
    user: {
        id: number;
        email: string;
        phone_number: string | null;
        is_active: boolean;
        country: { id: number; name: string } | null;
    };
}

interface PaginatedPlayers {
    data: Player[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    players: PaginatedPlayers;
    filters: {
        search?: string;
        status?: string;
        rating?: string;
    };
}

export default function PlayersIndex({ players, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        action: 'reactivate' | 'deactivate';
        player: Player | null;
    }>({
        open: false,
        action: 'reactivate',
        player: null,
    });
    const [deactivateReason, setDeactivateReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/support/players', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key: string, value: string | undefined) => {
        router.get(
            '/support/players',
            { ...filters, [key]: value, page: 1 },
            { preserveState: true }
        );
    };

    const openConfirmDialog = (player: Player, action: 'reactivate' | 'deactivate') => {
        setConfirmDialog({ open: true, action, player });
        setDeactivateReason('');
    };

    const handleConfirmAction = () => {
        if (!confirmDialog.player) return;

        setIsSubmitting(true);
        router.post(
            `/support/players/${confirmDialog.player.id}/${confirmDialog.action}`,
            confirmDialog.action === 'deactivate' ? { reason: deactivateReason } : {},
            {
                onFinish: () => {
                    setIsSubmitting(false);
                    setConfirmDialog({ open: false, action: 'reactivate', player: null });
                },
            }
        );
    };

    const getDisplayName = (player: Player) => {
        return player.nickname || `${player.first_name} ${player.last_name}`;
    };

    const getInitials = (player: Player) => {
        const first = player.first_name?.[0] || '';
        const last = player.last_name?.[0] || '';
        return (first + last).toUpperCase() || 'P';
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
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

    const winRate = (player: Player) => {
        if (!player.total_matches) return '0.0';
        return ((player.wins / player.total_matches) * 100).toFixed(1);
    };

    return (
        <SupportLayout>
            <Head title="Players" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Players</h1>
                        <p className="text-muted-foreground">
                            Manage player accounts and profiles
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary" className="text-base px-3 py-1">
                            <Users className="size-4 mr-1" />
                            {players.total} Players
                        </Badge>
                    </div>
                </div>

                {/* Search & Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col sm:flex-row gap-4">
                            <form onSubmit={handleSearch} className="flex gap-2 flex-1">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search by name, nickname, email, or phone..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                                <Button type="submit">Search</Button>
                            </form>

                            <div className="flex gap-2">
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline">
                                            <Filter className="size-4 mr-2" />
                                            Status
                                            {filters.status && (
                                                <Badge variant="secondary" className="ml-2">
                                                    {filters.status}
                                                </Badge>
                                            )}
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent>
                                        <DropdownMenuItem onClick={() => handleFilter('status', undefined)}>
                                            All
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleFilter('status', 'active')}>
                                            Active
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleFilter('status', 'inactive')}>
                                            Inactive
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline">
                                            <Star className="size-4 mr-2" />
                                            Rating
                                            {filters.rating && (
                                                <Badge variant="secondary" className="ml-2">
                                                    {filters.rating}
                                                </Badge>
                                            )}
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent>
                                        <DropdownMenuItem onClick={() => handleFilter('rating', undefined)}>
                                            All Ratings
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem onClick={() => handleFilter('rating', 'pro')}>
                                            Pro (1800+)
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleFilter('rating', 'advanced')}>
                                            Advanced (1500-1799)
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleFilter('rating', 'intermediate')}>
                                            Intermediate (1200-1499)
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleFilter('rating', 'beginner')}>
                                            Beginner (&lt;1200)
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Players Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Players</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {players.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Users className="size-16 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold">No players found</h3>
                                <p className="text-muted-foreground">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Player</TableHead>
                                        <TableHead>Contact</TableHead>
                                        <TableHead>Rating</TableHead>
                                        <TableHead>Stats</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Country</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {players.data.map((player) => (
                                        <TableRow key={player.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-9 border">
                                                        <AvatarImage src={player.photo_url || undefined} />
                                                        <AvatarFallback className="text-xs bg-primary/10 text-primary">
                                                            {getInitials(player)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="font-medium">{getDisplayName(player)}</p>
                                                        {player.nickname && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {player.first_name} {player.last_name}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    <p>{player.user.email}</p>
                                                    <p className="text-muted-foreground">
                                                        {player.user.phone_number || '-'}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="space-y-1">
                                                    <p className="font-semibold">{player.rating}</p>
                                                    <Badge
                                                        variant="outline"
                                                        className={`text-xs ${getRatingColor(player.rating_category)}`}
                                                    >
                                                        {player.rating_category || 'Beginner'}
                                                    </Badge>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    <p>
                                                        <span className="text-green-600 font-medium">{player.wins}W</span>
                                                        {' / '}
                                                        <span className="text-red-600 font-medium">{player.losses}L</span>
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {winRate(player)}% win rate
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={player.user.is_active ? 'default' : 'destructive'}
                                                    className={player.user.is_active ? 'bg-green-100 text-green-700 hover:bg-green-100' : ''}
                                                >
                                                    {player.user.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {player.user.country?.name || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(player.created_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm" className="size-8 p-0">
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            onClick={() => router.get(`/support/players/${player.id}`)}
                                                        >
                                                            <Eye className="size-4 mr-2" />
                                                            View Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        {player.user.is_active ? (
                                                            <DropdownMenuItem
                                                                className="text-red-600 focus:text-red-600"
                                                                onClick={() => openConfirmDialog(player, 'deactivate')}
                                                            >
                                                                <UserX className="size-4 mr-2" />
                                                                Deactivate
                                                            </DropdownMenuItem>
                                                        ) : (
                                                            <DropdownMenuItem
                                                                className="text-green-600 focus:text-green-600"
                                                                onClick={() => openConfirmDialog(player, 'reactivate')}
                                                            >
                                                                <UserCheck className="size-4 mr-2" />
                                                                Reactivate
                                                            </DropdownMenuItem>
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
                        {players.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Page {players.current_page} of {players.last_page} ({players.total} players)
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={players.current_page === 1}
                                        onClick={() =>
                                            router.get('/support/players', {
                                                ...filters,
                                                page: players.current_page - 1,
                                            })
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={players.current_page === players.last_page}
                                        onClick={() =>
                                            router.get('/support/players', {
                                                ...filters,
                                                page: players.current_page + 1,
                                            })
                                        }
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Confirm Dialog */}
            <Dialog
                open={confirmDialog.open}
                onOpenChange={(open) => setConfirmDialog({ ...confirmDialog, open })}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {confirmDialog.action === 'reactivate'
                                ? 'Reactivate Player'
                                : 'Deactivate Player'}
                        </DialogTitle>
                        <DialogDescription>
                            {confirmDialog.action === 'reactivate'
                                ? 'This will restore access to the player account.'
                                : 'This will revoke all access tokens and prevent the player from logging in.'}
                        </DialogDescription>
                    </DialogHeader>

                    {confirmDialog.player && (
                        <div className="flex items-center gap-3 p-4 rounded-lg bg-muted">
                            <Avatar>
                                <AvatarImage src={confirmDialog.player.photo_url || undefined} />
                                <AvatarFallback>{getInitials(confirmDialog.player)}</AvatarFallback>
                            </Avatar>
                            <div>
                                <p className="font-medium">{getDisplayName(confirmDialog.player)}</p>
                                <p className="text-sm text-muted-foreground">
                                    {confirmDialog.player.user.email}
                                </p>
                            </div>
                            <Badge variant="outline" className="ml-auto">
                                {confirmDialog.player.rating} rating
                            </Badge>
                        </div>
                    )}

                    {confirmDialog.action === 'deactivate' && (
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Reason (optional)</label>
                            <Textarea
                                placeholder="Enter reason for deactivation..."
                                value={deactivateReason}
                                onChange={(e) => setDeactivateReason(e.target.value)}
                                rows={3}
                            />
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDialog({ ...confirmDialog, open: false })}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant={confirmDialog.action === 'deactivate' ? 'destructive' : 'default'}
                            onClick={handleConfirmAction}
                            disabled={isSubmitting}
                        >
                            {isSubmitting
                                ? 'Processing...'
                                : confirmDialog.action === 'reactivate'
                                ? 'Reactivate'
                                : 'Deactivate'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SupportLayout>
    );
}
