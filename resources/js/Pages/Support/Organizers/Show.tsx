import { Head, Link, router } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    ArrowLeft,
    Building2,
    Mail,
    Phone,
    MapPin,
    Trophy,
    Calendar,
    Key,
    Clock,
    CheckCircle,
    XCircle,
    History,
} from 'lucide-react';
import type { OrganizerDetails, TournamentSummary, ActivityLogEntry } from '@/types';

interface Props {
    organizer: OrganizerDetails;
    tournaments: TournamentSummary[];
    activityLog: ActivityLogEntry[];
}

export default function OrganizerShow({ organizer, tournaments, activityLog }: Props) {
    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric',
        });
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'completed': return 'bg-green-100 text-green-800';
            case 'in_progress': return 'bg-blue-100 text-blue-800';
            case 'upcoming': return 'bg-yellow-100 text-yellow-800';
            case 'cancelled': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const handleActivate = () => router.post(`/support/organizers/${organizer.id}/activate`);
    const handleDeactivate = () => router.post(`/support/organizers/${organizer.id}/deactivate`);

    return (
        <SupportLayout>
            <Head title={organizer.organization_name} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/support/organizers"><ArrowLeft className="size-5" /></Link>
                    </Button>
                    <div className="flex-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold tracking-tight">{organizer.organization_name}</h1>
                            <Badge variant={organizer.is_active ? 'default' : 'destructive'} className={organizer.is_active ? 'bg-green-600' : ''}>
                                {organizer.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground">Organizer Profile</p>
                    </div>
                    {organizer.is_active ? (
                        <Button variant="destructive" onClick={handleDeactivate}>
                            <XCircle className="size-4 mr-2" />
                            Deactivate
                        </Button>
                    ) : (
                        <Button className="bg-green-600 hover:bg-green-700" onClick={handleActivate}>
                            <CheckCircle className="size-4 mr-2" />
                            Activate
                        </Button>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Info */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Profile Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="size-5 text-[#004E86]" />
                                    Organization Profile
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-start gap-6">
                                    <Avatar className="size-24 ring-2 ring-[#004E86]/20">
                                        <AvatarImage src={organizer.logo_url || undefined} />
                                        <AvatarFallback className="text-2xl bg-[#004E86]/10 text-[#004E86]">
                                            {organizer.organization_name?.[0]?.toUpperCase()}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="flex-1 space-y-4">
                                        <div>
                                            <h3 className="text-xl font-semibold">{organizer.organization_name}</h3>
                                            {organizer.description && (
                                                <p className="text-muted-foreground mt-1">{organizer.description}</p>
                                            )}
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="flex items-center gap-2 text-sm">
                                                <Mail className="size-4 text-muted-foreground" />
                                                <span>{organizer.user.email}</span>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm">
                                                <Phone className="size-4 text-muted-foreground" />
                                                <span>{organizer.user.phone_number}</span>
                                            </div>
                                            {organizer.user.country && (
                                                <div className="flex items-center gap-2 text-sm">
                                                    <MapPin className="size-4 text-muted-foreground" />
                                                    <span>{organizer.user.country.name}</span>
                                                </div>
                                            )}
                                            <div className="flex items-center gap-2 text-sm">
                                                <Calendar className="size-4 text-muted-foreground" />
                                                <span>Joined {formatDate(organizer.created_at)}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Tournaments */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between">
                                    <span className="flex items-center gap-2">
                                        <Trophy className="size-5 text-[#C9A227]" />
                                        Tournaments Hosted
                                    </span>
                                    <Badge variant="secondary">{tournaments.length}</Badge>
                                </CardTitle>
                                <CardDescription>Recent tournaments created by this organizer</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {tournaments.length === 0 ? (
                                    <div className="text-center py-8 text-muted-foreground">
                                        <Trophy className="size-12 mx-auto mb-2 opacity-50" />
                                        <p>No tournaments hosted yet</p>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Tournament</TableHead>
                                                <TableHead>Type</TableHead>
                                                <TableHead>Players</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead>Date</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {tournaments.map((t) => (
                                                <TableRow key={t.id}>
                                                    <TableCell className="font-medium">{t.name}</TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline" className="capitalize">{t.type}</Badge>
                                                    </TableCell>
                                                    <TableCell>{t.participants_count}</TableCell>
                                                    <TableCell>
                                                        <Badge className={getStatusColor(t.status)}>{t.status}</Badge>
                                                    </TableCell>
                                                    <TableCell>{formatDate(t.starts_at)}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Stats Card */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Statistics</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Tournaments Hosted</span>
                                    <span className="text-2xl font-bold text-[#004E86]">{organizer.tournaments_hosted}</span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">Account Status</span>
                                    <Badge variant={organizer.user.is_active ? 'default' : 'destructive'} className={organizer.user.is_active ? 'bg-green-600' : ''}>
                                        {organizer.user.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>

                        {/* API Credentials Card */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Key className="size-4" />
                                    API Credentials
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div>
                                    <span className="text-muted-foreground block mb-1">API Key</span>
                                    <code className="text-xs bg-slate-100 px-2 py-1 rounded">
                                        {organizer.api_key || 'Not generated'}
                                    </code>
                                </div>
                                {organizer.api_key_last_used_at && (
                                    <>
                                        <Separator />
                                        <div className="flex items-center gap-2">
                                            <Clock className="size-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">
                                                Last used: {formatDateTime(organizer.api_key_last_used_at)}
                                            </span>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Activity Log */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <History className="size-4" />
                                    Activity Log
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ScrollArea className="h-[250px]">
                                    {activityLog.length === 0 ? (
                                        <p className="text-sm text-muted-foreground text-center py-4">No activity yet</p>
                                    ) : (
                                        <div className="space-y-3">
                                            {activityLog.map((log) => (
                                                <div key={log.id} className="border-l-2 border-slate-200 pl-3 py-1">
                                                    <p className="text-sm font-medium">{log.action_label}</p>
                                                    <p className="text-xs text-muted-foreground">{log.description}</p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {formatDateTime(log.created_at)}
                                                        {log.performed_by && ` by ${log.performed_by.email}`}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </ScrollArea>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </SupportLayout>
    );
}
