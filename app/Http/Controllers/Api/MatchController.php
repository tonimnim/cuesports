<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Match\DisputeMatchRequest;
use App\Http\Requests\Match\DisputeResultRequest;
use App\Http\Requests\Match\ReportNoShowRequest;
use App\Http\Requests\Match\ResolveDisputeRequest;
use App\Http\Requests\Match\SubmitMatchResultRequest;
use App\Http\Requests\Match\SubmitResultRequest;
use App\Http\Requests\Match\UploadEvidenceRequest;
use App\Http\Resources\MatchEvidenceResource;
use App\Http\Resources\MatchResource;
use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Events\MatchResolved;
use App\Models\GameMatch as MatchModel;
use App\Models\MatchEvidence;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Services\Bracket\BracketService;
use App\Services\MatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MatchController extends Controller
{
    public function __construct(
        private MatchService $matchService,
        private BracketService $bracketService
    ) {}

    /**
     * Get matches feed for the LiveScore-style page.
     *
     * GET /api/matches/feed
     *
     * Query params:
     * - date: Filter by date (today, tomorrow, yesterday, or YYYY-MM-DD)
     * - tournament_id: Filter by specific tournament
     * - status: Filter by match status (scheduled, in_progress, completed)
     */
    public function feed(Request $request): JsonResponse
    {
        $date = $request->input('date', 'today');
        $tournamentId = $request->input('tournament_id');
        $status = $request->input('status');

        // Parse date
        $targetDate = match ($date) {
            'today' => Carbon::today(),
            'tomorrow' => Carbon::tomorrow(),
            'yesterday' => Carbon::yesterday(),
            default => Carbon::parse($date)->startOfDay(),
        };

        // Get all active tournaments with their matches for the target date
        $tournamentsQuery = Tournament::where('status', TournamentStatus::ACTIVE)
            ->with([
                'createdBy.organizerProfile',
                'matches' => function ($query) use ($targetDate, $status) {
                    $query->with(['player1.playerProfile', 'player2.playerProfile', 'winner.playerProfile'])
                        ->where('match_type', '!=', 'bye')
                        ->where(function ($q) use ($targetDate) {
                            // Include matches scheduled for this date
                            $q->whereDate('scheduled_play_date', $targetDate)
                                // Or matches played on this date
                                ->orWhereDate('played_at', $targetDate)
                                // Or any scheduled/in-progress matches if looking at today
                                ->orWhere(function ($sq) {
                                    $sq->whereIn('status', ['scheduled', 'in_progress', 'pending_confirmation']);
                                });
                        })
                        ->when($status, fn($q) => $q->where('status', $status))
                        ->orderBy('round_number')
                        ->orderBy('bracket_position');
                },
            ]);

        if ($tournamentId) {
            $tournamentsQuery->where('id', $tournamentId);
        }

        $tournaments = $tournamentsQuery->get();

        // Transform the data
        $result = $tournaments->filter(function ($tournament) {
            // Only include tournaments that have matches
            return $tournament->matches->count() > 0;
        })->map(function ($tournament) {
            return [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'status' => [
                    'value' => $tournament->status->value,
                    'label' => $tournament->status->label(),
                ],
                'venue' => [
                    'name' => $tournament->venue_name,
                    'address' => $tournament->venue_address,
                ],
                'organizer' => $tournament->createdBy?->organizerProfile ? [
                    'name' => $tournament->createdBy->organizerProfile->organization_name,
                    'logo_url' => $tournament->createdBy->organizerProfile->logo_url,
                ] : null,
                'matches' => $tournament->matches->map(function ($match) {
                    return [
                        'id' => $match->id,
                        'round_number' => $match->round_number,
                        'round_name' => $match->round_name,
                        'bracket_position' => $match->bracket_position,
                        'match_type' => $match->match_type->value,
                        'status' => [
                            'value' => $match->status->value,
                            'label' => $match->status->label(),
                        ],
                        'player1' => $match->player1 ? [
                            'id' => $match->player1->id,
                            'name' => $match->player1->playerProfile?->display_name ?? 'TBD',
                            'photo_url' => $match->player1->playerProfile?->photo_url,
                            'seed' => $match->player1->seed,
                        ] : null,
                        'player2' => $match->player2 ? [
                            'id' => $match->player2->id,
                            'name' => $match->player2->playerProfile?->display_name ?? 'TBD',
                            'photo_url' => $match->player2->playerProfile?->photo_url,
                            'seed' => $match->player2->seed,
                        ] : null,
                        'player1_score' => $match->player1_score,
                        'player2_score' => $match->player2_score,
                        'winner_id' => $match->winner_id,
                        'scheduled_at' => $match->scheduled_play_date?->toISOString(),
                        'played_at' => $match->played_at?->toISOString(),
                    ];
                }),
            ];
        })->values();

        // Get summary stats
        $allMatches = $tournaments->flatMap->matches;
        $stats = [
            'total_tournaments' => $result->count(),
            'total_matches' => $allMatches->count(),
            'live' => $allMatches->where('status', 'in_progress')->count(),
            'scheduled' => $allMatches->where('status', 'scheduled')->count(),
            'completed' => $allMatches->where('status', 'completed')->count(),
        ];

        return response()->json([
            'tournaments' => $result,
            'stats' => $stats,
            'date' => $targetDate->toDateString(),
        ]);
    }

    /**
     * Get a single match.
     *
     * GET /api/matches/{match}
     */
    public function show(MatchModel $match): MatchResource
    {
        $match->load([
            'player1.playerProfile',
            'player2.playerProfile',
            'winner.playerProfile',
            'tournament',
        ]);

        return new MatchResource($match);
    }

    /**
     * Submit match result.
     *
     * POST /api/matches/{match}/submit
     */
    public function submit(SubmitMatchResultRequest $request, MatchModel $match): JsonResponse
    {
        $user = $request->user();
        $participant = $this->getParticipantForUser($match, $user);

        if (!$participant) {
            return response()->json([
                'message' => 'You are not a participant in this match.',
            ], 403);
        }

        try {
            $match = $this->matchService->submitResult(
                $match,
                $participant,
                $request->input('my_score'),
                $request->input('opponent_score')
            );

            return response()->json([
                'message' => 'Result submitted successfully. Awaiting opponent confirmation.',
                'match' => new MatchResource($match),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Confirm match result.
     *
     * POST /api/matches/{match}/confirm
     */
    public function confirm(Request $request, MatchModel $match): JsonResponse
    {
        $user = $request->user();
        $participant = $this->getParticipantForUser($match, $user);

        if (!$participant) {
            return response()->json([
                'message' => 'You are not a participant in this match.',
            ], 403);
        }

        try {
            $match = $this->matchService->confirmResult($match, $participant);

            // Handle bracket progression
            $this->bracketService->advanceWinner($match);

            // Handle semi-finals losers → third-place match
            $this->bracketService->handleSemiFinalsCompletion($match);

            // Check if tournament is complete
            $this->checkTournamentCompletion($match->tournament);

            return response()->json([
                'message' => 'Match result confirmed.',
                'match' => new MatchResource($match),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Dispute match result.
     *
     * POST /api/matches/{match}/dispute
     */
    public function dispute(DisputeMatchRequest $request, MatchModel $match): JsonResponse
    {
        $user = $request->user();
        $participant = $this->getParticipantForUser($match, $user);

        if (!$participant) {
            return response()->json([
                'message' => 'You are not a participant in this match.',
            ], 403);
        }

        try {
            $match = $this->matchService->disputeResult(
                $match,
                $participant,
                $request->input('reason')
            );

            return response()->json([
                'message' => 'Match result disputed. Our support team will review this.',
                'match' => new MatchResource($match),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Submit match result.
     * POST /api/matches/{match}/submit-result
     */
    public function submitResult(SubmitResultRequest $request, MatchModel $match): JsonResponse
    {
        $user = $request->user();
        $participant = $this->getParticipantForUser($match, $user);

        if (!$participant) {
            return response()->json(['message' => 'You are not a participant in this match'], 403);
        }

        if (!$match->canSubmitResult($participant)) {
            return response()->json(['message' => 'Cannot submit result for this match'], 400);
        }

        try {
            $myScore = $request->input('my_score');
            $opponentScore = $request->input('opponent_score');

            $match = $this->matchService->submitResult(
                $match,
                $participant,
                $myScore,
                $opponentScore
            );

            // Set confirmation deadline
            $match->setConfirmationDeadline();
            $match->save();

            return response()->json([
                'message' => 'Result submitted. Waiting for opponent confirmation.',
                'match' => new MatchResource($match->fresh()),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Confirm opponent's submitted result.
     * POST /api/matches/{match}/confirm
     */
    public function confirmResult(Request $request, MatchModel $match): JsonResponse
    {
        $user = $request->user();
        $participant = $this->getParticipantForUser($match, $user);

        if (!$participant) {
            return response()->json(['message' => 'You are not a participant in this match'], 403);
        }

        if (!$match->canConfirm($participant)) {
            return response()->json(['message' => 'Cannot confirm this match result'], 400);
        }

        try {
            $match = $this->matchService->confirmResult($match, $participant);

            // Advance winner if applicable
            if ($match->winner_id && $match->next_match_id) {
                $this->bracketService->advanceWinner($match);
            }

            return response()->json([
                'message' => 'Result confirmed',
                'match' => new MatchResource($match->fresh()),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Dispute opponent's submitted result.
     * POST /api/matches/{match}/dispute
     */
    public function disputeResult(DisputeResultRequest $request, MatchModel $match): JsonResponse
    {
        $user = $request->user();
        $participant = $this->getParticipantForUser($match, $user);

        if (!$participant) {
            return response()->json(['message' => 'You are not a participant in this match'], 403);
        }

        if (!$match->canDispute($participant)) {
            return response()->json(['message' => 'Cannot dispute this match result'], 400);
        }

        $match->dispute($participant, $request->input('reason'));

        // Optionally store their claimed score
        if ($request->filled('my_score') && $request->filled('opponent_score')) {
            $match->dispute_claimed_score = json_encode([
                'submitter_id' => $participant->id,
                'my_score' => $request->input('my_score'),
                'opponent_score' => $request->input('opponent_score'),
            ]);
            $match->save();
        }

        return response()->json([
            'message' => 'Dispute submitted. Organizer will review.',
            'match' => new MatchResource($match->fresh()),
        ]);
    }

    /**
     * Report opponent no-show.
     * POST /api/matches/{match}/report-no-show
     */
    public function reportNoShow(ReportNoShowRequest $request, MatchModel $match): JsonResponse
    {
        $user = $request->user();
        $participant = $this->getParticipantForUser($match, $user);

        if (!$participant) {
            return response()->json(['message' => 'You are not a participant in this match'], 403);
        }

        if (!$match->isScheduled()) {
            return response()->json(['message' => 'Can only report no-show for scheduled matches'], 400);
        }

        if ($match->hasNoShowReport()) {
            return response()->json(['message' => 'No-show already reported for this match'], 400);
        }

        $match->no_show_reported_by = $participant->id;
        $match->no_show_reported_at = now();
        $match->status = MatchStatus::DISPUTED;
        $match->dispute_reason = 'No-show: ' . $request->input('description');
        $match->save();

        return response()->json([
            'message' => 'No-show reported. Organizer will review.',
            'match' => new MatchResource($match->fresh()),
        ]);
    }

    /**
     * Resolve a disputed match (admin action).
     *
     * POST /api/matches/{match}/resolve
     */
    public function resolve(ResolveDisputeRequest $request, MatchModel $match): JsonResponse
    {
        try {
            $match = $this->matchService->resolveDispute(
                $match,
                $request->user(),
                $request->input('player1_score'),
                $request->input('player2_score'),
                $request->input('notes')
            );

            // Handle bracket progression
            $this->bracketService->advanceWinner($match);
            $this->bracketService->handleSemiFinalsCompletion($match);
            $this->checkTournamentCompletion($match->tournament);

            return response()->json([
                'message' => 'Dispute resolved successfully.',
                'match' => new MatchResource($match),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Resolve a disputed match (organizer only).
     * POST /api/matches/{match}/resolve-dispute
     */
    public function resolveDispute(Request $request, MatchModel $match): JsonResponse
    {
        $user = $request->user();

        // Check if user is the tournament organizer
        if ($match->tournament->created_by !== $user->id) {
            return response()->json(['message' => 'Only the tournament organizer can resolve disputes'], 403);
        }

        if ($match->status !== MatchStatus::DISPUTED) {
            return response()->json(['message' => 'Match is not in disputed status'], 400);
        }

        $request->validate([
            'winner_id' => 'required|exists:tournament_participants,id',
            'player1_score' => 'required|integer|min:0',
            'player2_score' => 'required|integer|min:0',
            'resolution_notes' => 'nullable|string|max:500',
            'forfeit_type' => 'nullable|string|in:no_show,walkover',
        ]);

        DB::transaction(function () use ($request, $match, $user) {
            $match->player1_score = $request->input('player1_score');
            $match->player2_score = $request->input('player2_score');
            $match->winner_id = $request->input('winner_id');
            $match->loser_id = $match->winner_id === $match->player1_id
                ? $match->player2_id
                : $match->player1_id;
            $match->status = MatchStatus::COMPLETED;
            $match->resolved_by = $user->id;
            $match->resolved_at = now();
            $match->resolution_notes = $request->input('resolution_notes');
            $match->played_at = now();

            if ($request->filled('forfeit_type')) {
                $match->forfeit_type = $request->input('forfeit_type');
            }

            $match->save();

            // Update participant stats
            $match->updateParticipantStats();

            // Advance winner
            if ($match->next_match_id) {
                $this->bracketService->advanceWinner($match);
            }

            // Handle semi-finals completion
            $this->bracketService->handleSemiFinalsCompletion($match);

            // Fire event
            event(new MatchResolved($match));
        });

        // Check tournament completion
        $this->checkTournamentCompletion($match->tournament);

        return response()->json([
            'message' => 'Dispute resolved successfully',
            'match' => new MatchResource($match->fresh()),
        ]);
    }

    /**
     * Award walkover to a player (organizer only).
     * POST /api/matches/{match}/award-walkover
     */
    public function awardWalkover(Request $request, MatchModel $match): JsonResponse
    {
        $user = $request->user();

        if ($match->tournament->created_by !== $user->id) {
            return response()->json(['message' => 'Only the tournament organizer can award walkovers'], 403);
        }

        $request->validate([
            'winner_id' => 'required|exists:tournament_participants,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $winnerId = $request->input('winner_id');

        // Verify winner is in this match
        if ($winnerId !== $match->player1_id && $winnerId !== $match->player2_id) {
            return response()->json(['message' => 'Invalid winner - not a participant in this match'], 400);
        }

        $raceTo = $match->tournament->race_to ?? 3;

        DB::transaction(function () use ($request, $match, $user, $winnerId, $raceTo) {
            $match->winner_id = $winnerId;
            $match->loser_id = $winnerId === $match->player1_id ? $match->player2_id : $match->player1_id;
            $match->player1_score = $winnerId === $match->player1_id ? $raceTo : 0;
            $match->player2_score = $winnerId === $match->player2_id ? $raceTo : 0;
            $match->status = MatchStatus::COMPLETED;
            $match->forfeit_type = 'walkover';
            $match->resolved_by = $user->id;
            $match->resolved_at = now();
            $match->resolution_notes = $request->input('reason', 'Walkover awarded by organizer');
            $match->played_at = now();
            $match->save();

            // Update stats
            $match->updateParticipantStats();

            // Advance winner
            if ($match->next_match_id) {
                $this->bracketService->advanceWinner($match);
            }

            // Handle semi-finals completion
            $this->bracketService->handleSemiFinalsCompletion($match);
        });

        // Check tournament completion
        $this->checkTournamentCompletion($match->tournament);

        return response()->json([
            'message' => 'Walkover awarded',
            'match' => new MatchResource($match->fresh()),
        ]);
    }

    /**
     * Get matches requiring action for current user.
     *
     * GET /api/matches/my-matches
     */
    public function myMatches(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return response()->json([
                'pending' => [],
                'requiring_action' => [],
                'recent' => [],
            ]);
        }

        // Get all active tournament participations
        $participations = $user->playerProfile->tournamentParticipations()
            ->whereHas('tournament', fn($q) => $q->where('status', 'active'))
            ->with('tournament')
            ->get();

        $pendingMatches = collect();
        $actionRequired = collect();

        foreach ($participations as $participant) {
            $pending = $this->matchService->getPendingMatchesForPlayer($participant);
            $pendingMatches = $pendingMatches->merge($pending);

            $actions = $this->matchService->getMatchesRequiringAction($participant);
            $actionRequired = $actionRequired->merge($actions);
        }

        // Get recent completed matches
        $recentMatches = MatchModel::whereHas('tournament', fn($q) => $q->where('status', 'active'))
            ->where(function ($q) use ($user) {
                $participantIds = $user->playerProfile->tournamentParticipations()->pluck('id');
                $q->whereIn('player1_id', $participantIds)
                    ->orWhereIn('player2_id', $participantIds);
            })
            ->completed()
            ->with(['player1.playerProfile.user', 'player2.playerProfile.user', 'tournament'])
            ->orderByDesc('played_at')
            ->limit(10)
            ->get();

        return response()->json([
            'pending' => MatchResource::collection($pendingMatches->unique('id')),
            'requiring_action' => MatchResource::collection($actionRequired->unique('id')),
            'recent' => MatchResource::collection($recentMatches),
        ]);
    }

    /**
     * Get all disputed matches (admin view).
     *
     * GET /api/matches/disputed
     */
    public function disputed(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->is_support && !$user->is_super_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $matches = $this->matchService->getDisputedMatches();

        return response()->json([
            'matches' => MatchResource::collection($matches),
            'total' => $matches->count(),
        ]);
    }

    /**
     * Get participant for the current user in a match.
     */
    protected function getParticipantForUser(MatchModel $match, $user)
    {
        if (!$user->playerProfile) {
            return null;
        }

        $playerProfileId = $user->playerProfile->id;

        // Check if user is player1
        if ($match->player1 && $match->player1->player_profile_id === $playerProfileId) {
            return $match->player1;
        }

        // Check if user is player2
        if ($match->player2 && $match->player2->player_profile_id === $playerProfileId) {
            return $match->player2;
        }

        return null;
    }

    /**
     * Check if tournament should be completed.
     */
    protected function checkTournamentCompletion(Tournament $tournament): void
    {
        if ($this->bracketService->isBracketComplete($tournament)) {
            $this->bracketService->calculateFinalPositions($tournament);
            $tournament->complete();
        }
    }

    /**
     * Get evidence for a match.
     * GET /api/matches/{match}/evidence
     */
    public function getEvidence(MatchModel $match): JsonResponse
    {
        $evidence = $match->evidence()->with('uploadedBy.playerProfile')->get();

        return response()->json([
            'evidence' => MatchEvidenceResource::collection($evidence),
        ]);
    }

    /**
     * Upload evidence for a match (image file) or add video link.
     * POST /api/matches/{match}/evidence
     */
    public function uploadEvidence(Request $request, MatchModel $match): JsonResponse
    {
        Log::info('uploadEvidence: Starting', ['match_id' => $match->id, 'request_type' => $request->input('type'), 'has_file' => $request->hasFile('file')]);

        $user = $request->user();
        $participant = $this->getParticipantForUser($match, $user);
        Log::info('uploadEvidence: Got participant', ['participant_id' => $participant?->id, 'user_id' => $user?->id]);

        if (!$participant) {
            return response()->json(['message' => 'You are not a participant in this match'], 403);
        }

        // Only allow evidence upload for disputed matches or when reporting no-show
        if (!in_array($match->status->value, ['disputed', 'scheduled', 'pending_confirmation'])) {
            return response()->json(['message' => 'Cannot upload evidence for this match status'], 400);
        }

        $type = $request->input('type');

        // Handle video link submission (no file upload)
        if ($type === 'video' && $request->has('url') && !$request->hasFile('file')) {
            $request->validate([
                'url' => 'required|url|max:500',
                'description' => 'nullable|string|max:255',
            ]);

            $evidence = MatchEvidence::create([
                'match_id' => $match->id,
                'uploaded_by' => $participant->id,
                'file_type' => 'video',
                'file_url' => $request->input('url'),
                'description' => $request->input('description'),
                'evidence_type' => MatchEvidence::TYPE_DISPUTE_EVIDENCE,
                'uploaded_at' => now(),
            ]);

            return response()->json([
                'message' => 'Video link added successfully',
                'evidence' => new MatchEvidenceResource($evidence->load('uploadedBy.playerProfile')),
            ], 201);
        }

        // Handle file upload (images only)
        Log::info('uploadEvidence: Starting file upload validation');

        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
            'type' => 'required|in:image,video',
            'description' => 'nullable|string|max:255',
        ]);

        Log::info('uploadEvidence: Validation passed');

        try {
            $file = $request->file('file');
            Log::info('uploadEvidence: Got file', ['filename' => $file?->getClientOriginalName(), 'size' => $file?->getSize()]);

            // Try Cloudinary first, fallback to local storage
            $url = null;
            $publicId = null;

            $cloudinaryConfigured = config('cloudinary.cloud_url') && config('filesystems.disks.cloudinary.url');
            Log::info('uploadEvidence: Cloudinary check', ['configured' => $cloudinaryConfigured]);

            if ($cloudinaryConfigured) {
                // Upload to Cloudinary using the SDK
                $folder = "cuesports/matches/{$match->id}/evidence";
                Log::info('uploadEvidence: Uploading to Cloudinary', ['folder' => $folder]);

                $cloudinary = app(\Cloudinary\Cloudinary::class);
                $uploadResult = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                    'folder' => $folder,
                    'resource_type' => 'image',
                ]);

                $url = $uploadResult['secure_url'];
                $publicId = $uploadResult['public_id'];
                Log::info('uploadEvidence: Cloudinary upload success', ['url' => $url]);
            } else {
                // Fallback to local storage
                Log::info('uploadEvidence: Using local storage');
                $path = $file->store("matches/{$match->id}/evidence", 'public');
                $url = Storage::disk('public')->url($path);
                Log::info('uploadEvidence: Local storage success', ['url' => $url]);
            }

            Log::info('uploadEvidence: Creating evidence record');
            $evidence = MatchEvidence::create([
                'match_id' => $match->id,
                'uploaded_by' => $participant->id,
                'file_type' => 'image',
                'file_url' => $url,
                'public_id' => $publicId,
                'description' => $request->input('description'),
                'evidence_type' => MatchEvidence::TYPE_DISPUTE_EVIDENCE,
                'uploaded_at' => now(),
            ]);

            return response()->json([
                'message' => 'Evidence uploaded successfully',
                'evidence' => new MatchEvidenceResource($evidence->load('uploadedBy.playerProfile')),
            ], 201);
        } catch (\Exception $e) {
            Log::error('uploadEvidence: Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to upload evidence: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete evidence.
     * DELETE /api/matches/{match}/evidence/{evidence}
     */
    public function deleteEvidence(MatchModel $match, MatchEvidence $evidence): JsonResponse
    {
        $user = request()->user();
        $participant = $this->getParticipantForUser($match, $user);

        // Only uploader or organizer can delete
        if (!$participant || $evidence->uploaded_by !== $participant->id) {
            // Check if user is organizer
            if ($match->tournament->created_by !== $user->id) {
                return response()->json(['message' => 'Cannot delete this evidence'], 403);
            }
        }

        try {
            // Delete from Cloudinary if public_id exists
            if ($evidence->public_id && class_exists(Cloudinary::class) && config('cloudinary.cloud_url')) {
                Cloudinary::destroy($evidence->public_id);
            }

            $evidence->delete();

            return response()->json(['message' => 'Evidence deleted']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete evidence'], 500);
        }
    }
}
