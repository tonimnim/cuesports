import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Search, CheckCircle, AlertTriangle, Eye } from 'lucide-react';
import type { Dispute, PaginatedDisputes } from '@/types';

interface Props {
    disputes: PaginatedDisputes;
    filters: {
        search?: string;
    };
}

export default function DisputesIndex({ disputes, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedDispute, setSelectedDispute] = useState<Dispute | null>(null);
    const [isResolving, setIsResolving] = useState(false);

    const resolveForm = useForm({
        player1_score: 0,
        player2_score: 0,
        notes: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/support/disputes', { search }, { preserveState: true });
    };

    const openResolveDialog = (dispute: Dispute) => {
        setSelectedDispute(dispute);
        resolveForm.setData({
            player1_score: dispute.player1_score || 0,
            player2_score: dispute.player2_score || 0,
            notes: '',
        });
        setIsResolving(true);
    };

    const handleResolve = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedDispute) return;

        resolveForm.post(`/support/disputes/${selectedDispute.id}/resolve`, {
            onSuccess: () => {
                setIsResolving(false);
                setSelectedDispute(null);
            },
        });
    };

    const getPlayerName = (player: Dispute['player1']) => {
        if (!player?.player_profile) return 'Unknown';
        const p = player.player_profile;
        return p.nickname || `${p.first_name} ${p.last_name}`;
    };

    const getInitials = (player: Dispute['player1']) => {
        if (!player?.player_profile) return 'U';
        const p = player.player_profile;
        return `${p.first_name?.[0] || ''}${p.last_name?.[0] || ''}`.toUpperCase() || 'U';
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

    return (
        <SupportLayout>
            <Head title="Disputes" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Disputes</h1>
                        <p className="text-muted-foreground">
                            Review and resolve match disputes
                        </p>
                    </div>
                    <Badge variant="warning" className="text-base px-3 py-1">
                        {disputes.total} Pending
                    </Badge>
                </div>

                {/* Search */}
                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={handleSearch} className="flex gap-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search by tournament, player name..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <Button type="submit">Search</Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Disputes Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Pending Disputes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {disputes.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <CheckCircle className="size-16 text-green-500 mb-4" />
                                <h3 className="text-lg font-semibold">No disputes found</h3>
                                <p className="text-muted-foreground">
                                    {filters.search
                                        ? 'Try adjusting your search criteria'
                                        : 'All disputes have been resolved'}
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Tournament</TableHead>
                                        <TableHead>Match</TableHead>
                                        <TableHead>Submitted Score</TableHead>
                                        <TableHead>Dispute Reason</TableHead>
                                        <TableHead>Disputed At</TableHead>
                                        <TableHead className="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {disputes.data.map((dispute) => (
                                        <TableRow key={dispute.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {dispute.tournament.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {dispute.round_name}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="flex items-center gap-2">
                                                        <Avatar className="size-6">
                                                            <AvatarImage
                                                                src={dispute.player1?.player_profile?.photo_url || undefined}
                                                            />
                                                            <AvatarFallback className="text-xs">
                                                                {getInitials(dispute.player1)}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <span className="text-sm">
                                                            {getPlayerName(dispute.player1)}
                                                        </span>
                                                    </div>
                                                    <span className="text-muted-foreground">vs</span>
                                                    <div className="flex items-center gap-2">
                                                        <Avatar className="size-6">
                                                            <AvatarImage
                                                                src={dispute.player2?.player_profile?.photo_url || undefined}
                                                            />
                                                            <AvatarFallback className="text-xs">
                                                                {getInitials(dispute.player2)}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <span className="text-sm">
                                                            {getPlayerName(dispute.player2)}
                                                        </span>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {dispute.player1_score ?? '-'} - {dispute.player2_score ?? '-'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <p className="text-sm max-w-[200px] truncate">
                                                    {dispute.dispute_reason || 'No reason provided'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(dispute.disputed_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link href={`/support/disputes/${dispute.id}`}>
                                                            <Eye className="size-4 mr-1" />
                                                            View
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        onClick={() => openResolveDialog(dispute)}
                                                    >
                                                        Resolve
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        {/* Pagination */}
                        {disputes.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Page {disputes.current_page} of {disputes.last_page}
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={disputes.current_page === 1}
                                        onClick={() =>
                                            router.get('/support/disputes', {
                                                ...filters,
                                                page: disputes.current_page - 1,
                                            })
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={disputes.current_page === disputes.last_page}
                                        onClick={() =>
                                            router.get('/support/disputes', {
                                                ...filters,
                                                page: disputes.current_page + 1,
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

            {/* Resolve Dialog */}
            <Dialog open={isResolving} onOpenChange={setIsResolving}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Resolve Dispute</DialogTitle>
                        <DialogDescription>
                            Enter the correct match score to resolve this dispute.
                        </DialogDescription>
                    </DialogHeader>

                    {selectedDispute && (
                        <form onSubmit={handleResolve} className="space-y-4">
                            {/* Match Info */}
                            <div className="rounded-lg bg-muted p-4">
                                <p className="text-sm font-medium mb-2">
                                    {selectedDispute.tournament.name} - {selectedDispute.round_name}
                                </p>
                                <div className="flex items-center justify-center gap-4">
                                    <div className="text-center">
                                        <Avatar className="size-10 mx-auto mb-1">
                                            <AvatarImage
                                                src={selectedDispute.player1?.player_profile?.photo_url || undefined}
                                            />
                                            <AvatarFallback>
                                                {getInitials(selectedDispute.player1)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <p className="text-sm font-medium">
                                            {getPlayerName(selectedDispute.player1)}
                                        </p>
                                    </div>
                                    <span className="text-lg font-bold text-muted-foreground">VS</span>
                                    <div className="text-center">
                                        <Avatar className="size-10 mx-auto mb-1">
                                            <AvatarImage
                                                src={selectedDispute.player2?.player_profile?.photo_url || undefined}
                                            />
                                            <AvatarFallback>
                                                {getInitials(selectedDispute.player2)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <p className="text-sm font-medium">
                                            {getPlayerName(selectedDispute.player2)}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Dispute Reason */}
                            {selectedDispute.dispute_reason && (
                                <div className="rounded-lg border border-orange-200 bg-orange-50 p-3">
                                    <div className="flex items-start gap-2">
                                        <AlertTriangle className="size-4 text-orange-600 mt-0.5" />
                                        <div>
                                            <p className="text-sm font-medium text-orange-800">
                                                Dispute Reason
                                            </p>
                                            <p className="text-sm text-orange-700">
                                                {selectedDispute.dispute_reason}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Score Inputs */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="player1_score">
                                        {getPlayerName(selectedDispute.player1)} Score
                                    </Label>
                                    <Input
                                        id="player1_score"
                                        type="number"
                                        min="0"
                                        value={resolveForm.data.player1_score}
                                        onChange={(e) =>
                                            resolveForm.setData('player1_score', parseInt(e.target.value) || 0)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="player2_score">
                                        {getPlayerName(selectedDispute.player2)} Score
                                    </Label>
                                    <Input
                                        id="player2_score"
                                        type="number"
                                        min="0"
                                        value={resolveForm.data.player2_score}
                                        onChange={(e) =>
                                            resolveForm.setData('player2_score', parseInt(e.target.value) || 0)
                                        }
                                    />
                                </div>
                            </div>

                            {/* Resolution Notes */}
                            <div className="space-y-2">
                                <Label htmlFor="notes">Resolution Notes (Optional)</Label>
                                <Textarea
                                    id="notes"
                                    placeholder="Add any notes about this resolution..."
                                    value={resolveForm.data.notes}
                                    onChange={(e) => resolveForm.setData('notes', e.target.value)}
                                    rows={3}
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsResolving(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={resolveForm.processing}>
                                    {resolveForm.processing ? 'Resolving...' : 'Resolve Dispute'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </SupportLayout>
    );
}
