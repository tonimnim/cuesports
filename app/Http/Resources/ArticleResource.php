<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Indicates if the resource should include content.
     */
    protected bool $includeContent = false;

    /**
     * Include full content in the response.
     */
    public function withContent(): self
    {
        $this->includeContent = true;
        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'featured_image_url' => $this->featured_image_url,
            'category' => [
                'value' => $this->category->value,
                'label' => $this->category->label(),
                'color' => $this->category->color(),
            ],
            'author' => $this->author_display_name,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'is_featured' => $this->is_featured,
            'view_count' => $this->view_count,
            'read_time' => $this->read_time,
            'read_time_minutes' => $this->read_time_minutes,
            'published_at' => $this->published_at?->toISOString(),
            'formatted_date' => $this->formatted_date,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];

        // Include content only when explicitly requested (single article view)
        if ($this->includeContent || $request->routeIs('api.articles.show')) {
            $data['content'] = $this->content;
            $data['meta'] = [
                'title' => $this->meta_title ?? $this->title,
                'description' => $this->meta_description ?? $this->excerpt,
            ];
        }

        return $data;
    }
}
