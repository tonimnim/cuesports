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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Search,
    MoreHorizontal,
    Eye,
    Filter,
    Trophy,
    Users,
    Calendar,
    Building2,
} from 'lucide-react';

interface Tournament {
    id: number;
    name: string;
    slug: string;
    status: string;
    type: string;
    format: string;
    participants_count: number;
    max_participants: number | null;
    starts_at: string | null;
    ends_at: string | null;
    created_at: string;
    organizer: {
        id: number;
        organization_name: string;
        logo_url: string | null;
    } | null;
}

interface PaginatedTournaments {
    data: Tournament[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    tournaments: PaginatedTournaments;
    filters: {
        search?: string;
        status?: string;
        type?: string;
    };
    statuses: string[];
    types: string[];
}

export default function TournamentsIndex({ tournaments, filters, statuses, types }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/support/tournaments', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key: string, value: string | undefined) => {
        router.get(
            '/support/tournaments',
            { ...filters, [key]: value, page: 1 },
            { preserveState: true }
        );
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

    const getTypeColor = (type: string) => {
        switch (type) {
            case 'open':
                return 'bg-green-50 text-green-700 border-green-200';
            case 'invitational':
                return 'bg-purple-50 text-purple-700 border-purple-200';
            case 'league':
                return 'bg-blue-50 text-blue-700 border-blue-200';
            case 'ranked':
                return 'bg-orange-50 text-orange-700 border-orange-200';
            default:
                return 'bg-gray-50 text-gray-700 border-gray-200';
        }
    };

    const formatStatus = (status: string) => {
        return status.split('_').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    };

    return (
        <SupportLayout>
            <Head title="Tournaments" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Tournaments</h1>
                        <p className="text-muted-foreground">
                            Manage and oversee tournament operations
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary" className="text-base px-3 py-1">
                            <Trophy className="size-4 mr-1" />
                            {tournaments.total} Tournaments
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
                                        placeholder="Search by tournament name, slug, or organizer..."
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
                                                    {formatStatus(filters.status)}
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
                                                key={status}
                                                onClick={() => handleFilter('status', status)}
                                            >
                                                {formatStatus(status)}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline">
                                            <Filter className="size-4 mr-2" />
                                            Type
                                            {filters.type && (
                                                <Badge variant="secondary" className="ml-2 capitalize">
                                                    {filters.type}
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
                                                key={type}
                                                onClick={() => handleFilter('type', type)}
                                                className="capitalize"
                                            >
                                                {type}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tournaments Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Tournaments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tournaments.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Trophy className="size-16 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold">No tournaments found</h3>
                                <p className="text-muted-foreground">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Tournament</TableHead>
                                        <TableHead>Organizer</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Type / Format</TableHead>
                                        <TableHead>Participants</TableHead>
                                        <TableHead>Dates</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tournaments.data.map((tournament) => (
                                        <TableRow key={tournament.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium">{tournament.name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        /{tournament.slug}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {tournament.organizer ? (
                                                    <div className="flex items-center gap-2">
                                                        <Avatar className="size-7">
                                                            <AvatarImage
                                                                src={tournament.organizer.logo_url || undefined}
                                                            />
                                                            <AvatarFallback className="text-xs bg-primary/10 text-primary">
                                                                <Building2 className="size-3" />
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <span className="text-sm">
                                                            {tournament.organizer.organization_name}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={getStatusColor(tournament.status)}
                                                >
                                                    {formatStatus(tournament.status)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="space-y-1">
                                                    <Badge
                                                        variant="outline"
                                                        className={`${getTypeColor(tournament.type)} capitalize`}
                                                    >
                                                        {tournament.type}
                                                    </Badge>
                                                    <p className="text-xs text-muted-foreground capitalize">
                                                        {tournament.format?.replace('_', ' ')}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Users className="size-4 text-muted-foreground" />
                                                    <span>
                                                        {tournament.participants_count}
                                                        {tournament.max_participants && (
                                                            <span className="text-muted-foreground">
                                                                /{tournament.max_participants}
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    <div className="flex items-center gap-1">
                                                        <Calendar className="size-3 text-muted-foreground" />
                                                        <span>{formatDate(tournament.starts_at)}</span>
                                                    </div>
                                                    {tournament.ends_at && (
                                                        <p className="text-xs text-muted-foreground">
                                                            to {formatDate(tournament.ends_at)}
                                                        </p>
                                                    )}
                                                </div>
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
                                                            onClick={() =>
                                                                router.get(`/support/tournaments/${tournament.id}`)
                                                            }
                                                        >
                                                            <Eye className="size-4 mr-2" />
                                                            View Details
                                                        </DropdownMenuItem>
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
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Page {tournaments.current_page} of {tournaments.last_page} (
                                    {tournaments.total} tournaments)
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={tournaments.current_page === 1}
                                        onClick={() =>
                                            router.get('/support/tournaments', {
                                                ...filters,
                                                page: tournaments.current_page - 1,
                                            })
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={tournaments.current_page === tournaments.last_page}
                                        onClick={() =>
                                            router.get('/support/tournaments', {
                                                ...filters,
                                                page: tournaments.current_page + 1,
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
        </SupportLayout>
    );
}
