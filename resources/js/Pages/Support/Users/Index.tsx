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
    UserCheck,
    UserX,
    Eye,
    Filter,
    Users,
} from 'lucide-react';
import type { UserListItem, PaginatedUsers } from '@/types';

interface Props {
    users: PaginatedUsers;
    filters: {
        search?: string;
        status?: string;
        role?: string;
    };
}

export default function UsersIndex({ users, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        action: 'reactivate' | 'deactivate';
        user: UserListItem | null;
    }>({
        open: false,
        action: 'reactivate',
        user: null,
    });
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/support/users', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key: string, value: string | undefined) => {
        router.get(
            '/support/users',
            { ...filters, [key]: value, page: 1 },
            { preserveState: true }
        );
    };

    const openConfirmDialog = (user: UserListItem, action: 'reactivate' | 'deactivate') => {
        setConfirmDialog({ open: true, action, user });
    };

    const handleConfirmAction = () => {
        if (!confirmDialog.user) return;

        setIsSubmitting(true);
        router.post(
            `/support/users/${confirmDialog.user.id}/${confirmDialog.action}`,
            {},
            {
                onFinish: () => {
                    setIsSubmitting(false);
                    setConfirmDialog({ open: false, action: 'reactivate', user: null });
                },
            }
        );
    };

    const getDisplayName = (user: UserListItem) => {
        if (user.player_profile) {
            return (
                user.player_profile.nickname ||
                `${user.player_profile.first_name} ${user.player_profile.last_name}`
            );
        }
        return user.email;
    };

    const getInitials = (user: UserListItem) => {
        if (user.player_profile) {
            const first = user.player_profile.first_name?.[0] || '';
            const last = user.player_profile.last_name?.[0] || '';
            return (first + last).toUpperCase() || 'U';
        }
        return user.email[0].toUpperCase();
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
            <Head title="Users" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Users</h1>
                        <p className="text-muted-foreground">
                            Manage user accounts and status
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary" className="text-base px-3 py-1">
                            <Users className="size-4 mr-1" />
                            {users.total} Users
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
                                        placeholder="Search by email, phone, or name..."
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
                                            <Filter className="size-4 mr-2" />
                                            Role
                                            {filters.role && (
                                                <Badge variant="secondary" className="ml-2">
                                                    {filters.role}
                                                </Badge>
                                            )}
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent>
                                        <DropdownMenuItem onClick={() => handleFilter('role', undefined)}>
                                            All
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleFilter('role', 'player')}>
                                            Player
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleFilter('role', 'organizer')}>
                                            Organizer
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleFilter('role', 'support')}>
                                            Support
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onClick={() => handleFilter('role', 'admin')}>
                                            Admin
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Users Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Users</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {users.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Users className="size-16 text-muted-foreground mb-4" />
                                <h3 className="text-lg font-semibold">No users found</h3>
                                <p className="text-muted-foreground">
                                    Try adjusting your search or filters
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Contact</TableHead>
                                        <TableHead>Rating</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Country</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-8">
                                                        <AvatarImage
                                                            src={user.player_profile?.photo_url || undefined}
                                                        />
                                                        <AvatarFallback className="text-xs">
                                                            {getInitials(user)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p className="font-medium">
                                                            {getDisplayName(user)}
                                                        </p>
                                                        {user.player_profile?.nickname && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {user.player_profile.first_name}{' '}
                                                                {user.player_profile.last_name}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    <p>{user.email}</p>
                                                    <p className="text-muted-foreground">
                                                        {user.phone_number}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {user.player_profile ? (
                                                    <div className="text-sm">
                                                        <p className="font-medium">
                                                            {user.player_profile.rating}
                                                        </p>
                                                        <Badge variant="outline" className="text-xs">
                                                            {user.player_profile.rating_category}
                                                        </Badge>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={user.is_active ? 'success' : 'destructive'}
                                                >
                                                    {user.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {user.country?.name || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(user.created_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon-sm">
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                router.get(`/support/users/${user.id}`)
                                                            }
                                                        >
                                                            <Eye className="size-4 mr-2" />
                                                            View Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        {user.is_active ? (
                                                            <DropdownMenuItem
                                                                className="text-red-600 focus:text-red-600"
                                                                onClick={() =>
                                                                    openConfirmDialog(user, 'deactivate')
                                                                }
                                                            >
                                                                <UserX className="size-4 mr-2" />
                                                                Deactivate
                                                            </DropdownMenuItem>
                                                        ) : (
                                                            <DropdownMenuItem
                                                                className="text-green-600 focus:text-green-600"
                                                                onClick={() =>
                                                                    openConfirmDialog(user, 'reactivate')
                                                                }
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
                        {users.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Page {users.current_page} of {users.last_page} ({users.total} users)
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={users.current_page === 1}
                                        onClick={() =>
                                            router.get('/support/users', {
                                                ...filters,
                                                page: users.current_page - 1,
                                            })
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={users.current_page === users.last_page}
                                        onClick={() =>
                                            router.get('/support/users', {
                                                ...filters,
                                                page: users.current_page + 1,
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
                onOpenChange={(open) =>
                    setConfirmDialog({ ...confirmDialog, open })
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {confirmDialog.action === 'reactivate'
                                ? 'Reactivate User'
                                : 'Deactivate User'}
                        </DialogTitle>
                        <DialogDescription>
                            {confirmDialog.action === 'reactivate'
                                ? 'This will restore access to the user account.'
                                : 'This will revoke all access tokens and prevent the user from logging in.'}
                        </DialogDescription>
                    </DialogHeader>

                    {confirmDialog.user && (
                        <div className="flex items-center gap-3 p-4 rounded-lg bg-muted">
                            <Avatar>
                                <AvatarImage
                                    src={confirmDialog.user.player_profile?.photo_url || undefined}
                                />
                                <AvatarFallback>
                                    {getInitials(confirmDialog.user)}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <p className="font-medium">
                                    {getDisplayName(confirmDialog.user)}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {confirmDialog.user.email}
                                </p>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() =>
                                setConfirmDialog({ ...confirmDialog, open: false })
                            }
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
