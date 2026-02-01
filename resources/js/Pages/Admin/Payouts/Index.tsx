import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
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
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Search,
    CheckCircle,
    Clock,
    XCircle,
    Eye,
    Building2,
    Wallet,
    DollarSign,
    TrendingUp,
} from 'lucide-react';

interface PayoutMethod {
    id: number;
    type: string;
    type_label: string;
    account_name: string;
    account_number_masked: string;
}

interface Reviewer {
    id: number;
    name: string;
}

interface Organizer {
    id: number;
    organization_name: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
}

interface Payout {
    id: number;
    amount: number;
    formatted_amount: string;
    currency: string;
    status: string;
    status_label: string;
    payout_method: PayoutMethod;
    organizer: Organizer;
    reviewed_by: Reviewer | null;
    reviewed_at: string | null;
    review_notes: string | null;
    created_at: string;
}

interface PaginatedPayouts {
    data: Payout[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Stats {
    pending_approval: number;
    processing: number;
    completed_today: number;
    total_paid_today: number;
}

interface Props {
    payouts: PaginatedPayouts;
    filters: {
        search?: string;
        status?: string;
    };
    stats: Stats;
}

export default function AdminPayoutsIndex({ payouts, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'support_confirmed');
    const [selectedPayout, setSelectedPayout] = useState<Payout | null>(null);
    const [actionType, setActionType] = useState<'approve' | 'reject' | null>(null);

    const approveForm = useForm({
        notes: '',
    });

    const rejectForm = useForm({
        reason: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/payouts', { search, status: statusFilter }, { preserveState: true });
    };

    const handleStatusChange = (value: string) => {
        setStatusFilter(value);
        router.get('/admin/payouts', { search, status: value }, { preserveState: true });
    };

    const openApproveDialog = (payout: Payout) => {
        setSelectedPayout(payout);
        setActionType('approve');
        approveForm.reset();
    };

    const openRejectDialog = (payout: Payout) => {
        setSelectedPayout(payout);
        setActionType('reject');
        rejectForm.reset();
    };

    const handleApprove = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedPayout) return;

        approveForm.post(`/admin/payouts/${selectedPayout.id}/approve`, {
            onSuccess: () => {
                setActionType(null);
                setSelectedPayout(null);
            },
        });
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedPayout) return;

        rejectForm.post(`/admin/payouts/${selectedPayout.id}/reject`, {
            onSuccess: () => {
                setActionType(null);
                setSelectedPayout(null);
            },
        });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatCurrency = (amount: number, currency: string = 'KES') => {
        return `${currency} ${(amount / 100).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    };

    const getStatusBadge = (status: string, label: string) => {
        switch (status) {
            case 'pending_review':
                return <Badge variant="warning">{label}</Badge>;
            case 'support_confirmed':
                return <Badge variant="info">{label}</Badge>;
            case 'admin_approved':
                return <Badge variant="success">{label}</Badge>;
            case 'processing':
                return <Badge variant="secondary">{label}</Badge>;
            case 'completed':
                return <Badge variant="success">{label}</Badge>;
            case 'rejected':
                return <Badge variant="destructive">{label}</Badge>;
            default:
                return <Badge variant="outline">{label}</Badge>;
        }
    };

    return (
        <AdminLayout>
            <Head title="Payouts" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Payouts</h1>
                    <p className="text-muted-foreground">
                        Approve organizer payout requests confirmed by support
                    </p>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pending Approval
                            </CardTitle>
                            <Clock className="size-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.pending_approval}</div>
                            <p className="text-xs text-muted-foreground">
                                Confirmed by support
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Processing
                            </CardTitle>
                            <Wallet className="size-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.processing}</div>
                            <p className="text-xs text-muted-foreground">
                                Being paid out
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Completed Today
                            </CardTitle>
                            <CheckCircle className="size-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.completed_today}</div>
                            <p className="text-xs text-muted-foreground">
                                Payouts completed
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Paid Today
                            </CardTitle>
                            <TrendingUp className="size-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold font-mono">
                                {formatCurrency(stats.total_paid_today)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Amount disbursed
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={handleSearch} className="flex gap-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search by organizer name..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <Select value={statusFilter} onValueChange={handleStatusChange}>
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Filter by status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="support_confirmed">Pending Approval</SelectItem>
                                    <SelectItem value="admin_approved">Approved</SelectItem>
                                    <SelectItem value="processing">Processing</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                    <SelectItem value="rejected">Rejected</SelectItem>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button type="submit">Search</Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Payouts Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Payout Requests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {payouts.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <CheckCircle className="size-16 text-green-500 mb-4" />
                                <h3 className="text-lg font-semibold">No payouts found</h3>
                                <p className="text-muted-foreground">
                                    {filters.search || filters.status !== 'support_confirmed'
                                        ? 'Try adjusting your filters'
                                        : 'No payout requests awaiting approval'}
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Organizer</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Payment Method</TableHead>
                                        <TableHead>Confirmed By</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payouts.data.map((payout) => (
                                        <TableRow key={payout.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-8">
                                                        <AvatarFallback>
                                                            <Building2 className="size-4" />
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <div className="font-medium">
                                                            {payout.organizer?.organization_name || 'Unknown'}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {payout.organizer?.user?.email}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <span className="font-mono font-semibold">
                                                    {payout.formatted_amount}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <div className="text-sm font-medium">
                                                        {payout.payout_method?.type_label}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {payout.payout_method?.account_number_masked}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {payout.reviewed_by ? (
                                                    <div>
                                                        <div className="text-sm">{payout.reviewed_by.name}</div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {payout.reviewed_at && formatDate(payout.reviewed_at)}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(payout.status, payout.status_label)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link href={`/admin/payouts/${payout.id}`}>
                                                            <Eye className="size-4 mr-1" />
                                                            View
                                                        </Link>
                                                    </Button>
                                                    {payout.status === 'support_confirmed' && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="destructive"
                                                                onClick={() => openRejectDialog(payout)}
                                                            >
                                                                <XCircle className="size-4 mr-1" />
                                                                Reject
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                onClick={() => openApproveDialog(payout)}
                                                            >
                                                                <CheckCircle className="size-4 mr-1" />
                                                                Approve
                                                            </Button>
                                                        </>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        {/* Pagination */}
                        {payouts.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Page {payouts.current_page} of {payouts.last_page}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={payouts.current_page === 1}
                                        onClick={() =>
                                            router.get('/admin/payouts', {
                                                ...filters,
                                                page: payouts.current_page - 1,
                                            })
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={payouts.current_page === payouts.last_page}
                                        onClick={() =>
                                            router.get('/admin/payouts', {
                                                ...filters,
                                                page: payouts.current_page + 1,
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

            {/* Approve Dialog */}
            <Dialog open={actionType === 'approve'} onOpenChange={() => setActionType(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Approve Payout</DialogTitle>
                        <DialogDescription>
                            This will debit the organizer's wallet and initiate the payment.
                        </DialogDescription>
                    </DialogHeader>

                    {selectedPayout && (
                        <form onSubmit={handleApprove} className="space-y-4">
                            <div className="rounded-lg bg-muted p-4">
                                <div className="text-center">
                                    <p className="text-muted-foreground">Amount to be paid</p>
                                    <p className="text-2xl font-bold font-mono">{selectedPayout.formatted_amount}</p>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        to {selectedPayout.organizer?.organization_name}
                                    </p>
                                </div>
                            </div>

                            {selectedPayout.review_notes && (
                                <div className="rounded-lg bg-blue-50 p-3">
                                    <p className="text-sm font-medium text-blue-800">Support Notes:</p>
                                    <p className="text-sm text-blue-700">{selectedPayout.review_notes}</p>
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="notes">Approval Notes (Optional)</Label>
                                <Textarea
                                    id="notes"
                                    placeholder="Add any notes..."
                                    value={approveForm.data.notes}
                                    onChange={(e) => approveForm.setData('notes', e.target.value)}
                                    rows={3}
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setActionType(null)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={approveForm.processing}>
                                    {approveForm.processing ? 'Approving...' : 'Approve & Pay'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={actionType === 'reject'} onOpenChange={() => setActionType(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Payout</DialogTitle>
                        <DialogDescription>
                            Provide a reason for rejecting this payout request.
                        </DialogDescription>
                    </DialogHeader>

                    {selectedPayout && (
                        <form onSubmit={handleReject} className="space-y-4">
                            <div className="rounded-lg bg-muted p-4">
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-muted-foreground">Organizer</p>
                                        <p className="font-medium">{selectedPayout.organizer?.organization_name}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Amount</p>
                                        <p className="font-mono font-semibold">{selectedPayout.formatted_amount}</p>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="reason">Rejection Reason *</Label>
                                <Textarea
                                    id="reason"
                                    placeholder="Explain why this payout is being rejected..."
                                    value={rejectForm.data.reason}
                                    onChange={(e) => rejectForm.setData('reason', e.target.value)}
                                    rows={3}
                                    required
                                />
                                {rejectForm.errors.reason && (
                                    <p className="text-sm text-destructive">{rejectForm.errors.reason}</p>
                                )}
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setActionType(null)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={rejectForm.processing || !rejectForm.data.reason.trim()}
                                >
                                    {rejectForm.processing ? 'Rejecting...' : 'Reject Payout'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
