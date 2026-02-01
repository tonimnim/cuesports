import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AdminLayout } from '@/layouts/admin';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Search,
    MoreHorizontal,
    UserCog,
    Shield,
    Plus,
    Edit,
    Trash2,
    Ban,
    CheckCircle,
    Mail,
    Phone,
    AlertCircle,
} from 'lucide-react';

interface SupportUser {
    id: number;
    email: string;
    phone_number: string;
    is_active: boolean;
    is_super_admin: boolean;
    is_support: boolean;
    email_verified_at: string | null;
    phone_verified_at: string | null;
    created_at: string;
    name?: string;
}

interface Props {
    supportUsers: SupportUser[];
    stats: {
        total: number;
        admins: number;
        support: number;
        active: number;
    };
}

export default function SupportIndex({ supportUsers, stats }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<SupportUser | null>(null);

    const createForm = useForm({
        name: '',
        email: '',
        phone_number: '',
        password: '',
        password_confirmation: '',
        is_super_admin: false,
    });

    const editForm = useForm({
        name: '',
        email: '',
        phone_number: '',
        is_super_admin: false,
    });

    const filteredUsers = supportUsers.filter(user => {
        if (!searchQuery) return true;
        const query = searchQuery.toLowerCase();
        return user.email.toLowerCase().includes(query) ||
            user.phone_number.includes(query) ||
            user.name?.toLowerCase().includes(query);
    });

    const getInitials = (user: SupportUser) => {
        if (user.name) {
            const parts = user.name.split(' ');
            return parts.length > 1 ? `${parts[0][0]}${parts[1][0]}` : user.name.substring(0, 2);
        }
        return user.email[0].toUpperCase();
    };

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/admin/users/support', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedUser) return;
        editForm.put(`/admin/users/support/${selectedUser.id}`, {
            onSuccess: () => {
                setIsEditOpen(false);
                setSelectedUser(null);
            },
        });
    };

    const handleDelete = () => {
        if (!selectedUser) return;
        router.delete(`/admin/users/support/${selectedUser.id}`, {
            onSuccess: () => {
                setIsDeleteOpen(false);
                setSelectedUser(null);
            },
        });
    };

    const openEditDialog = (user: SupportUser) => {
        setSelectedUser(user);
        editForm.setData({
            name: user.name || '',
            email: user.email,
            phone_number: user.phone_number,
            is_super_admin: user.is_super_admin,
        });
        setIsEditOpen(true);
    };

    const openDeleteDialog = (user: SupportUser) => {
        setSelectedUser(user);
        setIsDeleteOpen(true);
    };

    const toggleStatus = (userId: number, currentStatus: boolean) => {
        router.post(`/admin/users/support/${userId}/toggle-status`, {
            is_active: !currentStatus,
        }, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Support Staff - Admin" />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-[#0A1628]">Support Staff</h1>
                        <p className="text-[#64748B]">
                            Manage support staff and administrators
                        </p>
                    </div>
                    <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                        <DialogTrigger asChild>
                            <Button className="bg-[#004E86] hover:bg-[#003D6B] text-white">
                                <Plus className="size-4 mr-2" /> Add Staff Member
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle>Add Support Staff</DialogTitle>
                                <DialogDescription>
                                    Create a new support staff account. They will receive an email with login instructions.
                                </DialogDescription>
                            </DialogHeader>
                            <form onSubmit={handleCreate}>
                                <div className="grid gap-4 py-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Full Name</Label>
                                        <Input
                                            id="name"
                                            value={createForm.data.name}
                                            onChange={(e) => createForm.setData('name', e.target.value)}
                                            placeholder="John Doe"
                                            className="border-[#E2E8F0]"
                                        />
                                        {createForm.errors.name && (
                                            <p className="text-sm text-red-600">{createForm.errors.name}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={createForm.data.email}
                                            onChange={(e) => createForm.setData('email', e.target.value)}
                                            placeholder="john@cuesportsafrica.com"
                                            className="border-[#E2E8F0]"
                                        />
                                        {createForm.errors.email && (
                                            <p className="text-sm text-red-600">{createForm.errors.email}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="phone_number">Phone Number</Label>
                                        <Input
                                            id="phone_number"
                                            value={createForm.data.phone_number}
                                            onChange={(e) => createForm.setData('phone_number', e.target.value)}
                                            placeholder="+254700000000"
                                            className="border-[#E2E8F0]"
                                        />
                                        {createForm.errors.phone_number && (
                                            <p className="text-sm text-red-600">{createForm.errors.phone_number}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="password">Password</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={createForm.data.password}
                                            onChange={(e) => createForm.setData('password', e.target.value)}
                                            placeholder="••••••••"
                                            className="border-[#E2E8F0]"
                                        />
                                        {createForm.errors.password && (
                                            <p className="text-sm text-red-600">{createForm.errors.password}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="password_confirmation">Confirm Password</Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            value={createForm.data.password_confirmation}
                                            onChange={(e) => createForm.setData('password_confirmation', e.target.value)}
                                            placeholder="••••••••"
                                            className="border-[#E2E8F0]"
                                        />
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <input
                                            id="is_super_admin"
                                            type="checkbox"
                                            checked={createForm.data.is_super_admin}
                                            onChange={(e) => createForm.setData('is_super_admin', e.target.checked)}
                                            className="size-4 rounded border-[#E2E8F0] text-[#004E86]"
                                        />
                                        <Label htmlFor="is_super_admin" className="text-sm font-normal">
                                            Grant Super Admin privileges
                                        </Label>
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        className="bg-[#004E86] hover:bg-[#003D6B]"
                                        disabled={createForm.processing}
                                    >
                                        {createForm.processing ? 'Creating...' : 'Create Account'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Search */}
                <div className="flex items-center gap-4">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-[#64748B]" />
                        <Input
                            placeholder="Search staff..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9 border-[#E2E8F0] focus:border-[#004E86] focus:ring-[#004E86]"
                        />
                    </div>
                </div>

                {/* Table */}
                <Card className="border-0 shadow-sm">
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#F8FAFC]">
                                    <TableHead className="font-semibold text-[#0A1628]">Staff Member</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Contact</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Role</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Status</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Joined</TableHead>
                                    <TableHead className="w-[50px]"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredUsers.map((user) => (
                                    <TableRow key={user.id} className="hover:bg-[#F8FAFC]">
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-10 border-2 border-[#E2E8F0]">
                                                    <AvatarFallback className={user.is_super_admin ? 'bg-purple-600 text-white' : 'bg-[#004E86] text-white'}>
                                                        {getInitials(user)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="font-medium text-[#0A1628]">
                                                        {user.name || user.email}
                                                    </p>
                                                    {user.name && (
                                                        <p className="text-sm text-[#64748B]">{user.email}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="space-y-1">
                                                <p className="flex items-center gap-1 text-sm text-[#0A1628]">
                                                    <Mail className="size-3" /> {user.email}
                                                </p>
                                                <p className="flex items-center gap-1 text-sm text-[#64748B]">
                                                    <Phone className="size-3" /> {user.phone_number}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {user.is_super_admin ? (
                                                <Badge className="bg-purple-100 text-purple-700 hover:bg-purple-100">
                                                    <Shield className="size-3 mr-1" /> Super Admin
                                                </Badge>
                                            ) : (
                                                <Badge className="bg-blue-100 text-blue-700 hover:bg-blue-100">
                                                    <UserCog className="size-3 mr-1" /> Support
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {user.is_active ? (
                                                <Badge className="bg-green-100 text-green-700 hover:bg-green-100">Active</Badge>
                                            ) : (
                                                <Badge className="bg-red-100 text-red-700 hover:bg-red-100">Inactive</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-[#64748B]">
                                            {new Date(user.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="hover:bg-[#F1F5F9]">
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onClick={() => openEditDialog(user)}>
                                                        <Edit className="size-4 mr-2" /> Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => toggleStatus(user.id, user.is_active)}
                                                    >
                                                        {user.is_active ? (
                                                            <><Ban className="size-4 mr-2" /> Deactivate</>
                                                        ) : (
                                                            <><CheckCircle className="size-4 mr-2" /> Activate</>
                                                        )}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onClick={() => openDeleteDialog(user)}
                                                        className="text-red-600"
                                                    >
                                                        <Trash2 className="size-4 mr-2" /> Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {filteredUsers.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center py-12 text-[#64748B]">
                                            <UserCog className="size-12 mx-auto mb-4 text-[#E2E8F0]" />
                                            <p className="font-medium">No staff members found</p>
                                            <p className="text-sm">Add your first support staff member</p>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Edit Dialog */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Edit Staff Member</DialogTitle>
                        <DialogDescription>
                            Update the staff member's information.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleEdit}>
                        <div className="grid gap-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="edit-name">Full Name</Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(e) => editForm.setData('name', e.target.value)}
                                    className="border-[#E2E8F0]"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-email">Email</Label>
                                <Input
                                    id="edit-email"
                                    type="email"
                                    value={editForm.data.email}
                                    onChange={(e) => editForm.setData('email', e.target.value)}
                                    className="border-[#E2E8F0]"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-phone">Phone Number</Label>
                                <Input
                                    id="edit-phone"
                                    value={editForm.data.phone_number}
                                    onChange={(e) => editForm.setData('phone_number', e.target.value)}
                                    className="border-[#E2E8F0]"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    id="edit-is_super_admin"
                                    type="checkbox"
                                    checked={editForm.data.is_super_admin}
                                    onChange={(e) => editForm.setData('is_super_admin', e.target.checked)}
                                    className="size-4 rounded border-[#E2E8F0] text-[#004E86]"
                                />
                                <Label htmlFor="edit-is_super_admin" className="text-sm font-normal">
                                    Super Admin privileges
                                </Label>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsEditOpen(false)}>
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                className="bg-[#004E86] hover:bg-[#003D6B]"
                                disabled={editForm.processing}
                            >
                                {editForm.processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <AlertCircle className="size-5 text-red-600" />
                            Delete Staff Member
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete <strong>{selectedUser?.name || selectedUser?.email}</strong>?
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
