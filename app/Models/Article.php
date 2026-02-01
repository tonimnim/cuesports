<?php

namespace App\Models;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image_url',
        'category',
        'author_id',
        'author_name',
        'status',
        'is_featured',
        'view_count',
        'read_time_minutes',
        'published_at',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'category' => ArticleCategory::class,
        'status' => ArticleStatus::class,
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'read_time_minutes' => 'integer',
        'published_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Article $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }

            // Ensure unique slug
            $originalSlug = $article->slug;
            $count = 1;
            while (static::where('slug', $article->slug)->exists()) {
                $article->slug = "{$originalSlug}-{$count}";
                $count++;
            }

            // Calculate read time based on content
            if (empty($article->read_time_minutes) && !empty($article->content)) {
                $article->read_time_minutes = self::calculateReadTime($article->content);
            }
        });

        static::updating(function (Article $article) {
            // Recalculate read time if content changed
            if ($article->isDirty('content') && !empty($article->content)) {
                $article->read_time_minutes = self::calculateReadTime($article->content);
            }
        });
    }

    /**
     * Calculate read time in minutes based on word count.
     */
    public static function calculateReadTime(string $content): int
    {
        $wordCount = str_word_count(strip_tags($content));
        $readTime = ceil($wordCount / 200); // Average reading speed: 200 wpm

        return max(1, (int) $readTime);
    }

    /**
     * Get the author of the article.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the display name for the author.
     */
    public function getAuthorDisplayNameAttribute(): string
    {
        if (!empty($this->author_name)) {
            return $this->author_name;
        }

        if ($this->author) {
            return $this->author->name ?? $this->author->email;
        }

        return 'CueSports Team';
    }

    /**
     * Scope to only published articles.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope to only featured articles.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeCategory(Builder $query, string|ArticleCategory $category): Builder
    {
        if ($category instanceof ArticleCategory) {
            $category = $category->value;
        }

        return $query->where('category', $category);
    }

    /**
     * Scope to get trending articles by view count.
     */
    public function scopeTrending(Builder $query, int $limit = 5): Builder
    {
        return $query->published()
            ->orderByDesc('view_count')
            ->limit($limit);
    }

    /**
     * Increment view count.
     */
    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    /**
     * Publish the article.
     */
    public function publish(): bool
    {
        return $this->update([
            'status' => ArticleStatus::PUBLISHED,
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    /**
     * Unpublish the article.
     */
    public function unpublish(): bool
    {
        return $this->update([
            'status' => ArticleStatus::DRAFT,
        ]);
    }

    /**
     * Archive the article.
     */
    public function archive(): bool
    {
        return $this->update([
            'status' => ArticleStatus::ARCHIVED,
        ]);
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(): bool
    {
        return $this->update([
            'is_featured' => !$this->is_featured,
        ]);
    }

    /**
     * Check if article is published.
     */
    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::PUBLISHED
            && $this->published_at
            && $this->published_at->lte(now());
    }

    /**
     * Get formatted read time.
     */
    public function getReadTimeAttribute(): string
    {
        return "{$this->read_time_minutes} min read";
    }

    /**
     * Get formatted published date.
     */
    public function getFormattedDateAttribute(): ?string
    {
        return $this->published_at?->format('M j, Y');
    }
}
