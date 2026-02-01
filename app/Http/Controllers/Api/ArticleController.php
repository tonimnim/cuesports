<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleCollection;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * List published articles with optional filters.
     */
    public function index(Request $request)
    {
        $query = Article::published()
            ->with('author')
            ->orderByDesc('published_at');

        // Filter by category
        if ($request->filled('category') && $request->category !== 'all') {
            $query->category($request->category);
        }

        // Filter featured only
        if ($request->boolean('featured')) {
            $query->featured();
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $perPage = min($request->integer('per_page', 12), 50);
        $articles = $query->paginate($perPage);

        return new ArticleCollection($articles);
    }

    /**
     * Get a single article by slug.
     */
    public function show(string $slug)
    {
        $article = Article::published()
            ->with('author')
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment view count
        $article->incrementViews();

        return (new ArticleResource($article))->withContent();
    }

    /**
     * Get trending articles.
     */
    public function trending(Request $request)
    {
        $limit = min($request->integer('limit', 5), 10);

        $articles = Article::published()
            ->with('author')
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get();

        return ArticleResource::collection($articles);
    }

    /**
     * Get featured article (most recent featured).
     */
    public function featured()
    {
        $article = Article::published()
            ->featured()
            ->with('author')
            ->orderByDesc('published_at')
            ->first();

        if (!$article) {
            return response()->json(['data' => null]);
        }

        return new ArticleResource($article);
    }

    /**
     * Get related articles (same category, excluding current).
     */
    public function related(string $slug, Request $request)
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        $limit = min($request->integer('limit', 4), 10);

        $related = Article::published()
            ->with('author')
            ->where('id', '!=', $article->id)
            ->where('category', $article->category)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        return ArticleResource::collection($related);
    }
}
