<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\CreateTournamentDTO;
use App\DTOs\TournamentFiltersDTO;
use App\Enums\GeographicLevel;
use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentCollection;
use App\Http\Resources\TournamentResource;
use App\Models\GeographicUnit;
use App\Models\Tournament;
use App\Models\TournamentLevelSetting;
use App\Services\TournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TournamentController extends Controller
{
    public function __construct(
        protected TournamentService $tournamentService
    ) {}

    /**
     * Get all tournaments for admin panel.
     */
    public function index(Request $request): TournamentCollection
    {
        Gate::authorize('viewAdmin', Tournament::class);

        $filters = new TournamentFiltersDTO(
            type: $request->filled('type') ? TournamentType::from($request->type) : null,
            status: $request->filled('status') ? TournamentStatus::from($request->status) : null,
            geographicScopeId: $request->geographic_scope_id,
            search: $request->search,
            sortBy: $request->get('sort_by', 'created_at'),
            sortDirection: $request->get('sort_direction', 'desc'),
            perPage: $request->get('per_page', 20),
            page: $request->get('page', 1),
        );

        // For admin, show all statuses including pending review and draft
        $query = Tournament::query()
            ->with(['geographicScope', 'createdBy.organizerProfile', 'createdBy.playerProfile']);

        if ($filters->type) {
            $query->where('type', $filters->type);
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->geographicScopeId) {
            $query->where('geographic_scope_id', $filters->geographicScopeId);
        }

        if ($filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ILIKE', "%{$filters->search}%")
                    ->orWhere('description', 'ILIKE', "%{$filters->search}%");
            });
        }

        $tournaments = $query
            ->orderBy($filters->sortBy, $filters->sortDirection)
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return new TournamentCollection($tournaments);
    }

    /**
     * Get a single tournament with full details.
     */
    public function show(Tournament $tournament): TournamentResource
    {
        Gate::authorize('viewAdmin', Tournament::class);

        $tournament->load([
            'geographicScope',
            'createdBy.organizerProfile',
            'createdBy.playerProfile',
            'participants.playerProfile',
        ]);

        return new TournamentResource($tournament);
    }

    /**
     * Create a new Special tournament (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('createSpecial', Tournament::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'geographic_scope_id' => ['required', 'exists:geographic_units,id'],
            'venue_name' => ['nullable', 'string', 'max:150'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'registration_opens_at' => ['nullable', 'date', 'after_or_equal:now'],
            'registration_closes_at' => ['required', 'date', 'after_or_equal:now'],
            'starts_at' => ['required', 'date', 'after_or_equal:registration_closes_at'],
            'winners_count' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'winners_per_level' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'race_to' => ['sometimes', 'integer', 'min:2', 'max:7'],
            'finals_race_to' => ['nullable', 'integer', 'min:2', 'max:9'],
            'confirmation_hours' => ['sometimes', 'integer', 'min:1', 'max:72'],
            'entry_fee' => ['sometimes', 'integer', 'min:0'],
            'entry_fee_currency' => ['sometimes', 'string', 'size:3'],
        ]);

        // Force type to SPECIAL for admin-created tournaments
        $validated['type'] = TournamentType::SPECIAL->value;

        $dto = CreateTournamentDTO::fromArray($validated);
        $tournament = $this->tournamentService->create($dto, $request->user());

        // Admin-created tournaments go straight to DRAFT (auto-approved)
        $tournament->status = TournamentStatus::DRAFT;
        $tournament->verified_at = now();
        $tournament->verified_by = $request->user()->id;
        $tournament->save();

        return response()->json([
            'message' => 'Special tournament created successfully.',
            'tournament' => new TournamentResource($tournament),
        ], 201);
    }

    /**
     * Approve a tournament (Admin only).
     */
    public function approve(Tournament $tournament): JsonResponse
    {
        Gate::authorize('approve', $tournament);

        $tournament->verify(request()->user());

        return response()->json([
            'message' => 'Tournament approved successfully.',
            'tournament' => new TournamentResource($tournament->fresh(['geographicScope', 'createdBy'])),
        ]);
    }

    /**
     * Reject a tournament (Admin only).
     */
    public function reject(Request $request, Tournament $tournament): JsonResponse
    {
        Gate::authorize('reject', $tournament);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $tournament->reject($request->user(), $validated['reason']);

        return response()->json([
            'message' => 'Tournament rejected.',
            'tournament' => new TournamentResource($tournament->fresh(['geographicScope', 'createdBy'])),
        ]);
    }

    /**
     * Cancel a tournament.
     */
    public function cancel(Request $request, Tournament $tournament): JsonResponse
    {
        Gate::authorize('cancel', $tournament);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->tournamentService->cancel($tournament, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Tournament cancelled successfully.',
            'tournament' => new TournamentResource($tournament->fresh(['geographicScope', 'createdBy'])),
        ]);
    }

    /**
     * Get tournament statistics for dashboard.
     */
    public function stats(): JsonResponse
    {
        Gate::authorize('viewAdmin', Tournament::class);

        return response()->json([
            'total' => Tournament::count(),
            'by_status' => [
                'pending_review' => Tournament::where('status', TournamentStatus::PENDING_REVIEW)->count(),
                'draft' => Tournament::where('status', TournamentStatus::DRAFT)->count(),
                'registration' => Tournament::where('status', TournamentStatus::REGISTRATION)->count(),
                'active' => Tournament::where('status', TournamentStatus::ACTIVE)->count(),
                'completed' => Tournament::where('status', TournamentStatus::COMPLETED)->count(),
                'cancelled' => Tournament::where('status', TournamentStatus::CANCELLED)->count(),
            ],
            'by_type' => [
                'regular' => Tournament::where('type', TournamentType::REGULAR)->count(),
                'special' => Tournament::where('type', TournamentType::SPECIAL)->count(),
            ],
        ]);
    }

    /**
     * Get tournament level settings (for creating Special tournaments).
     */
    public function levelSettings(): JsonResponse
    {
        Gate::authorize('viewAdmin', Tournament::class);

        return response()->json([
            'settings' => TournamentLevelSetting::getAllSettings(),
        ]);
    }

    /**
     * Update tournament level settings (Admin only).
     */
    public function updateLevelSettings(Request $request): JsonResponse
    {
        Gate::authorize('createSpecial', Tournament::class);

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.geographic_level' => ['required', 'integer', 'min:1', 'max:7'],
            'settings.*.race_to' => ['required', 'integer', 'min:2', 'max:7'],
            'settings.*.finals_race_to' => ['required', 'integer', 'min:2', 'max:9'],
            'settings.*.confirmation_hours' => ['sometimes', 'integer', 'min:1', 'max:72'],
        ]);

        foreach ($validated['settings'] as $setting) {
            TournamentLevelSetting::updateOrCreate(
                ['geographic_level' => $setting['geographic_level']],
                [
                    'race_to' => $setting['race_to'],
                    'finals_race_to' => $setting['finals_race_to'],
                    'confirmation_hours' => $setting['confirmation_hours'] ?? 24,
                ]
            );
        }

        return response()->json([
            'message' => 'Level settings updated successfully.',
            'settings' => TournamentLevelSetting::getAllSettings(),
        ]);
    }

    /**
     * Get geographic units for tournament creation dropdown.
     */
    public function geographicUnits(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Tournament::class);

        $level = $request->get('level');

        $query = GeographicUnit::query()->active()->orderBy('name');

        if ($level) {
            $query->where('level', $level);
        }

        $units = $query->get()->map(fn($unit) => [
            'id' => $unit->id,
            'name' => $unit->name,
            'level' => $unit->level,
            'level_label' => $unit->getLevelEnum()->label(),
            'full_path' => $unit->getFullPath(),
        ]);

        return response()->json(['units' => $units]);
    }
}
