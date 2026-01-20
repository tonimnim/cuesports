<?php

namespace App\Http\Controllers\Api;

use App\Enums\GeographicLevel;
use App\Http\Controllers\Controller;
use App\Models\GeographicUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeographicUnitController extends Controller
{
    /**
     * Get all countries (for country selection dropdown).
     */
    public function countries(): JsonResponse
    {
        $countries = GeographicUnit::active()
            ->where('level', GeographicLevel::NATIONAL->value)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'country_code']);

        return response()->json([
            'countries' => $countries,
        ]);
    }

    /**
     * Get children of a geographic unit (for cascading dropdowns).
     *
     * Example flow:
     * 1. Select Country (Kenya) -> returns Counties
     * 2. Select County (Kirinyaga) -> returns Sub-Counties/Constituencies
     * 3. Select Constituency (Mwea) -> returns Wards
     * 4. Select Ward (Mutithi) -> returns Communities (ATOMIC level)
     */
    public function children(GeographicUnit $geographicUnit): JsonResponse
    {
        $children = $geographicUnit->children()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'level', 'local_term']);

        return response()->json([
            'parent' => [
                'id' => $geographicUnit->id,
                'name' => $geographicUnit->name,
                'level' => $geographicUnit->level,
                'local_term' => $geographicUnit->local_term,
            ],
            'children' => $children,
            'children_level' => $children->first()?->level,
            'children_local_term' => $children->first()?->local_term,
        ]);
    }

    /**
     * Search for communities (ATOMIC level) by name.
     * Used for autocomplete when user types their community name.
     *
     * GET /api/locations/search?q=kutus&country_id=1
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'country_id' => ['nullable', 'exists:geographic_units,id'],
            'level' => ['nullable', 'integer', 'min:1', 'max:7'],
        ]);

        $query = GeographicUnit::active()
            ->where('name', 'ILIKE', '%' . $request->q . '%');

        // Default to ATOMIC level (communities) for registration
        $level = $request->input('level', GeographicLevel::ATOMIC->value);
        $query->where('level', $level);

        // Filter by country if provided
        if ($request->country_id) {
            $country = GeographicUnit::find($request->country_id);
            if ($country) {
                $query->where('country_code', $country->country_code);
            }
        }

        $results = $query->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'code' => $unit->code,
                    'full_path' => $unit->getFullPath(),
                    'level' => $unit->level,
                    'local_term' => $unit->local_term,
                ];
            });

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * Get the full hierarchy path for a location.
     * Useful for showing "Kutus, Mwea, Kirinyaga, Kenya"
     */
    public function show(GeographicUnit $geographicUnit): JsonResponse
    {
        $ancestors = collect($geographicUnit->getAncestors())->map(fn($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'level' => $a->level,
            'local_term' => $a->local_term,
        ]);

        return response()->json([
            'location' => [
                'id' => $geographicUnit->id,
                'name' => $geographicUnit->name,
                'code' => $geographicUnit->code,
                'level' => $geographicUnit->level,
                'local_term' => $geographicUnit->local_term,
                'full_path' => $geographicUnit->getFullPath(),
                'is_atomic' => $geographicUnit->isAtomic(),
            ],
            'ancestors' => $ancestors,
        ]);
    }

    /**
     * Get location hierarchy for cascading selection.
     * Returns the full tree from country down to a specific level.
     *
     * GET /api/locations/hierarchy/1?to_level=7
     */
    public function hierarchy(GeographicUnit $geographicUnit, Request $request): JsonResponse
    {
        $toLevel = $request->input('to_level', GeographicLevel::ATOMIC->value);

        $buildTree = function ($unit, $currentLevel, $targetLevel) use (&$buildTree) {
            $data = [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'level' => $unit->level,
                'local_term' => $unit->local_term,
            ];

            if ($currentLevel < $targetLevel) {
                $children = $unit->children()->active()->orderBy('name')->get();
                $data['children'] = $children->map(fn($child) =>
                    $buildTree($child, $currentLevel + 1, $targetLevel)
                )->values();
            }

            return $data;
        };

        return response()->json([
            'hierarchy' => $buildTree($geographicUnit, $geographicUnit->level, $toLevel),
        ]);
    }
}
