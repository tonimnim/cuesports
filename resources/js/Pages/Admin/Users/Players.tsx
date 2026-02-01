import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminLayout } from '@/layouts/admin';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Search,
    MoreHorizontal,
    Users,
    UserCheck,
    UserX,
    Eye,
    Ban,
    CheckCircle,
    TrendingUp,
    TrendingDown,
} from 'lucide-react';

interface PlayerProfile {
    first_name: string;
    last_name: string;
    nickname: string | null;
    rating: number;
    photo_url: string | null;
    matches_played: number;
    wins: number;
    losses: number;
}

interface Player {
    id: number;
    email: string;
    phone_number: string;
    is_active: boolean;
    email_verified_at: string | null;
    phone_verified_at: string | null;
    created_at: string;
    player_profile: PlayerProfile;
}

interface Props {
    players: Player[];
    stats: {
        total: number;
        active: number;
        inactive: number;
        new_this_month: number;
    };
}

export default function PlayersIndex({ players, stats }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');

    const filteredPlayers = players.filter(player => {
        const matchesSearch = !searchQuery ||
            player.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
            player.phone_number.includes(searchQuery) ||
            player.player_profile?.first_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            player.player_profile?.last_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            player.player_profile?.nickname?.toLowerCase().includes(searchQuery.toLowerCase());

        const matchesStatus = statusFilter === 'all' ||
            (statusFilter === 'active' && player.is_active) ||
            (statusFilter === 'inactive' && !player.is_active);

        return matchesSearch && matchesStatus;
    });

    const getInitials = (player: Player) => {
        if (player.player_profile) {
            return `${player.player_profile.first_name[0]}${player.player_profile.last_name[0]}`;
        }
        return player.email[0].toUpperCase();
    };

    const toggleStatus = (playerId: number, currentStatus: boolean) => {
        router.post(`/admin/users/players/${playerId}/toggle-status`, {
            is_active: !currentStatus,
        }, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Players - Admin" />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-[#0A1628]">Players</h1>
                    <p className="text-[#64748B]">
                        View and manage registered players on the platform
                    </p>
                </div>

                {/* Filters */}
                <div className="flex flex-col sm:flex-row gap-4">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-[#64748B]" />
                        <Input
                            placeholder="Search players..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9 border-[#E2E8F0] focus:border-[#004E86] focus:ring-[#004E86]"
                        />
                    </div>
                    <Select value={statusFilter} onValueChange={setStatusFilter}>
                        <SelectTrigger className="w-[180px] border-[#E2E8F0]">
                            <SelectValue placeholder="Filter by status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Players</SelectItem>
                            <SelectItem value="active">Active Only</SelectItem>
                            <SelectItem value="inactive">Suspended Only</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Table */}
                <Card className="border-0 shadow-sm">
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#F8FAFC]">
                                    <TableHead className="font-semibold text-[#0A1628]">Player</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Contact</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Rating</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Record</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Status</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Joined</TableHead>
                                    <TableHead className="w-[50px]"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredPlayers.map((player) => (
                                    <TableRow key={player.id} className="hover:bg-[#F8FAFC]">
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-10 border-2 border-[#E2E8F0]">
                                                    <AvatarImage src={player.player_profile?.photo_url || undefined} />
                                                    <AvatarFallback className="bg-[#004E86] text-white text-sm font-medium">
                                                        {getInitials(player)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="font-medium text-[#0A1628]">
                                                        {player.player_profile?.first_name} {player.player_profile?.last_name}
                                                    </p>
                                                    {player.player_profile?.nickname && (
                                                        <p className="text-sm text-[#64748B]">"{player.player_profile.nickname}"</p>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="space-y-1">
                                                <p className="text-sm text-[#0A1628]">{player.email}</p>
                                                <p className="text-sm text-[#64748B]">{player.phone_number}</p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono font-semibold text-[#0A1628]">
                                                    {player.player_profile?.rating || 1000}
                                                </span>
                                                {(player.player_profile?.rating || 1000) > 1000 ? (
                                                    <TrendingUp className="size-4 text-green-600" />
                                                ) : (player.player_profile?.rating || 1000) < 1000 ? (
                                                    <TrendingDown className="size-4 text-red-600" />
                                                ) : null}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="text-sm">
                                                <span className="text-green-600 font-medium">{player.player_profile?.wins || 0}W</span>
                                                <span className="text-[#64748B] mx-1">-</span>
                                                <span className="text-red-600 font-medium">{player.player_profile?.losses || 0}L</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {player.is_active ? (
                                                <Badge className="bg-green-100 text-green-700 hover:bg-green-100">Active</Badge>
                                            ) : (
                                                <Badge className="bg-red-100 text-red-700 hover:bg-red-100">Suspended</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-[#64748B]">
                                            {new Date(player.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="hover:bg-[#F1F5F9]">
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onClick={() => router.visit(`/admin/users/players/${player.id}`)}>
                                                        <Eye className="size-4 mr-2" /> View Profile
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onClick={() => toggleStatus(player.id, player.is_active)}
                                                        className={player.is_active ? 'text-red-600' : 'text-green-600'}
                                                    >
                                                        {player.is_active ? (
                                                            <><Ban className="size-4 mr-2" /> Suspend Player</>
                                                        ) : (
                                                            <><CheckCircle className="size-4 mr-2" /> Reactivate Player</>
                                                        )}
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {filteredPlayers.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center py-12 text-[#64748B]">
                                            <Users className="size-12 mx-auto mb-4 text-[#E2E8F0]" />
                                            <p className="font-medium">No players found</p>
                                            <p className="text-sm">Try adjusting your search or filters</p>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
