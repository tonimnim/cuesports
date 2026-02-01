import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Search,
    History,
    User,
    Trophy,
    AlertTriangle,
    Building2,
    Eye,
    CheckCircle,
    XCircle,
    FileText,
    RefreshCw,
} from 'lucide-react';
import type { ActivityLogEntry } from '@/types';

interface PaginatedLogs {
    data: ActivityLogEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    logs: PaginatedLogs;
    filters: {
        search?: string;
        entity_type?: string;
        action?: string;
    };
    entityTypes: Record<string, string>;
}

export default function ActivityIndex({ logs, filters, entityTypes }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/support/activity', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key: string, value: string | undefined) => {
        router.get('/support/activity', { ...filters, [key]: value === 'all' ? undefined : value, page: 1 }, { preserveState: true });
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    };

    const formatRelativeTime = (dateString: string) => {
        const diffMs = Date.now() - new Date(dateString).getTime();
        const diffMins = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return formatDateTime(dateString);
    };

    const getActionIcon = (action: string) => {
        if (action.includes('viewed')) return <Eye className="size-4" />;
        if (action.includes('activated') || action.includes('reactivated')) return <CheckCircle className="size-4 text-green-600" />;
        if (action.includes('deactivated')) return <XCircle className="size-4 text-red-600" />;
        if (action.includes('resolved')) return <CheckCircle className="size-4 text-green-600" />;
        if (action.includes('note')) return <FileText className="size-4 text-blue-600" />;
        return <History className="size-4" />;
    };

    const getEntityIcon = (entityType: string) => {
        switch (entityType) {
            case 'user': return <User className="size-4" />;
            case 'match': return <AlertTriangle className="size-4" />;
            case 'tournament': return <Trophy className="size-4" />;
            case 'organizer': return <Building2 className="size-4" />;
            default: return <History className="size-4" />;
        }
    };

    const getActionColor = (action: string) => {
        if (action.includes('viewed')) return 'bg-slate-100 text-slate-700 border-slate-200';
        if (action.includes('activated') || action.includes('reactivated') || action.includes('resolved'))
            return 'bg-green-50 text-green-700 border-green-200';
        if (action.includes('deactivated')) return 'bg-red-50 text-red-700 border-red-200';
        if (action.includes('note')) return 'bg-blue-50 text-blue-700 border-blue-200';
        return 'bg-slate-100 text-slate-700 border-slate-200';
    };

    return (
        <SupportLayout>
            <Head title="Activity Log" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Activity Log</h1>
                        <p className="text-muted-foreground">Track all support team actions</p>
                    </div>
                    <Button variant="outline" onClick={() => router.reload()}>
                        <RefreshCw className="size-4 mr-2" />
                        Refresh
                    </Button>
                </div>

                {/* Search & Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col sm:flex-row gap-4">
                            <form onSubmit={handleSearch} className="flex gap-2 flex-1">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search by description, action, or email..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                                <Button type="submit">Search</Button>
                            </form>
                            <Select value={filters.entity_type || 'all'} onValueChange={(v) => handleFilter('entity_type', v)}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Entity Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    {Object.entries(entityTypes).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>{label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Activity Log */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <span className="flex items-center gap-2">
                                <History className="size-5 text-[#004E86]" />
                                Recent Activity
                            </span>
                            <Badge variant="secondary">{logs.total} entries</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {logs.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <History className="size-16 text-muted-foreground/50 mb-4" />
                                <h3 className="text-lg font-semibold">No activity found</h3>
                                <p className="text-muted-foreground">Try adjusting your search or filters</p>
                            </div>
                        ) : (
                            <ScrollArea className="h-[600px]">
                                <div className="space-y-3">
                                    {logs.data.map((log) => (
                                        <div
                                            key={log.id}
                                            className="flex items-start gap-4 p-4 rounded-lg border bg-white hover:bg-slate-50 transition-colors"
                                        >
                                            <div className={`flex size-10 items-center justify-center rounded-full ${getActionColor(log.action)}`}>
                                                {getActionIcon(log.action)}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <span className="font-medium">{log.action_label}</span>
                                                    <Badge variant="outline" className="text-xs gap-1">
                                                        {getEntityIcon(log.entity_type)}
                                                        {log.entity_type}
                                                    </Badge>
                                                    <span className="text-xs text-muted-foreground">#{log.entity_id}</span>
                                                </div>
                                                <p className="text-sm text-muted-foreground mt-1 truncate">{log.description}</p>
                                                <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                                                    <span title={formatDateTime(log.created_at)}>{formatRelativeTime(log.created_at)}</span>
                                                    {log.performed_by && (
                                                        <span className="flex items-center gap-1">
                                                            <User className="size-3" />
                                                            {log.performed_by.email}
                                                        </span>
                                                    )}
                                                    {log.ip_address && (
                                                        <span className="hidden sm:inline">IP: {log.ip_address}</span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </ScrollArea>
                        )}

                        {/* Pagination */}
                        {logs.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Page {logs.current_page} of {logs.last_page} ({logs.total} entries)
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={logs.current_page === 1}
                                        onClick={() => router.get('/support/activity', { ...filters, page: logs.current_page - 1 })}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={logs.current_page === logs.last_page}
                                        onClick={() => router.get('/support/activity', { ...filters, page: logs.current_page + 1 })}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SupportLayout>
    );
}
