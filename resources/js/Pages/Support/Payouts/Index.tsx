import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Search, CheckCircle, Clock, XCircle, Eye, Building2 } from 'lucide-react';

interface PayoutMethod {
    id: number;
    type: string;
    type_label: string;
    account_name: string;
    account_number_masked: string;
    display_name: string;
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

interface Props {
    payouts: PaginatedPayouts;
    filters: {
        search?: string;
        status?: string;
    };
    stats: {
        pending_review: number;
        support_confirmed: number;
    };
}

export default function PayoutsIndex({ payouts, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'pending_review');
    const [selectedPayout, setSelectedPayout] = useState<Payout | null>(null);
    const [actionType, setActionType] = useState<'confirm' | 'reject' | null>(null);

    const confirmForm = useForm({
        notes: '',
    });

    const rejectForm = useForm({
        reason: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/support/payouts', { search, status: statusFilter }, { preserveState: true });
    };

    const handleStatusChange = (value: string) => {
        setStatusFilter(value);
        router.get('/support/payouts', { search, status: value }, { preserveState: true });
    };

    const openConfirmDialog = (payout: Payout) => {
        setSelectedPayout(payout);
        setActionType('confirm');
        confirmForm.reset();
    };

    const openRejectDialog = (payout: Payout) => {
        setSelectedPayout(payout);
        setActionType('reject');
        rejectForm.reset();
    };

    const handleConfirm = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedPayout) return;

        confirmForm.post(`/support/payouts/${selectedPayout.id}/confirm`, {
            onSuccess: () => {
                setActionType(null);
                setSelectedPayout(null);
            },
        });
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedPayout) return;

        rejectForm.post(`/support/payouts/${selectedPayout.id}/reject`, {
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
        <SupportLayout>
            <Head title="Payouts" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Payouts</h1>
                        <p className="text-muted-foreground">
                            Review and confirm organizer payout requests
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Badge variant="warning" className="text-base px-3 py-1">
                            <Clock className="size-4 mr-1" />
                            {stats.pending_review} Pending
                        </Badge>
                        <Badge variant="info" className="text-base px-3 py-1">
                            <CheckCircle className="size-4 mr-1" />
                            {stats.support_confirmed} Confirmed
                        </Badge>
                    </div>
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
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Filter by status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending_review">Pending Review</SelectItem>
                                    <SelectItem value="support_confirmed">Confirmed</SelectItem>
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
                                    {filters.search || filters.status !== 'pending_review'
                                        ? 'Try adjusting your filters'
                                        : 'No payout requests pending review'}
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Organizer</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Payment Method</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Requested</TableHead>
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
                                                {getStatusBadge(payout.status, payout.status_label)}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(payout.created_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link href={`/support/payouts/${payout.id}`}>
                                                            <Eye className="size-4 mr-1" />
                                                            View
                                                        </Link>
                                                    </Button>
                                                    {payout.status === 'pending_review' && (
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
                                                                onClick={() => openConfirmDialog(payout)}
                                                            >
                                                                <CheckCircle className="size-4 mr-1" />
                                                                Confirm
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
                                            router.get('/support/payouts', {
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
                                            router.get('/support/payouts', {
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

            {/* Confirm Dialog */}
            <Dialog open={actionType === 'confirm'} onOpenChange={() => setActionType(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Payout</DialogTitle>
                        <DialogDescription>
                            Confirm this payout request to send it to admin for final approval.
                        </DialogDescription>
                    </DialogHeader>

                    {selectedPayout && (
                        <form onSubmit={handleConfirm} className="space-y-4">
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
                                    <div>
                                        <p className="text-muted-foreground">Payment Method</p>
                                        <p className="font-medium">{selectedPayout.payout_method?.display_name}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">Requested</p>
                                        <p className="font-medium">{formatDate(selectedPayout.created_at)}</p>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notes">Notes (Optional)</Label>
                                <Textarea
                                    id="notes"
                                    placeholder="Add any notes for the admin..."
                                    value={confirmForm.data.notes}
                                    onChange={(e) => confirmForm.setData('notes', e.target.value)}
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
                                <Button type="submit" disabled={confirmForm.processing}>
                                    {confirmForm.processing ? 'Confirming...' : 'Confirm Payout'}
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
        </SupportLayout>
    );
}
