<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\GameMatch as MatchModel;
use App\Models\PlayerMatchHistory;
use App\Models\User;
use App\Models\UserNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with(['playerProfile', 'country'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhereHas('playerProfile', fn($q) =>
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('nickname', 'like', "%{$search}%")
                        );
                });
            })
            ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($request->role === 'player', fn($q) => $q->where('is_player', true))
            ->when($request->role === 'organizer', fn($q) => $q->where('is_organizer', true))
            ->when($request->role === 'support', fn($q) => $q->where('is_support', true))
            ->when($request->role === 'admin', fn($q) => $q->where('is_super_admin', true))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Support/Users/Index', [
            'users' => [
                'data' => $users->map(fn($user) => $this->formatUser($user)),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'filters' => $request->only(['search', 'status', 'role']),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $user->load(['playerProfile', 'country', 'organizerProfile', 'notes.creator']);

        // Log view activity
        ActivityLog::log(
            ActivityLog::ACTION_USER_VIEWED,
            ActivityLog::ENTITY_USER,
            $user->id,
            "Viewed user profile for {$user->email}",
            null,
            $request
        );

        // Get match history if player
        $matchHistory = [];
        $disputeHistory = [];
        $ratingHistory = [];

        if ($user->playerProfile) {
            $matchHistory = PlayerMatchHistory::forPlayer($user->playerProfile->id)
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

            // Get dispute history (matches this player was involved in that were disputed)
            $disputeHistory = MatchModel::where(function ($q) use ($user) {
                $q->whereHas('player1', fn($q) => $q->where('player_profile_id', $user->playerProfile->id))
                    ->orWhereHas('player2', fn($q) => $q->where('player_profile_id', $user->playerProfile->id));
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
                    'opponent_name' => $this->getOpponentName($m, $user->playerProfile->id),
                    'was_disputer' => $m->disputedBy?->player_profile_id === $user->playerProfile->id,
                    'status' => $m->status instanceof \BackedEnum ? $m->status->value : $m->status,
                    'dispute_reason' => $m->dispute_reason,
                    'resolution_notes' => $m->resolution_notes,
                    'disputed_at' => $m->disputed_at?->toISOString(),
                    'resolved_at' => $m->resolved_at?->toISOString(),
                ]);

            // Get rating history
            $ratingHistory = $user->playerProfile->ratingHistory()
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
        }

        // Get activity log for this user
        $activityLog = ActivityLog::forEntity(ActivityLog::ENTITY_USER, $user->id)
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

        return Inertia::render('Support/Users/Show', [
            'user' => $this->formatUser($user, true),
            'notes' => $user->notes->map(fn($n) => [
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

    public function addNote(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'type' => 'required|in:general,warning,ban_reason,verification',
            'is_pinned' => 'boolean',
        ]);

        UserNote::create([
            'user_id' => $user->id,
            'created_by' => $request->user()->id,
            'content' => $validated['content'],
            'type' => $validated['type'],
            'is_pinned' => $validated['is_pinned'] ?? false,
        ]);

        ActivityLog::log(
            ActivityLog::ACTION_NOTE_ADDED,
            ActivityLog::ENTITY_USER,
            $user->id,
            "Added {$validated['type']} note to user {$user->email}",
            ['type' => $validated['type']],
            $request
        );

        return back()->with('success', 'Note added successfully.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        if ($user->is_active) {
            return back()->with('error', 'User is already active.');
        }

        $user->update(['is_active' => true]);

        ActivityLog::log(
            ActivityLog::ACTION_USER_REACTIVATED,
            ActivityLog::ENTITY_USER,
            $user->id,
            "Reactivated user account for {$user->email}",
            null,
            $request
        );

        return back()->with('success', 'User account has been reactivated.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if (!$user->is_active) {
            return back()->with('error', 'User is already inactive.');
        }

        // Prevent deactivating super admins
        if ($user->is_super_admin) {
            return back()->with('error', 'Cannot deactivate super admin accounts.');
        }

        // Prevent self-deactivation
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Cannot deactivate your own account.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user->update(['is_active' => false]);

        // Revoke all tokens
        $user->tokens()->delete();

        // Add ban reason note if provided
        if (!empty($validated['reason'])) {
            UserNote::create([
                'user_id' => $user->id,
                'created_by' => $request->user()->id,
                'content' => $validated['reason'],
                'type' => UserNote::TYPE_BAN_REASON,
                'is_pinned' => true,
            ]);
        }

        ActivityLog::log(
            ActivityLog::ACTION_USER_DEACTIVATED,
            ActivityLog::ENTITY_USER,
            $user->id,
            "Deactivated user account for {$user->email}",
            ['reason' => $validated['reason'] ?? null],
            $request
        );

        return back()->with('success', 'User account has been deactivated.');
    }

    private function getOpponentName(MatchModel $match, int $profileId): string
    {
        if ($match->player1?->player_profile_id === $profileId) {
            $opponent = $match->player2?->playerProfile;
        } else {
            $opponent = $match->player1?->playerProfile;
        }

        if (!$opponent) {
            return 'Unknown';
        }

        return $opponent->nickname ?? "{$opponent->first_name} {$opponent->last_name}";
    }

    private function formatUser(User $user, bool $detailed = false): array
    {
        $data = [
            'id' => $user->id,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at->toISOString(),
            'roles' => [
                'is_super_admin' => $user->is_super_admin,
                'is_support' => $user->is_support,
                'is_player' => $user->is_player,
                'is_organizer' => $user->is_organizer,
            ],
            'player_profile' => $user->playerProfile ? [
                'id' => $user->playerProfile->id,
                'first_name' => $user->playerProfile->first_name,
                'last_name' => $user->playerProfile->last_name,
                'nickname' => $user->playerProfile->nickname,
                'photo_url' => $user->playerProfile->photo_url,
                'rating' => $user->playerProfile->rating,
                'rating_category' => $user->playerProfile->rating_category instanceof \BackedEnum
                    ? $user->playerProfile->rating_category->value
                    : $user->playerProfile->rating_category,
            ] : null,
            'country' => $user->country ? [
                'id' => $user->country->id,
                'name' => $user->country->name,
            ] : null,
        ];

        if ($detailed) {
            $data['email_verified_at'] = $user->email_verified_at?->toISOString();
            $data['phone_verified_at'] = $user->phone_verified_at?->toISOString();
            $data['updated_at'] = $user->updated_at->toISOString();

            if ($user->playerProfile) {
                $data['player_profile']['total_matches'] = $user->playerProfile->total_matches;
                $data['player_profile']['wins'] = $user->playerProfile->wins;
                $data['player_profile']['losses'] = $user->playerProfile->losses;
                $data['player_profile']['best_rating'] = $user->playerProfile->best_rating;
                $data['player_profile']['tournaments_played'] = $user->playerProfile->tournaments_played;
                $data['player_profile']['tournaments_won'] = $user->playerProfile->tournaments_won;
                $data['player_profile']['lifetime_frames_won'] = $user->playerProfile->lifetime_frames_won;
                $data['player_profile']['lifetime_frames_lost'] = $user->playerProfile->lifetime_frames_lost;
            }

            if ($user->organizerProfile) {
                $data['organizer_profile'] = [
                    'id' => $user->organizerProfile->id,
                    'organization_name' => $user->organizerProfile->organization_name,
                    'description' => $user->organizerProfile->description,
                    'logo_url' => $user->organizerProfile->logo_url,
                    'tournaments_hosted' => $user->organizerProfile->tournaments_hosted,
                    'is_active' => $user->organizerProfile->is_active,
                ];
            }

            // Count warnings
            $data['warning_count'] = $user->notes()->where('type', 'warning')->count();
        }

        return $data;
    }
}
