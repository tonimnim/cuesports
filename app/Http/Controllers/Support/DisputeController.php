<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GameMatch as MatchModel;
use App\Models\PlayerMatchHistory;
use App\Services\MatchService;
use App\Services\RatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DisputeController extends Controller
{
    public function __construct(
        private MatchService $matchService,
        private RatingService $ratingService
    ) {}

    public function index(Request $request): Response
    {
        $disputes = MatchModel::disputed()
            ->with([
                'player1.playerProfile',
                'player2.playerProfile',
                'tournament',
                'submittedBy.playerProfile',
                'disputedBy.playerProfile',
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('tournament', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('player1.playerProfile', fn($q) =>
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('nickname', 'like', "%{$search}%")
                        )
                        ->orWhereHas('player2.playerProfile', fn($q) =>
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('nickname', 'like', "%{$search}%")
                        );
                });
            })
            ->when($request->priority === 'oldest', fn($q) => $q->orderBy('disputed_at', 'asc'))
            ->when($request->priority !== 'oldest', fn($q) => $q->orderBy('disputed_at', 'desc'))
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Support/Disputes/Index', [
            'disputes' => [
                'data' => $disputes->map(fn($match) => $this->formatDispute($match)),
                'current_page' => $disputes->currentPage(),
                'last_page' => $disputes->lastPage(),
                'per_page' => $disputes->perPage(),
                'total' => $disputes->total(),
            ],
            'filters' => $request->only(['search', 'priority']),
        ]);
    }

    public function show(Request $request, int $match): Response
    {
        $matchRecord = MatchModel::findOrFail($match);

        if ($matchRecord->status->value !== 'disputed') {
            abort(404, 'Match is not disputed');
        }

        $matchRecord->load([
            'player1.playerProfile',
            'player2.playerProfile',
            'tournament',
            'submittedBy.playerProfile',
            'disputedBy.playerProfile',
            'evidence.uploader.playerProfile',
        ]);

        // Log view activity
        ActivityLog::log(
            ActivityLog::ACTION_DISPUTE_VIEWED,
            ActivityLog::ENTITY_MATCH,
            $matchRecord->id,
            "Viewed dispute #{$matchRecord->id} for {$matchRecord->tournament->name}",
            ['tournament_id' => $matchRecord->tournament_id],
            $request
        );

        // Get player profiles for rating preview
        $player1Profile = $matchRecord->player1?->playerProfile;
        $player2Profile = $matchRecord->player2?->playerProfile;

        // Calculate rating preview for the submitted score
        $ratingPreview = null;
        if ($player1Profile && $player2Profile &&
            $matchRecord->player1_score !== null &&
            $matchRecord->player2_score !== null) {
            $ratingPreview = $this->ratingService->previewRatingChange(
                $player1Profile,
                $player2Profile,
                $matchRecord->player1_score,
                $matchRecord->player2_score
            );
        }

        // Get dispute history for both players
        $player1DisputeStats = $this->getPlayerDisputeStats($player1Profile?->id);
        $player2DisputeStats = $this->getPlayerDisputeStats($player2Profile?->id);

        // Get recent matches between these players (head-to-head)
        $headToHead = $this->getHeadToHead($player1Profile?->id, $player2Profile?->id);

        // Get recent matches for both players
        $player1RecentMatches = $player1Profile
            ? PlayerMatchHistory::forPlayer($player1Profile->id)
                ->excludingByes()
                ->recent(5)
                ->get()
                ->map(fn($m) => $this->formatMatchHistory($m))
            : [];

        $player2RecentMatches = $player2Profile
            ? PlayerMatchHistory::forPlayer($player2Profile->id)
                ->excludingByes()
                ->recent(5)
                ->get()
                ->map(fn($m) => $this->formatMatchHistory($m))
            : [];

        return Inertia::render('Support/Disputes/Show', [
            'dispute' => $this->formatDispute($matchRecord, true),
            'evidence' => $matchRecord->evidence->map(fn($e) => [
                'id' => $e->id,
                'file_url' => $e->file_url,
                'file_type' => $e->file_type,
                'thumbnail_url' => $e->thumbnail_url,
                'description' => $e->description,
                'evidence_type' => $e->evidence_type,
                'uploaded_at' => $e->uploaded_at->toISOString(),
                'uploader' => $e->uploader ? [
                    'id' => $e->uploader->id,
                    'name' => $e->uploader->playerProfile?->nickname
                        ?? "{$e->uploader->playerProfile?->first_name} {$e->uploader->playerProfile?->last_name}",
                ] : null,
            ]),
            'ratingPreview' => $ratingPreview,
            'tournament' => [
                'id' => $matchRecord->tournament->id,
                'name' => $matchRecord->tournament->name,
                'best_of' => $matchRecord->tournament->best_of,
            ],
            'player1DisputeStats' => $player1DisputeStats,
            'player2DisputeStats' => $player2DisputeStats,
            'headToHead' => $headToHead,
            'player1RecentMatches' => $player1RecentMatches,
            'player2RecentMatches' => $player2RecentMatches,
        ]);
    }

    public function resolve(Request $request, int $match): RedirectResponse
    {
        $matchRecord = MatchModel::findOrFail($match);

        $validated = $request->validate([
            'player1_score' => 'required|integer|min:0',
            'player2_score' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->matchService->resolveDispute(
                $matchRecord,
                $request->user(),
                $validated['player1_score'],
                $validated['player2_score'],
                $validated['notes'] ?? null
            );

            // Log resolution
            ActivityLog::log(
                ActivityLog::ACTION_DISPUTE_RESOLVED,
                ActivityLog::ENTITY_MATCH,
                $matchRecord->id,
                "Resolved dispute #{$matchRecord->id} with score {$validated['player1_score']}-{$validated['player2_score']}",
                [
                    'player1_score' => $validated['player1_score'],
                    'player2_score' => $validated['player2_score'],
                    'notes' => $validated['notes'] ?? null,
                ],
                $request
            );

            return redirect()
                ->route('support.disputes.index')
                ->with('success', 'Dispute resolved successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function getPlayerDisputeStats(?int $profileId): array
    {
        if (!$profileId) {
            return ['disputes_filed' => 0, 'disputes_against' => 0, 'disputes_won' => 0, 'disputes_lost' => 0];
        }

        // Count disputes filed by this player
        $disputesFiled = MatchModel::whereHas('disputedBy', function ($q) use ($profileId) {
            $q->where('player_profile_id', $profileId);
        })->count();

        // Count disputes filed against this player (where they submitted)
        $disputesAgainst = MatchModel::whereHas('submittedBy', function ($q) use ($profileId) {
            $q->where('player_profile_id', $profileId);
        })->where('status', 'disputed')->count();

        // Disputes won (resolved in their favor - they disputed and their score was different)
        $disputesWon = MatchModel::whereHas('disputedBy', function ($q) use ($profileId) {
            $q->where('player_profile_id', $profileId);
        })
            ->where('status', 'completed')
            ->whereNotNull('resolved_at')
            ->count();

        return [
            'disputes_filed' => $disputesFiled,
            'disputes_against' => $disputesAgainst,
            'disputes_won' => $disputesWon,
            'disputes_lost' => max(0, $disputesFiled - $disputesWon),
        ];
    }

    private function getHeadToHead(?int $player1ProfileId, ?int $player2ProfileId): array
    {
        if (!$player1ProfileId || !$player2ProfileId) {
            return ['total' => 0, 'player1_wins' => 0, 'player2_wins' => 0];
        }

        $matches = PlayerMatchHistory::forPlayer($player1ProfileId)
            ->where('opponent_profile_id', $player2ProfileId)
            ->excludingByes()
            ->get();

        return [
            'total' => $matches->count(),
            'player1_wins' => $matches->where('won', true)->count(),
            'player2_wins' => $matches->where('won', false)->count(),
        ];
    }

    private function formatMatchHistory($history): array
    {
        return [
            'id' => $history->id,
            'opponent_name' => $history->opponent_name,
            'won' => $history->won,
            'score' => $history->score,
            'rating_change' => $history->rating_change,
            'tournament_name' => $history->tournament_name,
            'round_name' => $history->round_name,
            'played_at' => $history->played_at?->toISOString(),
        ];
    }

    private function formatDispute(MatchModel $match, bool $detailed = false): array
    {
        $data = [
            'id' => $match->id,
            'tournament' => [
                'id' => $match->tournament->id,
                'name' => $match->tournament->name,
                'slug' => $match->tournament->slug,
            ],
            'player1' => $this->formatPlayer($match->player1, $detailed),
            'player2' => $this->formatPlayer($match->player2, $detailed),
            'player1_score' => $match->player1_score,
            'player2_score' => $match->player2_score,
            'status' => $match->status instanceof \BackedEnum ? $match->status->value : $match->status,
            'match_type' => $match->match_type instanceof \BackedEnum ? $match->match_type->value : $match->match_type,
            'round_number' => $match->round_number,
            'round_name' => $match->round_name,
            'submitted_by' => $this->formatPlayer($match->submittedBy),
            'submitted_at' => $match->submitted_at?->toISOString(),
            'disputed_by' => $this->formatPlayer($match->disputedBy),
            'disputed_at' => $match->disputed_at?->toISOString(),
            'dispute_reason' => $match->dispute_reason,
            'created_at' => $match->created_at->toISOString(),
        ];

        if ($detailed) {
            $data['scheduled_play_date'] = $match->scheduled_play_date?->toISOString();
            $data['played_at'] = $match->played_at?->toISOString();
        }

        return $data;
    }

    private function formatPlayer($participant, bool $detailed = false): ?array
    {
        if (!$participant || !$participant->playerProfile) {
            return null;
        }

        $profile = $participant->playerProfile;
        $data = [
            'id' => $participant->id,
            'player_profile' => [
                'id' => $profile->id,
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'nickname' => $profile->nickname,
                'photo_url' => $profile->photo_url,
                'rating' => $profile->rating,
                'rating_category' => $profile->rating_category instanceof \BackedEnum
                    ? $profile->rating_category->value
                    : $profile->rating_category,
            ],
        ];

        if ($detailed) {
            $data['player_profile']['total_matches'] = $profile->total_matches;
            $data['player_profile']['wins'] = $profile->wins;
            $data['player_profile']['losses'] = $profile->losses;
            $data['player_profile']['best_rating'] = $profile->best_rating;
        }

        return $data;
    }
}
