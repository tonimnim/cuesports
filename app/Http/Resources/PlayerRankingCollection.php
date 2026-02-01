<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PlayerRankingCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     */
    public $collects = PlayerRankingResource::class;

    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        // Calculate rank for each player based on pagination offset
        $perPage = $this->perPage();
        $currentPage = $this->currentPage();
        $offset = ($currentPage - 1) * $perPage;

        return [
            'data' => $this->collection->map(function ($resource, $index) use ($offset) {
                $resource->setRank($offset + $index + 1);
                return $resource;
            }),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'current_page' => $this->currentPage(),
                'from' => $this->firstItem(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'to' => $this->lastItem(),
                'total' => $this->total(),
            ],
        ];
    }

    /**
     * Customize the pagination information.
     */
    public function paginationInformation($request, $paginated, $default): array
    {
        return [];
    }
}
