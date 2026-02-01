<?php

namespace App\Http\Controllers\Api;

use App\DTOs\TournamentFiltersDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\CreateTournamentRequest;
use App\Http\Requests\Tournament\RegisterForTournamentRequest;
use App\Http\Requests\Tournament\UpdateTournamentRequest;
use App\Http\Resources\MatchResource;
use App\Http\Resources\TournamentCollection;
use App\Http\Resources\TournamentParticipantResource;
use App\Http\Resources\TournamentResource;
use App\Models\Payment;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Services\MatchService;
use App\Services\TournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TournamentController extends Controller
{
    public function __construct(
        private TournamentService $tournamentService,
        private MatchService $matchService
    ) {}

    /**
     * List tournaments with filters.
     *
     * GET /api/tournaments
     */
    public function index(Request $request): TournamentCollection
    {
        $filters = TournamentFiltersDTO::fromRequest($request);
        $tournaments = $this->tournamentService->getFiltered($filters);

        return new TournamentCollection($tournaments);
    }

    /**
     * Get tournaments the current player is eligible for.
     *
     * GET /api/tournaments/eligible
     */
    public function eligible(Request $request): TournamentCollection
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return new TournamentCollection(
                new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15)
            );
        }

        $filters = TournamentFiltersDTO::fromRequest($request);
        $tournaments = $this->tournamentService->getEligibleForPlayer($user->playerProfile, $filters);

        return new TournamentCollection($tournaments);
    }

    /**
     * Get tournaments created by the current organizer.
     *
     * GET /api/tournaments/my-tournaments
     */
    public function myTournaments(Request $request): TournamentCollection
    {
        $user = $request->user();

        if (!$user->is_organizer) {
            return new TournamentCollection(
                new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15)
            );
        }

        $filters = TournamentFiltersDTO::fromRequest($request);
        $tournaments = $this->tournamentService->getByOrganizer($user, $filters);

        return new TournamentCollection($tournaments);
    }

    /**
     * Get tournaments the current player has registered for.
     *
     * GET /api/tournaments/my-registered
     */
    public function myRegistered(Request $request): TournamentCollection
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            // Return empty paginator instead of collection
            return new TournamentCollection(
                new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15)
            );
        }

        $filters = TournamentFiltersDTO::fromRequest($request);
        $tournaments = $this->tournamentService->getRegisteredByPlayer($user->playerProfile, $filters);

        return new TournamentCollection($tournaments);
    }

    /**
     * Show a single tournament.
     *
     * GET /api/tournaments/{tournament}
     */
    public function show(Tournament $tournament): TournamentResource
    {
        $tournament->load(['geographicScope', 'createdBy.organizerProfile', 'participants.playerProfile']);

        return new TournamentResource($tournament);
    }

    /**
     * Create a new tournament.
     *
     * POST /api/tournaments
     */
    public function store(CreateTournamentRequest $request): JsonResponse
    {
        $tournament = $this->tournamentService->create(
            $request->toDTO(),
            $request->user()
        );

        return response()->json([
            'message' => 'Tournament created successfully',
            'tournament' => new TournamentResource($tournament),
        ], 201);
    }

    /**
     * Update a tournament.
     *
     * PUT /api/tournaments/{tournament}
     */
    public function update(UpdateTournamentRequest $request, Tournament $tournament): JsonResponse
    {
        $tournament = $this->tournamentService->update($tournament, $request->toDTO());

        return response()->json([
            'message' => 'Tournament updated successfully',
            'tournament' => new TournamentResource($tournament),
        ]);
    }

    /**
     * Delete a tournament (drafts only).
     *
     * DELETE /api/tournaments/{tournament}
     */
    public function destroy(Tournament $tournament): JsonResponse
    {
        Gate::authorize('delete', $tournament);

        $this->tournamentService->delete($tournament);

        return response()->json([
            'message' => 'Tournament deleted successfully',
        ]);
    }

    /**
     * Open registration for a tournament.
     *
     * POST /api/tournaments/{tournament}/open-registration
     */
    public function openRegistration(Tournament $tournament): JsonResponse
    {
        Gate::authorize('openRegistration', $tournament);

        $tournament = $this->tournamentService->openRegistration($tournament);

        return response()->json([
            'message' => 'Registration is now open',
            'tournament' => new TournamentResource($tournament),
        ]);
    }

    /**
     * Close registration for a tournament.
     *
     * POST /api/tournaments/{tournament}/close-registration
     */
    public function closeRegistration(Tournament $tournament): JsonResponse
    {
        Gate::authorize('closeRegistration', $tournament);

        $tournament = $this->tournamentService->closeRegistration($tournament);

        return response()->json([
            'message' => 'Registration is now closed',
            'tournament' => new TournamentResource($tournament),
        ]);
    }

    /**
     * Start a tournament (generate brackets).
     *
     * POST /api/tournaments/{tournament}/start
     */
    public function start(Tournament $tournament): JsonResponse
    {
        Gate::authorize('start', $tournament);

        // Check if can start
        $canStart = $this->tournamentService->canStart($tournament);

        if (!$canStart['can_start']) {
            return response()->json([
                'message' => 'Cannot start tournament',
                'issues' => $canStart['issues'],
            ], 422);
        }

        $tournament = $this->tournamentService->start($tournament);

        return response()->json([
            'message' => 'Tournament started successfully',
            'tournament' => new TournamentResource($tournament),
        ]);
    }

    /**
     * Cancel a tournament.
     *
     * POST /api/tournaments/{tournament}/cancel
     */
    public function cancel(Request $request, Tournament $tournament): JsonResponse
    {
        Gate::authorize('cancel', $tournament);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $tournament = $this->tournamentService->cancel($tournament, $request->reason);

        return response()->json([
            'message' => 'Tournament cancelled',
            'tournament' => new TournamentResource($tournament),
        ]);
    }

    /**
     * Register current player for a tournament.
     *
     * POST /api/tournaments/{tournament}/register
     */
    public function register(RegisterForTournamentRequest $request, Tournament $tournament): JsonResponse
    {
        $participant = $this->tournamentService->registerPlayer(
            $tournament,
            $request->user()->playerProfile
        );

        return response()->json([
            'message' => 'Successfully registered for tournament',
            'participant' => new TournamentParticipantResource($participant),
        ], 201);
    }

    /**
     * Withdraw current player from a tournament.
     *
     * DELETE /api/tournaments/{tournament}/register
     */
    public function withdraw(Tournament $tournament): JsonResponse
    {
        Gate::authorize('withdraw', $tournament);

        $this->tournamentService->withdrawPlayer(
            $tournament,
            request()->user()->playerProfile
        );

        return response()->json([
            'message' => 'Successfully withdrawn from tournament',
        ]);
    }

    /**
     * Get tournament participants.
     *
     * GET /api/tournaments/{tournament}/participants
     */
    public function participants(Request $request, Tournament $tournament): JsonResponse
    {
        $sortBy = $request->input('sort_by', 'registered_at');
        $participants = $this->tournamentService->getParticipants($tournament, $sortBy);

        return response()->json([
            'participants' => TournamentParticipantResource::collection($participants),
            'total' => $participants->count(),
        ]);
    }

    /**
     * Get tournament standings/leaderboard.
     *
     * GET /api/tournaments/{tournament}/standings
     */
    public function standings(Tournament $tournament): JsonResponse
    {
        $standings = $this->tournamentService->getStandings($tournament);

        return response()->json([
            'standings' => TournamentParticipantResource::collection($standings),
        ]);
    }

    /**
     * Remove a participant (organizer action).
     *
     * DELETE /api/tournaments/{tournament}/participants/{participant}
     */
    public function removeParticipant(Tournament $tournament, TournamentParticipant $participant): JsonResponse
    {
        Gate::authorize('manageParticipants', $tournament);

        if ($participant->tournament_id !== $tournament->id) {
            return response()->json([
                'message' => 'Participant does not belong to this tournament',
            ], 422);
        }

        $this->tournamentService->removeParticipant($tournament, $participant);

        return response()->json([
            'message' => 'Participant removed successfully',
        ]);
    }

    /**
     * Get tournament matches.
     *
     * GET /api/tournaments/{tournament}/matches
     */
    public function matches(Request $request, Tournament $tournament): JsonResponse
    {
        $matches = $tournament->matches()
            ->with(['player1.playerProfile', 'player2.playerProfile', 'winner.playerProfile'])
            ->when($request->filled('round'), fn($q) => $q->where('round_number', $request->round))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderBy('round_number')
            ->orderBy('bracket_position')
            ->get();

        return response()->json([
            'matches' => MatchResource::collection($matches),
            'total' => $matches->count(),
        ]);
    }

    /**
     * Get tournament bracket structure.
     *
     * GET /api/tournaments/{tournament}/bracket
     */
    public function bracket(Tournament $tournament): JsonResponse
    {
        $bracket = $this->matchService->getTournamentBracket($tournament);

        return response()->json([
            'bracket' => $bracket,
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'format' => $tournament->format->value,
                'status' => $tournament->status->value,
                'participants_count' => $tournament->participants_count,
            ],
        ]);
    }

    /**
     * Check if tournament can be started.
     *
     * GET /api/tournaments/{tournament}/can-start
     */
    public function canStart(Tournament $tournament): JsonResponse
    {
        Gate::authorize('start', $tournament);

        $result = $this->tournamentService->canStart($tournament);

        return response()->json($result);
    }

    /**
     * Get tournament analytics (organizer only).
     *
     * GET /api/tournaments/{tournament}/analytics
     */
    public function analytics(Tournament $tournament): JsonResponse
    {
        Gate::authorize('update', $tournament);

        // Match statistics
        $matchStats = DB::table('matches')
            ->where('tournament_id', $tournament->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'pending_confirmation' THEN 1 ELSE 0 END) as pending_confirmation,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN match_type = 'bye' THEN 1 ELSE 0 END) as byes,
                AVG(CASE WHEN status = 'completed' THEN player1_score + player2_score ELSE NULL END) as avg_frames_per_match
            ")
            ->first();

        // Participant statistics
        $participantStats = DB::table('tournament_participants')
            ->where('tournament_id', $tournament->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as registered,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'eliminated' THEN 1 ELSE 0 END) as eliminated,
                SUM(CASE WHEN status = 'withdrawn' THEN 1 ELSE 0 END) as withdrawn,
                SUM(CASE WHEN status = 'disqualified' THEN 1 ELSE 0 END) as disqualified,
                AVG(matches_played) as avg_matches_played,
                AVG(matches_won) as avg_matches_won
            ")
            ->first();

        // Rating distribution of participants
        $ratingDistribution = DB::table('tournament_participants')
            ->join('player_profiles', 'tournament_participants.player_profile_id', '=', 'player_profiles.id')
            ->where('tournament_participants.tournament_id', $tournament->id)
            ->selectRaw("
                CASE
                    WHEN player_profiles.rating < 900 THEN 'Beginner (<900)'
                    WHEN player_profiles.rating < 1100 THEN 'Intermediate (900-1099)'
                    WHEN player_profiles.rating < 1300 THEN 'Advanced (1100-1299)'
                    ELSE 'Expert (1300+)'
                END as category,
                COUNT(*) as count
            ")
            ->groupBy('category')
            ->get()
            ->pluck('count', 'category')
            ->toArray();

        // Top performers
        $topPerformers = TournamentParticipant::where('tournament_id', $tournament->id)
            ->with('playerProfile:id,first_name,last_name,nickname,rating,photo_url')
            ->orderByDesc('matches_won')
            ->orderByDesc('frame_difference')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'player' => [
                    'id' => $p->playerProfile->id,
                    'name' => $p->playerProfile->display_name,
                    'rating' => $p->playerProfile->rating,
                    'photo_url' => $p->playerProfile->photo_url,
                ],
                'seed' => $p->seed,
                'matches_played' => $p->matches_played,
                'matches_won' => $p->matches_won,
                'frames_won' => $p->frames_won,
                'frames_lost' => $p->frames_lost,
                'frame_difference' => $p->frame_difference,
                'final_position' => $p->final_position,
                'status' => $p->status,
            ]);

        // Round progression
        $roundStats = DB::table('matches')
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('round_name')
            ->selectRaw("
                round_number,
                round_name,
                COUNT(*) as total_matches,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_matches,
                AVG(CASE WHEN status = 'completed' THEN player1_score + player2_score ELSE NULL END) as avg_frames
            ")
            ->groupBy('round_number', 'round_name')
            ->orderBy('round_number')
            ->get();

        return response()->json([
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'status' => $tournament->status->value,
                'format' => $tournament->format->value,
            ],
            'matches' => [
                'total' => $matchStats->total ?? 0,
                'scheduled' => $matchStats->scheduled ?? 0,
                'pending_confirmation' => $matchStats->pending_confirmation ?? 0,
                'completed' => $matchStats->completed ?? 0,
                'disputed' => $matchStats->disputed ?? 0,
                'expired' => $matchStats->expired ?? 0,
                'byes' => $matchStats->byes ?? 0,
                'avg_frames_per_match' => round($matchStats->avg_frames_per_match ?? 0, 1),
                'completion_rate' => $matchStats->total > 0
                    ? round((($matchStats->completed ?? 0) / $matchStats->total) * 100, 1)
                    : 0,
            ],
            'participants' => [
                'total' => $participantStats->total ?? 0,
                'registered' => $participantStats->registered ?? 0,
                'active' => $participantStats->active ?? 0,
                'eliminated' => $participantStats->eliminated ?? 0,
                'withdrawn' => $participantStats->withdrawn ?? 0,
                'disqualified' => $participantStats->disqualified ?? 0,
                'avg_matches_played' => round($participantStats->avg_matches_played ?? 0, 1),
                'avg_matches_won' => round($participantStats->avg_matches_won ?? 0, 1),
            ],
            'rating_distribution' => $ratingDistribution,
            'top_performers' => $topPerformers,
            'rounds' => $roundStats,
        ]);
    }

    /**
     * Get tournament revenue details (organizer only).
     *
     * GET /api/tournaments/{tournament}/revenue
     */
    public function revenue(Tournament $tournament): JsonResponse
    {
        Gate::authorize('update', $tournament);

        // Get all payments for this tournament
        $payments = Payment::where('payable_type', Tournament::class)
            ->where('payable_id', $tournament->id)
            ->with('user.playerProfile:id,user_id,first_name,last_name,nickname')
            ->orderByDesc('created_at')
            ->get();

        $successfulPayments = $payments->where('status', 'success');
        $totalRevenue = $successfulPayments->sum('amount');
        $platformFee = (int) ($totalRevenue * 0.10);
        $netRevenue = $totalRevenue - $platformFee;

        // Payment status breakdown
        $statusBreakdown = $payments->groupBy('status')->map->count();

        // Daily revenue (last 30 days)
        $dailyRevenue = Payment::where('payable_type', Tournament::class)
            ->where('payable_id', $tournament->id)
            ->where('status', 'success')
            ->where('paid_at', '>=', now()->subDays(30))
            ->selectRaw("DATE(paid_at) as date, SUM(amount) as total, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'entry_fee' => $tournament->entry_fee,
                'formatted_entry_fee' => $tournament->entry_fee
                    ? 'KES ' . number_format($tournament->entry_fee / 100, 2)
                    : 'Free',
            ],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'platform_fee' => $platformFee,
                'net_revenue' => $netRevenue,
                'formatted_total' => 'KES ' . number_format($totalRevenue / 100, 2),
                'formatted_net' => 'KES ' . number_format($netRevenue / 100, 2),
                'total_payments' => $payments->count(),
                'successful_payments' => $successfulPayments->count(),
            ],
            'status_breakdown' => [
                'pending' => $statusBreakdown->get('pending', 0),
                'success' => $statusBreakdown->get('success', 0),
                'failed' => $statusBreakdown->get('failed', 0),
                'abandoned' => $statusBreakdown->get('abandoned', 0),
            ],
            'payments' => $successfulPayments->take(50)->map(fn($payment) => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'formatted_amount' => $payment->formatted_amount,
                'player' => $payment->user?->playerProfile ? [
                    'id' => $payment->user->playerProfile->id,
                    'name' => $payment->user->playerProfile->display_name,
                ] : null,
                'channel' => $payment->channel,
                'paid_at' => $payment->paid_at?->toISOString(),
            ]),
            'daily_revenue' => $dailyRevenue,
        ]);
    }
}
