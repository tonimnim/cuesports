<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GameMatch as MatchModel;
use App\Models\PlayerMatchHistory;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Models\UserNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $players = PlayerProfile::query()
            ->with(['user.country'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('nickname', 'like', "%{$search}%")
                        ->orWhereHas('user', fn($q) =>
                            $q->where('email', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%")
                        );
                });
            })
            ->when($request->status === 'active', fn($q) => $q->whereHas('user', fn($u) => $u->where('is_active', true)))
            ->when($request->status === 'inactive', fn($q) => $q->whereHas('user', fn($u) => $u->where('is_active', false)))
            ->when($request->rating, function ($q, $rating) {
                if ($rating === 'pro') $q->where('rating', '>=', 1800);
                elseif ($rating === 'advanced') $q->whereBetween('rating', [1500, 1799]);
                elseif ($rating === 'intermediate') $q->whereBetween('rating', [1200, 1499]);
                elseif ($rating === 'beginner') $q->where('rating', '<', 1200);
            })
            ->orderBy('rating', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Support/Players/Index', [
            'players' => [
                'data' => $players->map(fn($player) => $this->formatPlayer($player)),
                'current_page' => $players->currentPage(),
                'last_page' => $players->lastPage(),
                'per_page' => $players->perPage(),
                'total' => $players->total(),
            ],
            'filters' => $request->only(['search', 'status', 'rating']),
        ]);
    }

    public function show(Request $request, PlayerProfile $player): Response
    {
        $player->load(['user.country', 'user.notes.creator']);

        ActivityLog::log(
            ActivityLog::ACTION_USER_VIEWED,
            ActivityLog::ENTITY_USER,
            $player->user_id,
            "Viewed player profile: {$player->first_name} {$player->last_name}",
            null,
            $request
        );

        // Get match history
        $matchHistory = PlayerMatchHistory::forPlayer($player->id)
            ->excludingByes()
            ->recent(20)
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'opponent_name' => $m->opponent_name,
                'won' => $m->won,
                'score' => $m->score,
                'rating_before' => $m->rating_before,
                'rating_after' => $m->rating_after,
                'rating_change' => $m->rating_change,
                'tournament_name' => $m->tournament_name,
                'match_type' => $m->match_type,
                'round_name' => $m->round_name,
                'played_at' => $m->played_at?->toISOString(),
            ]);

        // Get dispute history
        $disputeHistory = MatchModel::where(function ($q) use ($player) {
            $q->whereHas('player1', fn($q) => $q->where('player_profile_id', $player->id))
                ->orWhereHas('player2', fn($q) => $q->where('player_profile_id', $player->id));
        })
            ->where(function ($q) {
                $q->where('status', 'disputed')
                    ->orWhereNotNull('resolved_at');
            })
            ->with(['tournament', 'player1.playerProfile', 'player2.playerProfile'])
            ->orderBy('disputed_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'tournament_name' => $m->tournament->name,
                'opponent_name' => $this->getOpponentName($m, $player->id),
                'was_disputer' => $m->disputedBy?->player_profile_id === $player->id,
                'status' => $m->status instanceof \BackedEnum ? $m->status->value : $m->status,
                'dispute_reason' => $m->dispute_reason,
                'resolution_notes' => $m->resolution_notes,
                'disputed_at' => $m->disputed_at?->toISOString(),
                'resolved_at' => $m->resolved_at?->toISOString(),
            ]);

        // Get rating history
        $ratingHistory = $player->ratingHistory()
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'old_rating' => $r->old_rating,
                'new_rating' => $r->new_rating,
                'change' => $r->change,
                'reason' => $r->reason,
                'created_at' => $r->created_at->toISOString(),
            ]);

        // Get activity log
        $activityLog = ActivityLog::forEntity(ActivityLog::ENTITY_USER, $player->user_id)
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

        return Inertia::render('Support/Players/Show', [
            'player' => $this->formatPlayer($player, true),
            'notes' => $player->user->notes->map(fn($n) => [
                'id' => $n->id,
                'content' => $n->content,
                'type' => $n->type,
                'type_label' => $n->getTypeLabel(),
                'is_pinned' => $n->is_pinned,
                'created_by' => $n->creator ? [
                    'id' => $n->creator->id,
                    'email' => $n->creator->email,
                ] : null,
                'created_at' => $n->created_at->toISOString(),
            ]),
            'matchHistory' => $matchHistory,
            'disputeHistory' => $disputeHistory,
            'ratingHistory' => $ratingHistory,
            'activityLog' => $activityLog,
        ]);
    }

    public function addNote(Request $request, PlayerProfile $player): RedirectResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'type' => 'required|in:general,warning,ban_reason,verification',
            'is_pinned' => 'boolean',
        ]);

        UserNote::create([
            'user_id' => $player->user_id,
            'created_by' => $request->user()->id,
            'content' => $validated['content'],
            'type' => $validated['type'],
            'is_pinned' => $validated['is_pinned'] ?? false,
        ]);

        ActivityLog::log(
            ActivityLog::ACTION_NOTE_ADDED,
            ActivityLog::ENTITY_USER,
            $player->user_id,
            "Added {$validated['type']} note to player {$player->first_name} {$player->last_name}",
            ['type' => $validated['type']],
            $request
        );

        return back()->with('success', 'Note added successfully.');
    }

    public function reactivate(Request $request, PlayerProfile $player): RedirectResponse
    {
        if ($player->user->is_active) {
            return back()->with('error', 'Player is already active.');
        }

        $player->user->update(['is_active' => true]);

        ActivityLog::log(
            ActivityLog::ACTION_USER_REACTIVATED,
            ActivityLog::ENTITY_USER,
            $player->user_id,
            "Reactivated player account: {$player->first_name} {$player->last_name}",
            null,
            $request
        );

        return back()->with('success', 'Player account has been reactivated.');
    }

    public function deactivate(Request $request, PlayerProfile $player): RedirectResponse
    {
        if (!$player->user->is_active) {
            return back()->with('error', 'Player is already inactive.');
        }

        if ($player->user->is_super_admin) {
            return back()->with('error', 'Cannot deactivate admin accounts.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $player->user->update(['is_active' => false]);
        $player->user->tokens()->delete();

        if (!empty($validated['reason'])) {
            UserNote::create([
                'user_id' => $player->user_id,
                'created_by' => $request->user()->id,
                'content' => $validated['reason'],
                'type' => UserNote::TYPE_BAN_REASON,
                'is_pinned' => true,
            ]);
        }

        ActivityLog::log(
            ActivityLog::ACTION_USER_DEACTIVATED,
            ActivityLog::ENTITY_USER,
            $player->user_id,
            "Deactivated player account: {$player->first_name} {$player->last_name}",
            ['reason' => $validated['reason'] ?? null],
            $request
        );

        return back()->with('success', 'Player account has been deactivated.');
    }

    private function getOpponentName(MatchModel $match, int $profileId): string
    {
        if ($match->player1?->player_profile_id === $profileId) {
            $opponent = $match->player2?->playerProfile;
        } else {
            $opponent = $match->player1?->playerProfile;
        }

        if (!$opponent) return 'Unknown';

        return $opponent->nickname ?? "{$opponent->first_name} {$opponent->last_name}";
    }

    private function formatPlayer(PlayerProfile $player, bool $detailed = false): array
    {
        $data = [
            'id' => $player->id,
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'nickname' => $player->nickname,
            'photo_url' => $player->photo_url,
            'rating' => $player->rating,
            'rating_category' => $player->rating_category instanceof \BackedEnum
                ? $player->rating_category->value
                : $player->rating_category,
            'total_matches' => $player->total_matches,
            'wins' => $player->wins,
            'losses' => $player->losses,
            'created_at' => $player->created_at->toISOString(),
            'user' => [
                'id' => $player->user->id,
                'email' => $player->user->email,
                'phone_number' => $player->user->phone_number,
                'is_active' => $player->user->is_active,
                'country' => $player->user->country ? [
                    'id' => $player->user->country->id,
                    'name' => $player->user->country->name,
                ] : null,
            ],
        ];

        if ($detailed) {
            $data['best_rating'] = $player->best_rating;
            $data['tournaments_played'] = $player->tournaments_played;
            $data['tournaments_won'] = $player->tournaments_won;
            $data['lifetime_frames_won'] = $player->lifetime_frames_won;
            $data['lifetime_frames_lost'] = $player->lifetime_frames_lost;
            $data['user']['email_verified_at'] = $player->user->email_verified_at?->toISOString();
            $data['user']['created_at'] = $player->user->created_at->toISOString();
            $data['warning_count'] = $player->user->notes()->where('type', 'warning')->count();
        }

        return $data;
    }
}
