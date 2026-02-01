import { Head, Link, useForm, router } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { ArrowLeft, Save, Eye, Globe, Archive, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Category {
    value: string;
    label: string;
    color?: string;
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
    content: string;
    category: Category;
    author: string;
    author_name: string | null;
    status: Status;
    is_featured: boolean;
    view_count: number;
    read_time: string;
    formatted_date: string | null;
    created_at: string;
}

interface Props {
    article: Article;
    categories: Category[];
}

export default function ArticleEdit({ article, categories }: Props) {
    const form = useForm({
        title: article.title,
        excerpt: article.excerpt,
        content: article.content,
        category: article.category.value,
        author_name: article.author_name || '',
        is_featured: article.is_featured,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/support/articles/${article.id}`);
    };

    const handlePublish = () => {
        router.post(`/support/articles/${article.id}/publish`);
    };

    const handleUnpublish = () => {
        router.post(`/support/articles/${article.id}/unpublish`);
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete "${article.title}"?`)) {
            router.delete(`/support/articles/${article.id}`);
        }
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

    return (
        <SupportLayout>
            <Head title={`Edit: ${article.title}`} />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/support/articles">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold tracking-tight">Edit Article</h1>
                                {getStatusBadge(article.status)}
                            </div>
                            <p className="text-muted-foreground">
                                {article.view_count.toLocaleString()} views · {article.read_time}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <a href={`/news/${article.slug}`} target="_blank" rel="noopener noreferrer">
                                <Eye className="size-4 mr-2" />
                                Preview
                            </a>
                        </Button>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="grid lg:grid-cols-3 gap-6">
                        {/* Main Content */}
                        <div className="lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Content</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="title">Title *</Label>
                                        <Input
                                            id="title"
                                            value={form.data.title}
                                            onChange={(e) => form.setData('title', e.target.value)}
                                            placeholder="Enter article title..."
                                            required
                                        />
                                        {form.errors.title && (
                                            <p className="text-sm text-destructive">{form.errors.title}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="excerpt">Excerpt *</Label>
                                        <Textarea
                                            id="excerpt"
                                            value={form.data.excerpt}
                                            onChange={(e) => form.setData('excerpt', e.target.value)}
                                            placeholder="Brief summary of the article (shown in listings and SEO)..."
                                            rows={3}
                                            required
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {form.data.excerpt.length}/500 characters
                                        </p>
                                        {form.errors.excerpt && (
                                            <p className="text-sm text-destructive">{form.errors.excerpt}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Content *</Label>
                                        <RichTextEditor
                                            content={form.data.content}
                                            onChange={(html) => form.setData('content', html)}
                                            placeholder="Start writing your article..."
                                        />
                                        {form.errors.content && (
                                            <p className="text-sm text-destructive">{form.errors.content}</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Sidebar */}
                        <div className="lg:sticky lg:top-6 space-y-6">
                            {/* Actions */}
                            <div className="flex flex-col gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="w-full"
                                >
                                    <Save className="size-4 mr-2" />
                                    Save Changes
                                </Button>

                                {article.status.value === 'draft' ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handlePublish}
                                        className="w-full"
                                    >
                                        <Globe className="size-4 mr-2" />
                                        Publish
                                    </Button>
                                ) : article.status.value === 'published' ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleUnpublish}
                                        className="w-full"
                                    >
                                        <Archive className="size-4 mr-2" />
                                        Unpublish
                                    </Button>
                                ) : null}
                            </div>

                            {/* Settings */}
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Category</Label>
                                    <div className="flex flex-wrap gap-2">
                                        {categories.map((category) => (
                                            <button
                                                key={category.value}
                                                type="button"
                                                onClick={() => form.setData('category', category.value)}
                                                className={cn(
                                                    'px-3 py-1.5 text-sm rounded-full border transition-colors',
                                                    form.data.category === category.value
                                                        ? 'bg-primary text-primary-foreground border-primary'
                                                        : 'bg-background hover:bg-muted border-input'
                                                )}
                                            >
                                                {category.label}
                                            </button>
                                        ))}
                                    </div>
                                    {form.errors.category && (
                                        <p className="text-sm text-destructive">{form.errors.category}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="author_name">Author Name</Label>
                                    <Input
                                        id="author_name"
                                        value={form.data.author_name}
                                        onChange={(e) => form.setData('author_name', e.target.value)}
                                        placeholder="Leave blank to use your name"
                                    />
                                </div>

                                <div className="flex items-center justify-between pt-2">
                                    <Label htmlFor="is_featured">Featured Article</Label>
                                    <Switch
                                        id="is_featured"
                                        checked={form.data.is_featured}
                                        onCheckedChange={(checked) => form.setData('is_featured', checked)}
                                    />
                                </div>
                            </div>

                            {/* Info */}
                            <div className="pt-4 border-t space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Slug</span>
                                    <span className="font-mono text-xs">{article.slug}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Views</span>
                                    <span>{article.view_count.toLocaleString()}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Read Time</span>
                                    <span>{article.read_time}</span>
                                </div>
                                {article.formatted_date && (
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Published</span>
                                        <span>{article.formatted_date}</span>
                                    </div>
                                )}
                            </div>

                            {/* Delete */}
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={handleDelete}
                                className="w-full text-destructive hover:text-destructive hover:bg-destructive/10"
                            >
                                <Trash2 className="size-4 mr-2" />
                                Delete Article
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </SupportLayout>
    );
}
