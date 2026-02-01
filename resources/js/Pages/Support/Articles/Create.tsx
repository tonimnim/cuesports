import { Head, Link, useForm } from '@inertiajs/react';
import { SupportLayout } from '@/layouts/support';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { ArrowLeft, Save, Send } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Category {
    value: string;
    label: string;
}

interface Props {
    categories: Category[];
}

export default function ArticleCreate({ categories }: Props) {
    const form = useForm({
        title: '',
        excerpt: '',
        content: '',
        category: 'announcements',
        author_name: '',
        is_featured: false,
        publish_now: false,
    });

    const handleSubmit = (e: React.FormEvent, publishNow: boolean = false) => {
        e.preventDefault();
        form.setData('publish_now', publishNow);
        form.post('/support/articles');
    };

    return (
        <SupportLayout>
            <Head title="Create Article" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/support/articles">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight">Create Article</h1>
                </div>

                <form onSubmit={(e) => handleSubmit(e, false)}>
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
                                    variant="outline"
                                    disabled={form.processing}
                                    className="w-full"
                                >
                                    <Save className="size-4 mr-2" />
                                    Save as Draft
                                </Button>
                                <Button
                                    type="button"
                                    disabled={form.processing}
                                    className="w-full"
                                    onClick={(e) => handleSubmit(e, true)}
                                >
                                    <Send className="size-4 mr-2" />
                                    Publish Now
                                </Button>
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
                        </div>
                    </div>
                </form>
            </div>
        </SupportLayout>
    );
}
