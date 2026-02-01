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
    Building2,
    CheckCircle2,
    Clock,
    Eye,
    Ban,
    CheckCircle,
    Trophy,
    XCircle,
} from 'lucide-react';

interface OrganizerProfile {
    organization_name: string;
    description: string | null;
    is_active: boolean;
    tournaments_hosted: number;
    logo_url: string | null;
}

interface Organizer {
    id: number;
    email: string;
    phone_number: string;
    is_active: boolean;
    email_verified_at: string | null;
    phone_verified_at: string | null;
    created_at: string;
    organizer_profile: OrganizerProfile;
}

interface Props {
    organizers: Organizer[];
    stats: {
        total: number;
        verified: number;
        pending: number;
        total_tournaments: number;
    };
}

export default function OrganizersIndex({ organizers, stats }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');

    const filteredOrganizers = organizers.filter(organizer => {
        const matchesSearch = !searchQuery ||
            organizer.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
            organizer.phone_number.includes(searchQuery) ||
            organizer.organizer_profile?.organization_name?.toLowerCase().includes(searchQuery.toLowerCase());

        const matchesStatus = statusFilter === 'all' ||
            (statusFilter === 'verified' && organizer.organizer_profile?.is_active) ||
            (statusFilter === 'pending' && !organizer.organizer_profile?.is_active);

        return matchesSearch && matchesStatus;
    });

    const getInitials = (organizer: Organizer) => {
        if (organizer.organizer_profile?.organization_name) {
            return organizer.organizer_profile.organization_name.substring(0, 2).toUpperCase();
        }
        return organizer.email[0].toUpperCase();
    };

    const toggleVerification = (organizerId: number, currentStatus: boolean) => {
        router.post(`/admin/users/organizers/${organizerId}/toggle-verification`, {
            is_active: !currentStatus,
        }, {
            preserveScroll: true,
        });
    };

    const toggleUserStatus = (organizerId: number, currentStatus: boolean) => {
        router.post(`/admin/users/organizers/${organizerId}/toggle-status`, {
            is_active: !currentStatus,
        }, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Organizers - Admin" />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-[#0A1628]">Organizers</h1>
                    <p className="text-[#64748B]">
                        View and manage tournament organizers on the platform
                    </p>
                </div>

                {/* Filters */}
                <div className="flex flex-col sm:flex-row gap-4">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-[#64748B]" />
                        <Input
                            placeholder="Search organizers..."
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
                            <SelectItem value="all">All Organizers</SelectItem>
                            <SelectItem value="verified">Verified Only</SelectItem>
                            <SelectItem value="pending">Pending Only</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Table */}
                <Card className="border-0 shadow-sm">
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#F8FAFC]">
                                    <TableHead className="font-semibold text-[#0A1628]">Organization</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Contact</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Tournaments</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Verification</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Account</TableHead>
                                    <TableHead className="font-semibold text-[#0A1628]">Joined</TableHead>
                                    <TableHead className="w-[50px]"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredOrganizers.map((organizer) => (
                                    <TableRow key={organizer.id} className="hover:bg-[#F8FAFC]">
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-10 border-2 border-[#E2E8F0]">
                                                    <AvatarImage src={organizer.organizer_profile?.logo_url || undefined} />
                                                    <AvatarFallback className="bg-[#C9A227] text-white text-sm font-medium">
                                                        {getInitials(organizer)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="font-medium text-[#0A1628]">
                                                        {organizer.organizer_profile?.organization_name}
                                                    </p>
                                                    {organizer.organizer_profile?.description && (
                                                        <p className="text-sm text-[#64748B] truncate max-w-[200px]">
                                                            {organizer.organizer_profile.description}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="space-y-1">
                                                <p className="text-sm text-[#0A1628]">{organizer.email}</p>
                                                <p className="text-sm text-[#64748B]">{organizer.phone_number}</p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Trophy className="size-4 text-[#C9A227]" />
                                                <span className="font-semibold text-[#0A1628]">
                                                    {organizer.organizer_profile?.tournaments_hosted || 0}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {organizer.organizer_profile?.is_active ? (
                                                <Badge className="bg-green-100 text-green-700 hover:bg-green-100">
                                                    <CheckCircle2 className="size-3 mr-1" /> Verified
                                                </Badge>
                                            ) : (
                                                <Badge className="bg-yellow-100 text-yellow-700 hover:bg-yellow-100">
                                                    <Clock className="size-3 mr-1" /> Pending
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {organizer.is_active ? (
                                                <Badge className="bg-blue-100 text-blue-700 hover:bg-blue-100">Active</Badge>
                                            ) : (
                                                <Badge className="bg-red-100 text-red-700 hover:bg-red-100">Suspended</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-[#64748B]">
                                            {new Date(organizer.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="hover:bg-[#F1F5F9]">
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onClick={() => router.visit(`/admin/users/organizers/${organizer.id}`)}>
                                                        <Eye className="size-4 mr-2" /> View Profile
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onClick={() => toggleVerification(organizer.id, organizer.organizer_profile?.is_active || false)}
                                                        className={organizer.organizer_profile?.is_active ? 'text-yellow-600' : 'text-green-600'}
                                                    >
                                                        {organizer.organizer_profile?.is_active ? (
                                                            <><XCircle className="size-4 mr-2" /> Revoke Verification</>
                                                        ) : (
                                                            <><CheckCircle className="size-4 mr-2" /> Verify Organizer</>
                                                        )}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => toggleUserStatus(organizer.id, organizer.is_active)}
                                                        className={organizer.is_active ? 'text-red-600' : 'text-green-600'}
                                                    >
                                                        {organizer.is_active ? (
                                                            <><Ban className="size-4 mr-2" /> Suspend Account</>
                                                        ) : (
                                                            <><CheckCircle className="size-4 mr-2" /> Reactivate Account</>
                                                        )}
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {filteredOrganizers.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center py-12 text-[#64748B]">
                                            <Building2 className="size-12 mx-auto mb-4 text-[#E2E8F0]" />
                                            <p className="font-medium">No organizers found</p>
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
