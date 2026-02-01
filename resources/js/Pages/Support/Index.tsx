import { Head, Link } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { AlertTriangle, Users, CheckCircle, UserX, ArrowRight } from 'lucide-react';
import type { Dispute } from '@/types';

interface Stats {
    pending_disputes: number;
    resolved_today: number;
    inactive_users: number;
    total_users: number;
}

interface Props {
    stats: Stats;
    recentDisputes: Dispute[];
}

export default function SupportDashboard({ stats, recentDisputes }: Props) {
    const getPlayerName = (player: Dispute['player1']) => {
        if (!player?.player_profile) return 'Unknown';
        const p = player.player_profile;
        return p.nickname || `${p.first_name} ${p.last_name}`;
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <SupportLayout>
            <Head title="Support Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                    <p className="text-muted-foreground">
                        Overview of support tasks and recent activity
                    </p>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pending Disputes
                            </CardTitle>
                            <AlertTriangle className="size-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.pending_disputes}</div>
                            <p className="text-xs text-muted-foreground">
                                Requires resolution
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Resolved Today
                            </CardTitle>
                            <CheckCircle className="size-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.resolved_today}</div>
                            <p className="text-xs text-muted-foreground">
                                Disputes resolved
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Inactive Users
                            </CardTitle>
                            <UserX className="size-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.inactive_users}</div>
                            <p className="text-xs text-muted-foreground">
                                Deactivated accounts
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Users
                            </CardTitle>
                            <Users className="size-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_users}</div>
                            <p className="text-xs text-muted-foreground">
                                Registered users
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Disputes */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Recent Disputes</CardTitle>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/support/disputes">
                                View All
                                <ArrowRight className="ml-2 size-4" />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {recentDisputes.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-8 text-center">
                                <CheckCircle className="size-12 text-green-500 mb-4" />
                                <h3 className="font-semibold">No pending disputes</h3>
                                <p className="text-sm text-muted-foreground">
                                    All disputes have been resolved
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Tournament</TableHead>
                                        <TableHead>Players</TableHead>
                                        <TableHead>Submitted Score</TableHead>
                                        <TableHead>Disputed At</TableHead>
                                        <TableHead className="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentDisputes.map((dispute) => (
                                        <TableRow key={dispute.id}>
                                            <TableCell className="font-medium">
                                                {dispute.tournament.name}
                                                <div className="text-xs text-muted-foreground">
                                                    {dispute.round_name}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    {getPlayerName(dispute.player1)} vs{' '}
                                                    {getPlayerName(dispute.player2)}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {dispute.player1_score} - {dispute.player2_score}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(dispute.disputed_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button size="sm" asChild>
                                                    <Link href={`/support/disputes/${dispute.id}`}>
                                                        Resolve
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SupportLayout>
    );
}
