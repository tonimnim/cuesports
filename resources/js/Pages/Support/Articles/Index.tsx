import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Search,
    Plus,
    Eye,
    Pencil,
    Trash2,
    MoreHorizontal,
    FileText,
    Star,
    StarOff,
    Globe,
    Archive,
} from 'lucide-react';

interface Category {
    value: string;
    label: string;
    color: string;
}

interface Status {
    value: string;
    label: string;
}

interface Article {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    featured_image_url: string | null;
    category: Category;
    author: string;
    status: Status;
    is_featured: boolean;
    view_count: number;
    read_time: string;
    formatted_date: string | null;
    created_at: string;
}

interface PaginatedArticles {
    data: Article[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    articles: PaginatedArticles;
    stats: {
        total: number;
        published: number;
        draft: number;
        featured: number;
    };
    filters: {
        search?: string;
        status?: string;
        category?: string;
    };
    categories: Category[];
    statuses: Status[];
}

export default function ArticlesIndex({ articles, stats, filters, categories, statuses }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [categoryFilter, setCategoryFilter] = useState(filters.category || 'all');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/support/articles', {
            search,
            status: statusFilter !== 'all' ? statusFilter : undefined,
            category: categoryFilter !== 'all' ? categoryFilter : undefined,
        }, { preserveState: true });
    };

    const handleFilterChange = (type: 'status' | 'category', value: string) => {
        if (type === 'status') {
            setStatusFilter(value);
        } else {
            setCategoryFilter(value);
        }
        router.get('/support/articles', {
            search,
            status: type === 'status' ? (value !== 'all' ? value : undefined) : (statusFilter !== 'all' ? statusFilter : undefined),
            category: type === 'category' ? (value !== 'all' ? value : undefined) : (categoryFilter !== 'all' ? categoryFilter : undefined),
        }, { preserveState: true });
    };

    const handlePublish = (article: Article) => {
        router.post(`/support/articles/${article.id}/publish`, {}, { preserveState: true });
    };

    const handleUnpublish = (article: Article) => {
        router.post(`/support/articles/${article.id}/unpublish`, {}, { preserveState: true });
    };

    const handleToggleFeatured = (article: Article) => {
        router.post(`/support/articles/${article.id}/toggle-featured`, {}, { preserveState: true });
    };

    const handleDelete = (article: Article) => {
        if (confirm(`Are you sure you want to delete "${article.title}"?`)) {
            router.delete(`/support/articles/${article.id}`);
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const getStatusBadge = (status: Status) => {
        switch (status.value) {
            case 'published':
                return <Badge variant="success">{status.label}</Badge>;
            case 'draft':
                return <Badge variant="secondary">{status.label}</Badge>;
            case 'archived':
                return <Badge variant="outline">{status.label}</Badge>;
            default:
                return <Badge variant="outline">{status.label}</Badge>;
        }
    };

    const getCategoryBadge = (category: Category) => {
        const colorMap: Record<string, string> = {
            primary: 'bg-primary/10 text-primary',
            gold: 'bg-yellow-500/10 text-yellow-600',
            green: 'bg-green-500/10 text-green-600',
            purple: 'bg-purple-500/10 text-purple-600',
            blue: 'bg-blue-500/10 text-blue-600',
        };
        return (
            <span className={`px-2 py-1 rounded-full text-xs font-medium ${colorMap[category.color] || 'bg-muted text-muted-foreground'}`}>
                {category.label}
            </span>
        );
    };

    return (
        <SupportLayout>
            <Head title="Articles" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Articles</h1>
                        <p className="text-muted-foreground">
                            Manage news articles and blog posts
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/support/articles/create">
                            <Plus className="size-4 mr-2" />
                            New Article
                        </Link>
                    </Button>
                </div>

                {/* Filters - Inline like Admin */}
                <form onSubmit={handleSearch} className="flex items-center gap-4">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search articles..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-10"
                        />
                    </div>
                    <Select value={statusFilter} onValueChange={(v) => handleFilterChange('status', v)}>
                        <SelectTrigger className="w-[140px]">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem key={status.value} value={status.value}>
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={categoryFilter} onValueChange={(v) => handleFilterChange('category', v)}>
                        <SelectTrigger className="w-[140px]">
                            <SelectValue placeholder="Category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Categories</SelectItem>
                            {categories.map((category) => (
                                <SelectItem key={category.value} value={category.value}>
                                    {category.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </form>

                {/* Table - Clean without card wrapper */}
                {articles.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <FileText className="size-12 text-muted-foreground/50 mb-4" />
                        <h3 className="text-lg font-semibold">No articles found</h3>
                        <p className="text-muted-foreground mb-4">
                            {filters.search || filters.status || filters.category
                                ? 'Try adjusting your filters'
                                : 'Create your first article to get started'}
                        </p>
                        <Button asChild>
                            <Link href="/support/articles/create">
                                <Plus className="size-4 mr-2" />
                                Create Article
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[400px]">Article</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Views</TableHead>
                                    <TableHead>Published</TableHead>
                                    <TableHead className="w-[50px]"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {articles.data.map((article) => (
                                    <TableRow key={article.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                {article.featured_image_url ? (
                                                    <img
                                                        src={article.featured_image_url}
                                                        alt=""
                                                        className="size-10 rounded-lg object-cover bg-muted"
                                                    />
                                                ) : (
                                                    <div className="size-10 rounded-lg bg-muted flex items-center justify-center">
                                                        <FileText className="size-5 text-muted-foreground" />
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium truncate max-w-[280px]">{article.title}</p>
                                                        {article.is_featured && (
                                                            <Star className="size-3.5 text-yellow-500 fill-yellow-500 shrink-0" />
                                                        )}
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        By {article.author} · {article.read_time}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {getCategoryBadge(article.category)}
                                        </TableCell>
                                        <TableCell>
                                            {getStatusBadge(article.status)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {article.view_count.toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {article.formatted_date || formatDate(article.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="size-8">
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <a href={`/news/${article.slug}`} target="_blank" rel="noopener noreferrer">
                                                            <Eye className="size-4 mr-2" />
                                                            View on Site
                                                        </a>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/support/articles/${article.id}/edit`}>
                                                            <Pencil className="size-4 mr-2" />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    {article.status.value === 'draft' ? (
                                                        <DropdownMenuItem onClick={() => handlePublish(article)}>
                                                            <Globe className="size-4 mr-2" />
                                                            Publish
                                                        </DropdownMenuItem>
                                                    ) : article.status.value === 'published' ? (
                                                        <DropdownMenuItem onClick={() => handleUnpublish(article)}>
                                                            <Archive className="size-4 mr-2" />
                                                            Unpublish
                                                        </DropdownMenuItem>
                                                    ) : null}
                                                    <DropdownMenuItem onClick={() => handleToggleFeatured(article)}>
                                                        {article.is_featured ? (
                                                            <>
                                                                <StarOff className="size-4 mr-2" />
                                                                Remove Featured
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Star className="size-4 mr-2" />
                                                                Mark Featured
                                                            </>
                                                        )}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        className="text-destructive"
                                                        onClick={() => handleDelete(article)}
                                                    >
                                                        <Trash2 className="size-4 mr-2" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {/* Pagination */}
                        {articles.last_page > 1 && (
                            <div className="flex items-center justify-between pt-4">
                                <p className="text-sm text-muted-foreground">
                                    Showing {articles.data.length} of {articles.total} articles
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={articles.current_page === 1}
                                        onClick={() =>
                                            router.get('/support/articles', {
                                                ...filters,
                                                page: articles.current_page - 1,
                                            })
                                        }
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={articles.current_page === articles.last_page}
                                        onClick={() =>
                                            router.get('/support/articles', {
                                                ...filters,
                                                page: articles.current_page + 1,
                                            })
                                        }
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </SupportLayout>
    );
}
