<?php

namespace App\Http\Controllers\Support;

use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateTournamentBracketJob;
use App\Models\ActivityLog;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TournamentManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $tournaments = Tournament::query()
            ->with(['organizer.user', 'geographicScope'])
            ->withCount('participants')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('organizer', fn($q) =>
                            $q->where('organization_name', 'like', "%{$search}%")
                        );
                });
            })
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Support/Tournaments/Index', [
            'tournaments' => [
                'data' => $tournaments->map(fn($t) => $this->formatTournament($t)),
                'current_page' => $tournaments->currentPage(),
                'last_page' => $tournaments->lastPage(),
                'per_page' => $tournaments->perPage(),
                'total' => $tournaments->total(),
            ],
            'filters' => $request->only(['search', 'status', 'type']),
            'statuses' => ['draft', 'upcoming', 'registration_open', 'registration_closed', 'in_progress', 'completed', 'cancelled'],
            'types' => ['open', 'invitational', 'league', 'ranked'],
        ]);
    }

    public function show(Request $request, Tournament $tournament): Response
    {
        $tournament->load([
            'organizer.user',
            'geographicScope',
            'participants.playerProfile',
            'stages',
        ]);

        ActivityLog::log(
            ActivityLog::ACTION_DISPUTE_VIEWED, // reuse for now
            ActivityLog::ENTITY_TOURNAMENT,
            $tournament->id,
            "Viewed tournament: {$tournament->name}",
            null,
            $request
        );

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
                'match_type' => $m->match_type instanceof \BackedEnum ? $m->match_type->value : $m->match_type,
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

        return Inertia::render('Support/Tournaments/Show', [
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
        ]);
    }

    public function cancel(Request $request, Tournament $tournament): RedirectResponse
    {
        if ($tournament->status->value === 'cancelled') {
            return back()->with('error', 'Tournament is already cancelled.');
        }

        if ($tournament->status->value === 'completed') {
            return back()->with('error', 'Cannot cancel a completed tournament.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $tournament->update(['status' => 'cancelled']);

        ActivityLog::log(
            'tournament.cancelled',
            ActivityLog::ENTITY_TOURNAMENT,
            $tournament->id,
            "Cancelled tournament: {$tournament->name}",
            ['reason' => $validated['reason'] ?? null],
            $request
        );

        return back()->with('success', 'Tournament has been cancelled.');
    }

    public function pendingReview(Request $request): Response
    {
        $tournaments = Tournament::query()
            ->with(['createdBy.organizerProfile', 'geographicScope'])
            ->pendingReview()
            ->orderBy('created_at', 'asc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Support/Tournaments/PendingReview', [
            'tournaments' => [
                'data' => $tournaments->map(fn($t) => $this->formatTournament($t, true)),
                'current_page' => $tournaments->currentPage(),
                'last_page' => $tournaments->lastPage(),
                'per_page' => $tournaments->perPage(),
                'total' => $tournaments->total(),
            ],
        ]);
    }

    public function verify(Request $request, Tournament $tournament): RedirectResponse
    {
        if (!$tournament->isPendingReview()) {
            return back()->with('error', 'Tournament is not pending review.');
        }

        $tournament->verify($request->user());

        ActivityLog::log(
            'tournament.verified',
            ActivityLog::ENTITY_TOURNAMENT,
            $tournament->id,
            "Verified tournament: {$tournament->name}",
            null,
            $request
        );

        return back()->with('success', 'Tournament has been verified and is now in draft status.');
    }

    public function reject(Request $request, Tournament $tournament): RedirectResponse
    {
        if (!$tournament->isPendingReview()) {
            return back()->with('error', 'Tournament is not pending review.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
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

        return back()->with('success', 'Tournament has been rejected.');
    }

    public function start(Request $request, Tournament $tournament): RedirectResponse
    {
        // Validate tournament can be started
        if ($tournament->status !== TournamentStatus::REGISTRATION) {
            return back()->with('error', 'Tournament must be in registration status to start.');
        }

        // Check minimum participants (at least 2 for a bracket)
        $participantsCount = $tournament->participants()->count();
        if ($participantsCount < 2) {
            return back()->with('error', 'Tournament needs at least 2 participants to start.');
        }

        // Update status to indicate bracket generation is in progress
        // The job will set it to ACTIVE once complete
        ActivityLog::log(
            'tournament.starting',
            ActivityLog::ENTITY_TOURNAMENT,
            $tournament->id,
            "Initiated tournament start: {$tournament->name} with {$participantsCount} participants",
            ['participants_count' => $participantsCount],
            $request
        );

        // Dispatch the bracket generation job to the queue
        GenerateTournamentBracketJob::dispatch($tournament, $request->user()->id);

        return back()->with('success', "Tournament is being started. Bracket generation for {$participantsCount} participants has been queued.");
    }

    public function canStart(Tournament $tournament): array
    {
        $participantsCount = $tournament->participants()->count();
        $canStart = $tournament->status === TournamentStatus::REGISTRATION && $participantsCount >= 2;

        return [
            'can_start' => $canStart,
            'status' => $tournament->status instanceof \BackedEnum ? $tournament->status->value : $tournament->status,
            'participants_count' => $participantsCount,
            'min_participants' => 2,
            'reasons' => array_filter([
                $tournament->status !== TournamentStatus::REGISTRATION ? 'Tournament is not in registration status' : null,
                $participantsCount < 2 ? 'Need at least 2 participants' : null,
            ]),
        ];
    }

    private function formatTournament(Tournament $tournament, bool $detailed = false): array
    {
        $data = [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'slug' => $tournament->slug,
            'status' => $tournament->status instanceof \BackedEnum ? $tournament->status->value : $tournament->status,
            'type' => $tournament->type instanceof \BackedEnum ? $tournament->type->value : $tournament->type,
            'format' => $tournament->format instanceof \BackedEnum ? $tournament->format->value : $tournament->format,
            'participants_count' => $tournament->participants_count ?? $tournament->participants()->count(),
            'max_participants' => $tournament->max_participants,
            'starts_at' => $tournament->starts_at?->toISOString(),
            'ends_at' => $tournament->ends_at?->toISOString(),
            'created_at' => $tournament->created_at->toISOString(),
            'is_verified' => $tournament->isVerified(),
            'verified_at' => $tournament->verified_at?->toISOString(),
            'rejection_reason' => $tournament->rejection_reason,
            'organizer' => $tournament->organizer ? [
                'id' => $tournament->organizer->id,
                'organization_name' => $tournament->organizer->organization_name,
                'logo_url' => $tournament->organizer->logo_url,
            ] : ($tournament->createdBy?->organizerProfile ? [
                'id' => $tournament->createdBy->organizerProfile->id,
                'organization_name' => $tournament->createdBy->organizerProfile->organization_name,
                'logo_url' => $tournament->createdBy->organizerProfile->logo_url,
            ] : null),
        ];

        if ($detailed) {
            $data['description'] = $tournament->description;
            $data['rules'] = $tournament->rules;
            $data['race_to'] = $tournament->race_to;
            $data['finals_race_to'] = $tournament->finals_race_to;
            $data['prize_pool'] = $tournament->prize_pool;
            $data['entry_fee'] = $tournament->entry_fee;
            $data['registration_opens_at'] = $tournament->registration_opens_at?->toISOString();
            $data['registration_closes_at'] = $tournament->registration_closes_at?->toISOString();
            $data['venue_name'] = $tournament->venue_name;
            $data['venue_address'] = $tournament->venue_address;
            $data['location'] = $tournament->geographicScope ? [
                'id' => $tournament->geographicScope->id,
                'name' => $tournament->geographicScope->name,
            ] : null;
            $data['stages'] = $tournament->stages->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'status' => $s->status instanceof \BackedEnum ? $s->status->value : $s->status,
                'order' => $s->order,
            ]);
            $data['can_start'] = $this->canStart($tournament);
        }

        return $data;
    }
}
