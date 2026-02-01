import { Head } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertTriangle,
    Users,
    Trophy,
    TrendingUp,
    Star,
    Calendar,
} from 'lucide-react';

interface Stats {
    pending_disputes: number;
    total_users: number;
    active_tournaments: number;
    completed_matches: number;
    average_rating: number;
    new_users_this_month: number;
}

interface Props {
    stats?: Stats;
}

export default function AdminDashboard({ stats }: Props) {
    const defaultStats: Stats = {
        pending_disputes: stats?.pending_disputes ?? 0,
        total_users: stats?.total_users ?? 0,
        active_tournaments: stats?.active_tournaments ?? 0,
        completed_matches: stats?.completed_matches ?? 0,
        average_rating: stats?.average_rating ?? 0,
        new_users_this_month: stats?.new_users_this_month ?? 0,
    };

    return (
        <AdminLayout>
            <Head title="Admin Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                    <p className="text-muted-foreground">
                        Overview of CueSports Africa platform
                    </p>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pending Disputes
                            </CardTitle>
                            <AlertTriangle className="size-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {defaultStats.pending_disputes}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Requires resolution
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
                            <div className="text-2xl font-bold">
                                {defaultStats.total_users}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Registered players
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Active Tournaments
                            </CardTitle>
                            <Trophy className="size-4 text-yellow-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {defaultStats.active_tournaments}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Currently running
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Completed Matches
                            </CardTitle>
                            <TrendingUp className="size-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {defaultStats.completed_matches}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                All time
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Average Rating
                            </CardTitle>
                            <Star className="size-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {defaultStats.average_rating.toFixed(0)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Platform average
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                New Users
                            </CardTitle>
                            <Calendar className="size-4 text-indigo-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {defaultStats.new_users_this_month}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                This month
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Quick Actions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <a
                                href="/admin/disputes"
                                className="flex items-center gap-3 rounded-lg border p-4 hover:bg-slate-50 transition-colors"
                            >
                                <AlertTriangle className="size-5 text-orange-500" />
                                <div>
                                    <p className="font-medium">Resolve Disputes</p>
                                    <p className="text-sm text-muted-foreground">
                                        Review pending
                                    </p>
                                </div>
                            </a>
                            <a
                                href="/admin/users"
                                className="flex items-center gap-3 rounded-lg border p-4 hover:bg-slate-50 transition-colors"
                            >
                                <Users className="size-5 text-blue-500" />
                                <div>
                                    <p className="font-medium">Manage Users</p>
                                    <p className="text-sm text-muted-foreground">
                                        View all users
                                    </p>
                                </div>
                            </a>
                            <a
                                href="/admin/tournaments"
                                className="flex items-center gap-3 rounded-lg border p-4 hover:bg-slate-50 transition-colors"
                            >
                                <Trophy className="size-5 text-yellow-500" />
                                <div>
                                    <p className="font-medium">Tournaments</p>
                                    <p className="text-sm text-muted-foreground">
                                        Create & manage
                                    </p>
                                </div>
                            </a>
                            <a
                                href="/admin/ratings"
                                className="flex items-center gap-3 rounded-lg border p-4 hover:bg-slate-50 transition-colors"
                            >
                                <Star className="size-5 text-purple-500" />
                                <div>
                                    <p className="font-medium">Ratings</p>
                                    <p className="text-sm text-muted-foreground">
                                        Adjust player ratings
                                    </p>
                                </div>
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
