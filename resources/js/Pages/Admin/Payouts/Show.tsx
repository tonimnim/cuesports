import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    ArrowLeft,
    Building2,
    Wallet,
    CreditCard,
    CheckCircle,
    XCircle,
    Clock,
    User,
    Mail,
    Phone,
    Trophy,
    RefreshCw,
} from 'lucide-react';

interface PayoutMethod {
    id: number;
    type: string;
    type_label: string;
    account_name: string;
    account_number_masked: string;
    is_verified: boolean;
}

interface WalletInfo {
    balance: number;
    formatted_balance: string;
    total_earned: number;
    total_withdrawn: number;
}

interface Tournament {
    id: number;
    name: string;
    status: string;
    entry_fee: number;
    participants_count: number;
}

interface Organizer {
    id: number;
    organization_name: string;
    phone: string | null;
    user: {
        id: number;
        name: string;
        email: string;
    };
    wallet: WalletInfo | null;
}

interface Reviewer {
    id: number;
    name: string;
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
    approved_by: Reviewer | null;
    approved_at: string | null;
    approval_notes: string | null;
    rejection_reason: string | null;
    rejected_at: string | null;
    paid_at: string | null;
    payment_reference: string | null;
    created_at: string;
}

interface Props {
    payout: Payout;
    recentTournaments: Tournament[];
}

export default function AdminPayoutShow({ payout, recentTournaments }: Props) {
    const [actionType, setActionType] = useState<'approve' | 'reject' | 'retry' | null>(null);

    const approveForm = useForm({ notes: '' });
    const rejectForm = useForm({ reason: '' });
    const retryForm = useForm({});

    const handleApprove = (e: React.FormEvent) => {
        e.preventDefault();
        approveForm.post(`/admin/payouts/${payout.id}/approve`, {
            onSuccess: () => setActionType(null),
        });
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        rejectForm.post(`/admin/payouts/${payout.id}/reject`, {
            onSuccess: () => setActionType(null),
        });
    };

    const handleRetry = (e: React.FormEvent) => {
        e.preventDefault();
        retryForm.post(`/admin/payouts/${payout.id}/process`, {
            onSuccess: () => setActionType(null),
        });
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
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
                return <Badge variant="warning" className="text-sm">{label}</Badge>;
            case 'support_confirmed':
                return <Badge variant="info" className="text-sm">{label}</Badge>;
            case 'admin_approved':
                return <Badge variant="success" className="text-sm">{label}</Badge>;
            case 'processing':
                return <Badge variant="secondary" className="text-sm">{label}</Badge>;
            case 'completed':
                return <Badge variant="success" className="text-sm">{label}</Badge>;
            case 'rejected':
                return <Badge variant="destructive" className="text-sm">{label}</Badge>;
            default:
                return <Badge variant="outline" className="text-sm">{label}</Badge>;
        }
    };

    return (
        <AdminLayout>
            <Head title={`Payout #${payout.id}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/admin/payouts">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                Payout #{payout.id}
                            </h1>
                            <p className="text-muted-foreground">
                                Requested {formatDate(payout.created_at)}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        {getStatusBadge(payout.status, payout.status_label)}
                        {payout.status === 'support_confirmed' && (
                            <>
                                <Button variant="destructive" onClick={() => setActionType('reject')}>
                                    <XCircle className="size-4 mr-2" />
                                    Reject
                                </Button>
                                <Button onClick={() => setActionType('approve')}>
                                    <CheckCircle className="size-4 mr-2" />
                                    Approve & Pay
                                </Button>
                            </>
                        )}
                        {payout.status === 'admin_approved' && (
                            <Button onClick={() => setActionType('retry')}>
                                <RefreshCw className="size-4 mr-2" />
                                Retry Payment
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {/* Main Info */}
                    <div className="md:col-span-2 space-y-6">
                        {/* Payout Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Wallet className="size-5" />
                                    Payout Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Amount</p>
                                        <p className="text-2xl font-bold font-mono">
                                            {payout.formatted_amount}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Status</p>
                                        <div className="mt-1">
                                            {getStatusBadge(payout.status, payout.status_label)}
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                <div>
                                    <p className="text-sm text-muted-foreground mb-2">Payment Method</p>
                                    <div className="flex items-center gap-3 p-3 rounded-lg bg-muted">
                                        <CreditCard className="size-8 text-muted-foreground" />
                                        <div>
                                            <p className="font-medium">{payout.payout_method?.type_label}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {payout.payout_method?.account_name} - {payout.payout_method?.account_number_masked}
                                            </p>
                                        </div>
                                        {payout.payout_method?.is_verified && (
                                            <Badge variant="success" className="ml-auto">Verified</Badge>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Timeline */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock className="size-5" />
                                    Approval Timeline
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="flex gap-4">
                                        <div className="flex flex-col items-center">
                                            <div className="size-8 rounded-full bg-green-100 flex items-center justify-center">
                                                <CheckCircle className="size-4 text-green-600" />
                                            </div>
                                            <div className="w-0.5 h-full bg-border mt-2" />
                                        </div>
                                        <div className="pb-4">
                                            <p className="font-medium">Request Created</p>
                                            <p className="text-sm text-muted-foreground">
                                                {formatDate(payout.created_at)}
                                            </p>
                                        </div>
                                    </div>

                                    {payout.reviewed_at && (
                                        <div className="flex gap-4">
                                            <div className="flex flex-col items-center">
                                                <div className="size-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <User className="size-4 text-blue-600" />
                                                </div>
                                                <div className="w-0.5 h-full bg-border mt-2" />
                                            </div>
                                            <div className="pb-4">
                                                <p className="font-medium">Confirmed by Support</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {payout.reviewed_by?.name} - {formatDate(payout.reviewed_at)}
                                                </p>
                                                {payout.review_notes && (
                                                    <div className="text-sm mt-1 p-2 bg-blue-50 text-blue-700 rounded">
                                                        <span className="font-medium">Notes:</span> {payout.review_notes}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {payout.approved_at && (
                                        <div className="flex gap-4">
                                            <div className="flex flex-col items-center">
                                                <div className="size-8 rounded-full bg-green-100 flex items-center justify-center">
                                                    <CheckCircle className="size-4 text-green-600" />
                                                </div>
                                                <div className="w-0.5 h-full bg-border mt-2" />
                                            </div>
                                            <div className="pb-4">
                                                <p className="font-medium">Approved by Admin</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {payout.approved_by?.name} - {formatDate(payout.approved_at)}
                                                </p>
                                                {payout.approval_notes && (
                                                    <div className="text-sm mt-1 p-2 bg-muted rounded">
                                                        {payout.approval_notes}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {payout.rejected_at && (
                                        <div className="flex gap-4">
                                            <div className="flex flex-col items-center">
                                                <div className="size-8 rounded-full bg-red-100 flex items-center justify-center">
                                                    <XCircle className="size-4 text-red-600" />
                                                </div>
                                            </div>
                                            <div>
                                                <p className="font-medium text-red-600">Rejected</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatDate(payout.rejected_at)}
                                                </p>
                                                {payout.rejection_reason && (
                                                    <p className="text-sm mt-1 p-2 bg-red-50 text-red-700 rounded">
                                                        {payout.rejection_reason}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {payout.paid_at && (
                                        <div className="flex gap-4">
                                            <div className="flex flex-col items-center">
                                                <div className="size-8 rounded-full bg-green-100 flex items-center justify-center">
                                                    <CheckCircle className="size-4 text-green-600" />
                                                </div>
                                            </div>
                                            <div>
                                                <p className="font-medium text-green-600">Payment Completed</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatDate(payout.paid_at)}
                                                </p>
                                                {payout.payment_reference && (
                                                    <p className="text-xs font-mono mt-1">
                                                        Ref: {payout.payment_reference}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {payout.status === 'support_confirmed' && (
                                        <div className="flex gap-4">
                                            <div className="flex flex-col items-center">
                                                <div className="size-8 rounded-full bg-orange-100 flex items-center justify-center">
                                                    <Clock className="size-4 text-orange-600" />
                                                </div>
                                            </div>
                                            <div>
                                                <p className="font-medium text-orange-600">Awaiting Your Approval</p>
                                                <p className="text-sm text-muted-foreground">
                                                    Support has confirmed this request
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Recent Tournaments */}
                        {recentTournaments.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Trophy className="size-5" />
                                        Recent Tournaments
                                    </CardTitle>
                                    <CardDescription>
                                        Revenue sources for this organizer
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {recentTournaments.map((tournament) => (
                                            <div
                                                key={tournament.id}
                                                className="flex items-center justify-between p-3 rounded-lg bg-muted"
                                            >
                                                <div>
                                                    <p className="font-medium">{tournament.name}</p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {tournament.participants_count} participants
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-mono text-sm">
                                                        {formatCurrency(tournament.entry_fee)}
                                                    </p>
                                                    <Badge variant="outline" className="text-xs">
                                                        {tournament.status}
                                                    </Badge>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Organizer Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="size-5" />
                                    Organizer
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <Avatar className="size-12">
                                        <AvatarFallback>
                                            <Building2 className="size-6" />
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <p className="font-semibold">
                                            {payout.organizer?.organization_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {payout.organizer?.user?.name}
                                        </p>
                                    </div>
                                </div>

                                <Separator />

                                <div className="space-y-3">
                                    <div className="flex items-center gap-2 text-sm">
                                        <Mail className="size-4 text-muted-foreground" />
                                        <span>{payout.organizer?.user?.email}</span>
                                    </div>
                                    {payout.organizer?.phone && (
                                        <div className="flex items-center gap-2 text-sm">
                                            <Phone className="size-4 text-muted-foreground" />
                                            <span>{payout.organizer.phone}</span>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Wallet Info */}
                        {payout.organizer?.wallet && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Wallet className="size-5" />
                                        Wallet Balance
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Current Balance</p>
                                        <p className="text-xl font-bold font-mono">
                                            {payout.organizer.wallet.formatted_balance}
                                        </p>
                                    </div>
                                    <Separator />
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <p className="text-muted-foreground">Total Earned</p>
                                            <p className="font-medium font-mono">
                                                {formatCurrency(payout.organizer.wallet.total_earned)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">Total Withdrawn</p>
                                            <p className="font-medium font-mono">
                                                {formatCurrency(payout.organizer.wallet.total_withdrawn)}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            {/* Approve Dialog */}
            <Dialog open={actionType === 'approve'} onOpenChange={() => setActionType(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Approve Payout</DialogTitle>
                        <DialogDescription>
                            This will debit the wallet and initiate the payment to the organizer.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleApprove} className="space-y-4">
                        <div className="rounded-lg bg-muted p-4 text-center">
                            <p className="text-muted-foreground">Amount</p>
                            <p className="text-2xl font-bold font-mono">{payout.formatted_amount}</p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="approve-notes">Notes (Optional)</Label>
                            <Textarea
                                id="approve-notes"
                                placeholder="Add any notes..."
                                value={approveForm.data.notes}
                                onChange={(e) => approveForm.setData('notes', e.target.value)}
                                rows={3}
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setActionType(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={approveForm.processing}>
                                {approveForm.processing ? 'Processing...' : 'Approve & Pay'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={actionType === 'reject'} onOpenChange={() => setActionType(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Payout</DialogTitle>
                        <DialogDescription>
                            Provide a reason for rejection.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleReject} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="reject-reason">Reason *</Label>
                            <Textarea
                                id="reject-reason"
                                placeholder="Why is this being rejected?"
                                value={rejectForm.data.reason}
                                onChange={(e) => rejectForm.setData('reason', e.target.value)}
                                rows={4}
                                required
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setActionType(null)}>
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={rejectForm.processing || !rejectForm.data.reason.trim()}
                            >
                                {rejectForm.processing ? 'Rejecting...' : 'Reject'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Retry Dialog */}
            <Dialog open={actionType === 'retry'} onOpenChange={() => setActionType(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Retry Payment</DialogTitle>
                        <DialogDescription>
                            Attempt to process this payment again.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleRetry} className="space-y-4">
                        <div className="rounded-lg bg-muted p-4 text-center">
                            <p className="text-muted-foreground">Amount</p>
                            <p className="text-2xl font-bold font-mono">{payout.formatted_amount}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setActionType(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={retryForm.processing}>
                                {retryForm.processing ? 'Processing...' : 'Retry Payment'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
