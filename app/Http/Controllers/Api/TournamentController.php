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
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Services\MatchService;
use App\Services\TournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            return new TournamentCollection(collect());
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
            return new TournamentCollection(collect());
        }

        $filters = TournamentFiltersDTO::fromRequest($request);
        $tournaments = $this->tournamentService->getByOrganizer($user, $filters);

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

        // Note: Bracket generation will be handled by BracketGeneratorService (next phase)

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
}
