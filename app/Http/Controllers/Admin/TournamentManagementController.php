<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\CreateTournamentDTO;
use App\Enums\GeographicLevel;
use App\Enums\TournamentStatus;
use App\Enums\TournamentType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GeographicUnit;
use App\Models\Tournament;
use App\Models\TournamentLevelSetting;
use App\Services\TournamentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TournamentManagementController extends Controller
{
    public function __construct(
        protected TournamentService $tournamentService
    ) {}

    /**
     * Display all tournaments list.
     */
    public function index(Request $request): Response
    {
        $query = Tournament::query()
            ->with(['geographicScope', 'createdBy.organizerProfile'])
            ->withCount('participants');

        // Search
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->type) {
            $query->where('type', $request->type);
        }

        $tournaments = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get stats for dashboard cards
        $stats = [
            'total' => Tournament::count(),
            'pending_review' => Tournament::where('status', TournamentStatus::PENDING_REVIEW)->count(),
            'active' => Tournament::where('status', TournamentStatus::ACTIVE)->count(),
            'by_type' => [
                'regular' => Tournament::where('type', TournamentType::REGULAR)->count(),
                'special' => Tournament::where('type', TournamentType::SPECIAL)->count(),
            ],
        ];

        return Inertia::render('Admin/Tournaments/Index', [
            'tournaments' => [
                'data' => $tournaments->map(fn($t) => $this->formatTournament($t)),
                'current_page' => $tournaments->currentPage(),
                'last_page' => $tournaments->lastPage(),
                'per_page' => $tournaments->perPage(),
                'total' => $tournaments->total(),
            ],
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'type']),
            'statuses' => collect(TournamentStatus::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'types' => collect(TournamentType::cases())->map(fn($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'canCreateSpecial' => $request->user()->is_super_admin,
        ]);
    }

    /**
     * Show tournament details.
     */
    public function show(Request $request, Tournament $tournament): Response
    {
        $tournament->load([
            'geographicScope',
            'createdBy.organizerProfile',
            'participants.playerProfile',
        ]);

        // Get recent matches
        $recentMatches = $tournament->matches()
            ->with(['player1.playerProfile', 'player2.playerProfile'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'player1_name' => $m->player1?->playerProfile?->nickname
                    ?? ($m->player1?->playerProfile ? "{$m->player1->playerProfile->first_name} {$m->player1->playerProfile->last_name}" : 'TBD'),
                'player2_name' => $m->player2?->playerProfile?->nickname
                    ?? ($m->player2?->playerProfile ? "{$m->player2->playerProfile->first_name} {$m->player2->playerProfile->last_name}" : 'TBD'),
                'player1_score' => $m->player1_score,
                'player2_score' => $m->player2_score,
                'status' => $m->status instanceof \BackedEnum ? $m->status->value : $m->status,
                'round_name' => $m->round_name,
                'played_at' => $m->played_at?->toISOString(),
            ]);

        // Get activity log
        $activityLog = ActivityLog::forEntity(ActivityLog::ENTITY_TOURNAMENT, $tournament->id)
            ->with('user')
            ->recent(20)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'action_label' => $a->getActionLabel(),
                'description' => $a->description,
                'performed_by' => $a->user ? [
                    'id' => $a->user->id,
                    'email' => $a->user->email,
                ] : null,
                'created_at' => $a->created_at->toISOString(),
            ]);

        $user = $request->user();

        return Inertia::render('Admin/Tournaments/Show', [
            'tournament' => $this->formatTournament($tournament, true),
            'participants' => $tournament->participants->map(fn($p) => [
                'id' => $p->id,
                'seed' => $p->seed,
                'status' => $p->status instanceof \BackedEnum ? $p->status->value : $p->status,
                'player' => $p->playerProfile ? [
                    'id' => $p->playerProfile->id,
                    'name' => $p->playerProfile->nickname ?? "{$p->playerProfile->first_name} {$p->playerProfile->last_name}",
                    'photo_url' => $p->playerProfile->photo_url,
                    'rating' => $p->playerProfile->rating,
                ] : null,
                'registered_at' => $p->created_at->toISOString(),
            ]),
            'recentMatches' => $recentMatches,
            'activityLog' => $activityLog,
            'permissions' => [
                'canApprove' => Gate::allows('approve', $tournament),
                'canReject' => Gate::allows('reject', $tournament),
                'canCancel' => Gate::allows('cancel', $tournament),
                'canStart' => Gate::allows('start', $tournament),
            ],
        ]);
    }

    /**
     * Show form to create a Special tournament.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('createSpecial', Tournament::class);

        // Get geographic units grouped by level
        $geographicUnits = GeographicUnit::query()
            ->active()
            ->orderBy('level')
            ->orderBy('name')
            ->get()
            ->map(fn($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'level' => $unit->level,
                'level_label' => $unit->getLevelEnum()->label(),
                'full_path' => $unit->getFullPath(),
            ]);

        // Get level settings for defaults
        $levelSettings = TournamentLevelSetting::getAllSettings();

        return Inertia::render('Admin/Tournaments/Create', [
            'geographicUnits' => $geographicUnits,
            'levelSettings' => $levelSettings,
            'levels' => collect(GeographicLevel::cases())->map(fn($l) => [
                'value' => $l->value,
                'label' => $l->label(),
            ]),
        ]);
    }

    /**
     * Store a new Special tournament.
     */
    public function store(Request $request): RedirectResponse
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

        ActivityLog::log(
            'tournament.created',
            ActivityLog::ENTITY_TOURNAMENT,
            $tournament->id,
            "Created Special tournament: {$tournament->name}",
            null,
            $request
        );

        return redirect()
            ->route('admin.tournaments.show', $tournament)
            ->with('success', 'Special tournament created successfully.');
    }

    /**
     * Approve a pending tournament.
     */
    public function approve(Request $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('approve', $tournament);

        $tournament->verify($request->user());

        ActivityLog::log(
            'tournament.approved',
            ActivityLog::ENTITY_TOURNAMENT,
            $tournament->id,
            "Approved tournament: {$tournament->name}",
            null,
            $request
        );

        return back()->with('success', 'Tournament approved successfully.');
    }

    /**
     * Reject a pending tournament.
     */
    public function reject(Request $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('reject', $tournament);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $tournament->reject($request->user(), $validated['reason']);

        ActivityLog::log(
            'tournament.rejected',
            ActivityLog::ENTITY_TOURNAMENT,
            $tournament->id,
            "Rejected tournament: {$tournament->name}",
            ['reason' => $validated['reason']],
            $request
        );

        return back()->with('success', 'Tournament rejected.');
    }

    /**
     * Cancel a tournament.
     */
    public function cancel(Request $request, Tournament $tournament): RedirectResponse
    {
        Gate::authorize('cancel', $tournament);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->tournamentService->cancel($tournament, $validated['reason'] ?? null);

        ActivityLog::log(
            'tournament.cancelled',
            ActivityLog::ENTITY_TOURNAMENT,
            $tournament->id,
            "Cancelled tournament: {$tournament->name}",
            ['reason' => $validated['reason'] ?? null],
            $request
        );

        return back()->with('success', 'Tournament cancelled successfully.');
    }

    /**
     * Show level settings management page.
     */
    public function levelSettings(Request $request): Response
    {
        Gate::authorize('createSpecial', Tournament::class);

        $settings = TournamentLevelSetting::getAllSettings();

        return Inertia::render('Admin/Tournaments/LevelSettings', [
            'settings' => $settings,
            'levels' => collect(GeographicLevel::cases())->map(fn($l) => [
                'value' => $l->value,
                'label' => $l->label(),
            ]),
        ]);
    }

    /**
     * Update level settings.
     */
    public function updateLevelSettings(Request $request): RedirectResponse
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

        ActivityLog::log(
            'settings.updated',
            'system',
            null,
            'Updated tournament level settings',
            $validated['settings'],
            $request
        );

        return back()->with('success', 'Level settings updated successfully.');
    }

    /**
     * Format tournament for response.
     */
    private function formatTournament(Tournament $tournament, bool $detailed = false): array
    {
        $data = [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'status' => $tournament->status instanceof \BackedEnum ? $tournament->status->value : $tournament->status,
            'status_label' => $tournament->status instanceof TournamentStatus ? $tournament->status->label() : $tournament->status,
            'type' => $tournament->type instanceof \BackedEnum ? $tournament->type->value : $tournament->type,
            'type_label' => $tournament->type instanceof TournamentType ? $tournament->type->label() : $tournament->type,
            'format' => $tournament->format instanceof \BackedEnum ? $tournament->format->value : $tournament->format,
            'participants_count' => $tournament->participants_count ?? $tournament->participants()->count(),
            'max_participants' => $tournament->max_participants,
            'starts_at' => $tournament->starts_at?->toISOString(),
            'created_at' => $tournament->created_at->toISOString(),
            'is_verified' => $tournament->isVerified(),
            'verified_at' => $tournament->verified_at?->toISOString(),
            'rejection_reason' => $tournament->rejection_reason,
            'geographic_scope' => $tournament->geographicScope ? [
                'id' => $tournament->geographicScope->id,
                'name' => $tournament->geographicScope->name,
                'level' => $tournament->geographicScope->level,
                'level_label' => $tournament->geographicScope->getLevelEnum()->label(),
            ] : null,
            'organizer' => $tournament->createdBy?->organizerProfile ? [
                'id' => $tournament->createdBy->organizerProfile->id,
                'organization_name' => $tournament->createdBy->organizerProfile->organization_name,
                'logo_url' => $tournament->createdBy->organizerProfile->logo_url,
            ] : null,
        ];

        if ($detailed) {
            $data['description'] = $tournament->description;
            $data['race_to'] = $tournament->race_to;
            $data['finals_race_to'] = $tournament->finals_race_to;
            $data['confirmation_hours'] = $tournament->confirmation_hours;
            $data['entry_fee'] = $tournament->entry_fee;
            $data['entry_fee_currency'] = $tournament->entry_fee_currency;
            $data['registration_opens_at'] = $tournament->registration_opens_at?->toISOString();
            $data['registration_closes_at'] = $tournament->registration_closes_at?->toISOString();
            $data['venue_name'] = $tournament->venue_name;
            $data['venue_address'] = $tournament->venue_address;
            $data['can_start'] = $this->tournamentService->canStart($tournament);
        }

        return $data;
    }
}
