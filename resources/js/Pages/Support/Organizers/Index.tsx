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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Search,
    MoreHorizontal,
    CheckCircle,
    XCircle,
    Eye,
    Filter,
    Building2,
    Trophy,
} from 'lucide-react';
import type { OrganizerListItem, PaginatedOrganizers } from '@/types';

interface Props {
    organizers: PaginatedOrganizers;
    filters: {
        search?: string;
        status?: string;
    };
}

export default function OrganizersIndex({ organizers, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        action: 'activate' | 'deactivate';
        organizer: OrganizerListItem | null;
    }>({ open: false, action: 'activate', organizer: null });
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/support/organizers', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key: string, value: string | undefined) => {
        router.get('/support/organizers', { ...filters, [key]: value, page: 1 }, { preserveState: true });
    };

    const openConfirmDialog = (organizer: OrganizerListItem, action: 'activate' | 'deactivate') => {
        setConfirmDialog({ open: true, action, organizer });
    };

    const handleConfirmAction = () => {
        if (!confirmDialog.organizer) return;
        setIsSubmitting(true);
        router.post(
            `/support/organizers/${confirmDialog.organizer.id}/${confirmDialog.action}`,
            {},
            {
                onFinish: () => {
                    setIsSubmitting(false);
                    setConfirmDialog({ open: false, action: 'activate', organizer: null });
                },
            }
        );
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    return (
        <SupportLayout>
            <Head title="Organizers" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Organizers</h1>
                        <p className="text-muted-foreground">Manage tournament organizers</p>
                    </div>
                    <Badge variant="secondary" className="text-base px-3 py-1">
                        <Building2 className="size-4 mr-1" />
                        {organizers.total} Organizers
                    </Badge>
                </div>

                {/* Search & Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col sm:flex-row gap-4">
                            <form onSubmit={handleSearch} className="flex gap-2 flex-1">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search by organization name, email..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                                <Button type="submit">Search</Button>
                            </form>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <Filter className="size-4 mr-2" />
                                        Status
                                        {filters.status && (
                                            <Badge variant="secondary" className="ml-2">{filters.status}</Badge>
                                        )}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem onClick={() => handleFilter('status', undefined)}>All</DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleFilter('status', 'active')}>Active</DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleFilter('status', 'inactive')}>Inactive</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </CardContent>
                </Card>

                {/* Organizers Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Organizers</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {organizers.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Building2 className="size-16 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold">No organizers found</h3>
                                <p className="text-muted-foreground">Try adjusting your search or filters</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Organization</TableHead>
                                        <TableHead>Contact</TableHead>
                                        <TableHead>Tournaments</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Country</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {organizers.data.map((org) => (
                                        <TableRow key={org.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-10">
                                                        <AvatarImage src={org.logo_url || undefined} />
                                                        <AvatarFallback className="bg-[#004E86]/10 text-[#004E86]">
                                                            {org.organization_name?.[0]?.toUpperCase() || 'O'}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="font-medium">{org.organization_name}</p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    <p>{org.user.email}</p>
                                                    <p className="text-muted-foreground">{org.user.phone_number}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Trophy className="size-4 text-[#C9A227]" />
                                                    <span className="font-medium">{org.tournaments_hosted}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={org.is_active ? 'default' : 'destructive'} className={org.is_active ? 'bg-green-600' : ''}>
                                                    {org.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{org.user.country?.name || '-'}</TableCell>
                                            <TableCell>{formatDate(org.created_at)}</TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm">
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem onClick={() => router.get(`/support/organizers/${org.id}`)}>
                                                            <Eye className="size-4 mr-2" />
                                                            View Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        {org.is_active ? (
                                                            <DropdownMenuItem
                                                                className="text-red-600 focus:text-red-600"
                                                                onClick={() => openConfirmDialog(org, 'deactivate')}
                                                            >
                                                                <XCircle className="size-4 mr-2" />
                                                                Deactivate
                                                            </DropdownMenuItem>
                                                        ) : (
                                                            <DropdownMenuItem
                                                                className="text-green-600 focus:text-green-600"
                                                                onClick={() => openConfirmDialog(org, 'activate')}
                                                            >
                                                                <CheckCircle className="size-4 mr-2" />
                                                                Activate
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
                        {organizers.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Page {organizers.current_page} of {organizers.last_page} ({organizers.total} organizers)
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={organizers.current_page === 1}
                                        onClick={() => router.get('/support/organizers', { ...filters, page: organizers.current_page - 1 })}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={organizers.current_page === organizers.last_page}
                                        onClick={() => router.get('/support/organizers', { ...filters, page: organizers.current_page + 1 })}
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
            <Dialog open={confirmDialog.open} onOpenChange={(open) => setConfirmDialog({ ...confirmDialog, open })}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {confirmDialog.action === 'activate' ? 'Activate Organizer' : 'Deactivate Organizer'}
                        </DialogTitle>
                        <DialogDescription>
                            {confirmDialog.action === 'activate'
                                ? 'This will allow the organizer to create and manage tournaments.'
                                : 'This will revoke API credentials and prevent the organizer from creating tournaments.'}
                        </DialogDescription>
                    </DialogHeader>
                    {confirmDialog.organizer && (
                        <div className="flex items-center gap-3 p-4 rounded-lg bg-muted">
                            <Avatar>
                                <AvatarImage src={confirmDialog.organizer.logo_url || undefined} />
                                <AvatarFallback>{confirmDialog.organizer.organization_name?.[0]}</AvatarFallback>
                            </Avatar>
                            <div>
                                <p className="font-medium">{confirmDialog.organizer.organization_name}</p>
                                <p className="text-sm text-muted-foreground">{confirmDialog.organizer.user.email}</p>
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmDialog({ ...confirmDialog, open: false })}>
                            Cancel
                        </Button>
                        <Button
                            variant={confirmDialog.action === 'deactivate' ? 'destructive' : 'default'}
                            onClick={handleConfirmAction}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Processing...' : confirmDialog.action === 'activate' ? 'Activate' : 'Deactivate'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SupportLayout>
    );
}
