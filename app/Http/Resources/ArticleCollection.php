<?php

namespace App\Http\Resources;

use App\Enums\ArticleCategory;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ArticleCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = ArticleResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'categories' => $this->getCategories(),
        ];
    }

    /**
     * Get category counts for published articles.
     */
    protected function getCategories(): array
    {
        $counts = Article::published()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $total = array_sum($counts);

        $categories = [
            [
                'name' => 'All',
                'slug' => 'all',
                'count' => $total,
            ],
        ];

        foreach (ArticleCategory::cases() as $category) {
            $categories[] = [
                'name' => $category->label(),
                'slug' => $category->value,
                'count' => $counts[$category->value] ?? 0,
            ];
        }

        return $categories;
    }
}
