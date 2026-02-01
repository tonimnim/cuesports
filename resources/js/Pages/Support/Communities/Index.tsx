import { Head } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Users2, Construction } from 'lucide-react';

interface PaginatedCommunities {
    data: never[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    communities: PaginatedCommunities;
    filters: {
        search?: string;
        status?: string;
    };
}

export default function CommunitiesIndex({ communities }: Props) {
    return (
        <SupportLayout>
            <Head title="Communities" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Communities</h1>
                        <p className="text-muted-foreground">
                            Manage player communities and groups
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary" className="text-base px-3 py-1">
                            <Users2 className="size-4 mr-1" />
                            {communities.total} Communities
                        </Badge>
                    </div>
                </div>

                {/* Coming Soon Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Construction className="size-5 text-yellow-500" />
                            Coming Soon
                        </CardTitle>
                        <CardDescription>
                            Community management features are being developed
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <div className="size-24 rounded-full bg-primary/10 flex items-center justify-center mb-6">
                                <Users2 className="size-12 text-primary" />
                            </div>
                            <h3 className="text-xl font-semibold mb-2">Community Management</h3>
                            <p className="text-muted-foreground max-w-md mb-6">
                                This feature is currently under development. Soon you'll be able to manage
                                player communities, create groups, and organize local tournaments.
                            </p>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 w-full max-w-2xl">
                                <div className="p-4 rounded-lg bg-muted text-center">
                                    <p className="text-lg font-semibold text-primary">Groups</p>
                                    <p className="text-xs text-muted-foreground">Player groups</p>
                                </div>
                                <div className="p-4 rounded-lg bg-muted text-center">
                                    <p className="text-lg font-semibold text-primary">Clubs</p>
                                    <p className="text-xs text-muted-foreground">Local clubs</p>
                                </div>
                                <div className="p-4 rounded-lg bg-muted text-center">
                                    <p className="text-lg font-semibold text-primary">Leagues</p>
                                    <p className="text-xs text-muted-foreground">Community leagues</p>
                                </div>
                                <div className="p-4 rounded-lg bg-muted text-center">
                                    <p className="text-lg font-semibold text-primary">Events</p>
                                    <p className="text-xs text-muted-foreground">Local events</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SupportLayout>
    );
}
