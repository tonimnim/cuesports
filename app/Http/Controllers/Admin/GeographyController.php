<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeographicUnit;
use App\Enums\GeographicLevel;
use App\Constants\AfricanCountries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class GeographyController extends Controller
{
    // Cache TTL in seconds (10 minutes for geographic data - rarely changes)
    private const CACHE_TTL = 600;
    private const CACHE_TTL_STATS = 3600; // 1 hour for stats

    public function index()
    {
        // Get the root node (Africa) - create if doesn't exist
        $root = Cache::remember('geography:root', self::CACHE_TTL, function () {
            $root = GeographicUnit::where('level', GeographicLevel::ROOT->value)->first();

            if (!$root) {
                $root = GeographicUnit::create([
                    'name' => 'Africa',
                    'code' => 'AF',
                    'level' => GeographicLevel::ROOT->value,
                    'parent_id' => null,
                ]);
            }

            return $root;
        });

        // Get countries already added to the platform
        $countries = Cache::remember('geography:countries', self::CACHE_TTL, function () {
            return GeographicUnit::where('level', GeographicLevel::NATIONAL->value)
                ->withCount('children')
                ->orderBy('name')
                ->get();
        });

        // Get list of country codes already in the system
        $existingCountryCodes = $countries->pluck('code')->map(fn($c) => strtoupper($c))->toArray();

        // Get available African countries (not yet added) - derived from countries cache
        $availableCountries = collect(AfricanCountries::all())
            ->filter(fn($country) => !in_array($country['code'], $existingCountryCodes))
            ->values()
            ->toArray();

        // Default levels (static, can cache forever)
        $levels = Cache::rememberForever('geography:levels', function () {
            return collect(GeographicLevel::cases())->map(fn($level) => [
                'value' => $level->value,
                'name' => $level->name,
                'label' => $level->label(),
            ])->toArray();
        });

        // Country-specific labels for all added countries
        $countryLabels = Cache::remember('geography:country_labels', self::CACHE_TTL, function () use ($countries) {
            $labels = [];
            foreach ($countries as $country) {
                $labels[$country->code] = GeographicLevel::labelsForCountry($country->code);
            }
            return $labels;
        });

        $stats = Cache::remember('geography:stats', self::CACHE_TTL_STATS, function () {
            return [
                'countries' => GeographicUnit::where('level', GeographicLevel::NATIONAL->value)->count(),
                'regions' => GeographicUnit::where('level', GeographicLevel::MACRO->value)->count(),
                'counties' => GeographicUnit::where('level', GeographicLevel::MESO->value)->count(),
                'communities' => GeographicUnit::where('level', GeographicLevel::ATOMIC->value)->count(),
            ];
        });

        return Inertia::render('Admin/Geography/Index', [
            'root' => $root,
            'countries' => $countries,
            'levels' => $levels,
            'countryLabels' => $countryLabels,
            'stats' => $stats,
            'availableCountries' => $availableCountries,
        ]);
    }

    public function getChildren(GeographicUnit $unit)
    {
        $cacheKey = "geography:children:{$unit->id}";

        $children = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($unit) {
            return $unit->children()
                ->withCount('children')
                ->orderBy('name')
                ->get()
                ->map(fn($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'code' => $child->code,
                    'level' => $child->level,
                    'level_label' => GeographicLevel::from($child->level)->label(),
                    'parent_id' => $child->parent_id,
                    'children_count' => $child->children_count,
                ]);
        });

        return response()->json([
            'children' => $children,
            'parent' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'level' => $unit->level,
                'level_label' => GeographicLevel::from($unit->level)->label(),
            ],
        ]);
    }

    public function getAncestors(GeographicUnit $unit)
    {
        $ancestors = [];
        $current = $unit;

        while ($current) {
            array_unshift($ancestors, [
                'id' => $current->id,
                'name' => $current->name,
                'level' => $current->level,
                'level_label' => GeographicLevel::from($current->level)->label(),
            ]);
            $current = $current->parent;
        }

        return response()->json(['ancestors' => $ancestors]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'parent_id' => 'required|exists:geographic_units,id',
        ]);

        $parent = GeographicUnit::findOrFail($request->parent_id);

        // Determine the level based on parent
        $newLevel = $parent->level + 1;

        if ($newLevel > GeographicLevel::ATOMIC->value) {
            return back()->with('error', 'Cannot add children below the community level.');
        }

        // Get country_code from parent chain
        $countryCode = $parent->country_code;
        if (!$countryCode) {
            // Find the country ancestor
            $country = $parent->getCountry();
            $countryCode = $country?->code;
        }

        // Get the local term for this level based on country
        $levelEnum = GeographicLevel::from($newLevel);
        $localTerm = $levelEnum->labelForCountry($countryCode);

        $unit = GeographicUnit::create([
            'name' => $request->name,
            'code' => $request->code ?? strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $request->name), 0, 10)),
            'level' => $newLevel,
            'parent_id' => $parent->id,
            'country_code' => $countryCode,
            'local_term' => $localTerm,
        ]);

        // Clear relevant caches
        $this->clearGeographyCache($parent->id);

        return back()->with('success', "{$request->name} has been added successfully.");
    }

    public function addCountry(Request $request)
    {
        $request->validate([
            'country_code' => 'required|string|size:2',
        ]);

        $countryCode = strtoupper($request->country_code);

        // Verify the country exists in our predefined list
        $countryData = AfricanCountries::getByCode($countryCode);
        if (!$countryData) {
            return back()->with('error', 'Invalid country code.');
        }

        // Check if country already exists
        $exists = GeographicUnit::where('level', GeographicLevel::NATIONAL->value)
            ->where('code', $countryCode)
            ->exists();

        if ($exists) {
            return back()->with('error', "{$countryData['name']} is already added to the platform.");
        }

        // Get or create the root (Africa)
        $root = GeographicUnit::where('level', GeographicLevel::ROOT->value)->first();

        if (!$root) {
            $root = GeographicUnit::create([
                'name' => 'Africa',
                'code' => 'AF',
                'level' => GeographicLevel::ROOT->value,
                'parent_id' => null,
            ]);
            Cache::forget('geography:root');
        }

        // Create the country
        GeographicUnit::create([
            'name' => $countryData['name'],
            'code' => $countryData['code'],
            'level' => GeographicLevel::NATIONAL->value,
            'parent_id' => $root->id,
            'country_code' => $countryData['code'],
        ]);

        // Clear country-related caches
        $this->clearCountryCache();

        return back()->with('success', "{$countryData['name']} has been added to the platform!");
    }

    public function update(Request $request, GeographicUnit $unit)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
        ]);

        // Prevent editing ROOT level
        if ($unit->level === GeographicLevel::ROOT->value) {
            return back()->with('error', 'Cannot edit the root continent level.');
        }

        $unit->update([
            'name' => $request->name,
            'code' => $request->code ?? $unit->code,
        ]);

        // Clear relevant caches
        $this->clearGeographyCache($unit->parent_id);

        return back()->with('success', "{$unit->name} has been updated.");
    }

    public function destroy(GeographicUnit $unit)
    {
        // Prevent deleting ROOT or NATIONAL levels
        if ($unit->level <= GeographicLevel::NATIONAL->value) {
            return back()->with('error', 'Cannot delete countries or the continent.');
        }

        // Check if unit has children
        if ($unit->children()->count() > 0) {
            return back()->with('error', 'Cannot delete a location that has sub-locations. Delete children first.');
        }

        // Check if unit has players
        if ($unit->players()->count() > 0) {
            return back()->with('error', 'Cannot delete a location that has registered players.');
        }

        $name = $unit->name;
        $parentId = $unit->parent_id;
        $unit->delete();

        // Clear relevant caches
        $this->clearGeographyCache($parentId);

        return back()->with('success', "{$name} has been deleted.");
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        // Cache search results for 5 minutes
        $cacheKey = 'geography:search:' . md5($query);

        $results = Cache::remember($cacheKey, 300, function () use ($query) {
            return GeographicUnit::where('name', 'like', "%{$query}%")
                ->where('level', '>', GeographicLevel::ROOT->value)
                ->with('parent')
                ->limit(20)
                ->get()
                ->map(function ($unit) {
                    $path = [];
                    $current = $unit->parent;
                    while ($current) {
                        array_unshift($path, $current->name);
                        $current = $current->parent;
                    }

                    return [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'level' => $unit->level,
                        'level_label' => GeographicLevel::from($unit->level)->label(),
                        'path' => implode(' > ', $path),
                    ];
                });
        });

        return response()->json(['results' => $results]);
    }

    // ==========================================
    // CACHE HELPERS
    // ==========================================

    private function clearGeographyCache(?int $parentId = null): void
    {
        Cache::forget('geography:stats');

        if ($parentId) {
            Cache::forget("geography:children:{$parentId}");
        }

        // Clear search cache pattern (will naturally expire)
    }

    private function clearCountryCache(): void
    {
        Cache::forget('geography:countries');
        Cache::forget('geography:country_labels');
        Cache::forget('geography:stats');
    }

    public static function clearAllGeographyCache(): void
    {
        Cache::forget('geography:root');
        Cache::forget('geography:countries');
        Cache::forget('geography:country_labels');
        Cache::forget('geography:stats');
        Cache::forget('geography:levels');
    }
}
