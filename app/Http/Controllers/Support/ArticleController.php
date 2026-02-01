<?php

namespace App\Http\Controllers\Support;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    /**
     * Display a listing of articles.
     */
    public function index(Request $request): Response
    {
        $query = Article::with('author')
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $articles = $query->paginate(15)->withQueryString();

        // Get stats
        $stats = [
            'total' => Article::count(),
            'published' => Article::where('status', ArticleStatus::PUBLISHED)->count(),
            'draft' => Article::where('status', ArticleStatus::DRAFT)->count(),
            'featured' => Article::where('is_featured', true)->count(),
        ];

        return Inertia::render('Support/Articles/Index', [
            'articles' => $articles->through(fn ($article) => $this->formatArticle($article)),
            'stats' => $stats,
            'filters' => $request->only(['status', 'category', 'search']),
            'categories' => $this->getCategories(),
            'statuses' => $this->getStatuses(),
        ]);
    }

    /**
     * Show the form for creating a new article.
     */
    public function create(): Response
    {
        return Inertia::render('Support/Articles/Create', [
            'categories' => $this->getCategories(),
        ]);
    }

    /**
     * Store a newly created article.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'required|string|max:500',
            'content' => 'required|string',
            'category' => ['required', Rule::enum(ArticleCategory::class)],
            'featured_image_url' => 'nullable|url|max:500',
            'author_name' => 'nullable|string|max:100',
            'is_featured' => 'boolean',
            'meta_title' => 'nullable|string|max:100',
            'meta_description' => 'nullable|string|max:200',
            'publish_now' => 'boolean',
        ]);

        $article = Article::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'excerpt' => $validated['excerpt'],
            'content' => $validated['content'],
            'category' => $validated['category'],
            'featured_image_url' => $validated['featured_image_url'] ?? null,
            'author_id' => $request->user()->id,
            'author_name' => $validated['author_name'] ?? null,
            'is_featured' => $validated['is_featured'] ?? false,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'status' => ($validated['publish_now'] ?? false) ? ArticleStatus::PUBLISHED : ArticleStatus::DRAFT,
            'published_at' => ($validated['publish_now'] ?? false) ? now() : null,
        ]);

        return redirect()
            ->route('support.articles.index')
            ->with('success', 'Article created successfully');
    }

    /**
     * Show the form for editing an article.
     */
    public function edit(Article $article): Response
    {
        return Inertia::render('Support/Articles/Edit', [
            'article' => $this->formatArticle($article, true),
            'categories' => $this->getCategories(),
        ]);
    }

    /**
     * Update the specified article.
     */
    public function update(Request $request, Article $article): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'required|string|max:500',
            'content' => 'required|string',
            'category' => ['required', Rule::enum(ArticleCategory::class)],
            'featured_image_url' => 'nullable|url|max:500',
            'author_name' => 'nullable|string|max:100',
            'is_featured' => 'boolean',
            'meta_title' => 'nullable|string|max:100',
            'meta_description' => 'nullable|string|max:200',
        ]);

        $article->update([
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'],
            'content' => $validated['content'],
            'category' => $validated['category'],
            'featured_image_url' => $validated['featured_image_url'] ?? null,
            'author_name' => $validated['author_name'] ?? null,
            'is_featured' => $validated['is_featured'] ?? false,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
        ]);

        return redirect()
            ->route('support.articles.index')
            ->with('success', 'Article updated successfully');
    }

    /**
     * Publish an article.
     */
    public function publish(Article $article): RedirectResponse
    {
        $article->publish();

        return redirect()
            ->back()
            ->with('success', 'Article published successfully');
    }

    /**
     * Unpublish an article.
     */
    public function unpublish(Article $article): RedirectResponse
    {
        $article->unpublish();

        return redirect()
            ->back()
            ->with('success', 'Article unpublished');
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(Article $article): RedirectResponse
    {
        $article->toggleFeatured();

        return redirect()
            ->back()
            ->with('success', $article->is_featured ? 'Article featured' : 'Article unfeatured');
    }

    /**
     * Delete an article.
     */
    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return redirect()
            ->route('support.articles.index')
            ->with('success', 'Article deleted');
    }

    /**
     * Format article for frontend.
     */
    private function formatArticle(Article $article, bool $includeContent = false): array
    {
        $data = [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'featured_image_url' => $article->featured_image_url,
            'category' => [
                'value' => $article->category->value,
                'label' => $article->category->label(),
                'color' => $article->category->color(),
            ],
            'author' => $article->author_display_name,
            'author_name' => $article->author_name,
            'status' => [
                'value' => $article->status->value,
                'label' => $article->status->label(),
            ],
            'is_featured' => $article->is_featured,
            'view_count' => $article->view_count,
            'read_time' => $article->read_time,
            'read_time_minutes' => $article->read_time_minutes,
            'published_at' => $article->published_at?->toISOString(),
            'formatted_date' => $article->formatted_date,
            'created_at' => $article->created_at->toISOString(),
            'updated_at' => $article->updated_at->toISOString(),
        ];

        if ($includeContent) {
            $data['content'] = $article->content;
            $data['meta_title'] = $article->meta_title;
            $data['meta_description'] = $article->meta_description;
        }

        return $data;
    }

    /**
     * Get categories for dropdown.
     */
    private function getCategories(): array
    {
        return collect(ArticleCategory::cases())->map(fn ($cat) => [
            'value' => $cat->value,
            'label' => $cat->label(),
        ])->toArray();
    }

    /**
     * Get statuses for dropdown.
     */
    private function getStatuses(): array
    {
        return collect(ArticleStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->toArray();
    }
}
