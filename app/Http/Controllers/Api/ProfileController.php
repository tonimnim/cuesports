<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdatePlayerProfileRequest;
use App\Http\Requests\Profile\UploadPhotoRequest;
use App\Http\Resources\PlayerProfileResource;
use App\Http\Resources\UserResource;
use App\Services\CloudinaryService;
use App\Services\PlayerStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(
        private CloudinaryService $cloudinaryService,
        private PlayerStatsService $playerStatsService
    ) {}

    /**
     * Get the authenticated user's profile with full stats.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['country', 'playerProfile.geographicUnit', 'organizerProfile']);

        $data = [
            'user' => new UserResource($user),
        ];

        // Add extended stats for player
        if ($user->playerProfile) {
            $profile = $user->playerProfile;

            $data['stats'] = $this->playerStatsService->getPlayerStats($profile);
            $data['streak'] = $this->playerStatsService->getStreakInfo($profile);
            $data['recent_matches'] = $this->playerStatsService->getRecentMatches($profile, 5);

            // Get rank in player's location
            if ($profile->geographicUnit) {
                $data['rank'] = [
                    'local' => $this->playerStatsService->getPlayerRank(
                        $profile,
                        $profile->geographicUnit
                    ),
                    'national' => $profile->getCountry()
                        ? $this->playerStatsService->getPlayerRank($profile, $profile->getCountry())
                        : null,
                ];
            }
        }

        return response()->json($data);
    }

    /**
     * Get the authenticated user's profile settings (for editing).
     */
    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['country', 'playerProfile.geographicUnit', 'organizerProfile']);

        // Get available locations for the dropdown
        $locations = \App\Models\GeographicUnit::whereIn('level', ['city', 'region'])
            ->orderBy('name')
            ->get()
            ->map(fn($loc) => [
                'id' => $loc->id,
                'name' => $loc->name,
                'full_path' => $loc->getFullPath(),
            ]);

        return response()->json([
            'user' => new UserResource($user),
            'locations' => $locations,
        ]);
    }

    /**
     * Update the authenticated user's player profile.
     */
    public function update(UpdatePlayerProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return response()->json([
                'message' => 'Player profile not found',
            ], 404);
        }

        $validated = $request->validated();

        $user->playerProfile->update($validated);
        $user->load(['country', 'playerProfile.geographicUnit']);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Upload a profile photo.
     */
    public function uploadPhoto(UploadPhotoRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return response()->json([
                'message' => 'Player profile not found',
            ], 404);
        }

        $profile = $user->playerProfile;

        // Delete old photo if exists
        if ($profile->photo_url) {
            $this->cloudinaryService->delete($profile->photo_url);
        }

        // Upload new photo
        $photoUrl = $this->cloudinaryService->uploadProfilePhoto(
            $request->file('photo'),
            $user->id
        );

        if (!$photoUrl) {
            return response()->json([
                'message' => 'Failed to upload photo. Please try again.',
            ], 500);
        }

        $profile->photo_url = $photoUrl;
        $profile->save();

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'photo_url' => $photoUrl,
        ]);
    }

    /**
     * Delete the profile photo.
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return response()->json([
                'message' => 'Player profile not found',
            ], 404);
        }

        $profile = $user->playerProfile;

        if (!$profile->photo_url) {
            return response()->json([
                'message' => 'No photo to delete',
            ], 422);
        }

        // Delete from Cloudinary
        $this->cloudinaryService->delete($profile->photo_url);

        $profile->photo_url = null;
        $profile->save();

        return response()->json([
            'message' => 'Photo deleted successfully',
        ]);
    }

    /**
     * Change the user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->password = Hash::make($validated['password']);
        $user->save();

        // Revoke all other tokens
        $currentTokenId = $user->token()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Get the user's rating history.
     */
    public function ratingHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return response()->json([
                'message' => 'Player profile not found',
            ], 404);
        }

        $history = $this->playerStatsService->getRatingHistory(
            $user->playerProfile,
            $request->input('start_date') ? \Carbon\Carbon::parse($request->input('start_date')) : null,
            $request->input('end_date') ? \Carbon\Carbon::parse($request->input('end_date')) : null
        );

        return response()->json([
            'rating_history' => $history->map(fn($entry) => [
                'old_rating' => $entry->old_rating,
                'new_rating' => $entry->new_rating,
                'change' => $entry->change,
                'formatted_change' => $entry->getFormattedChange(),
                'reason' => $entry->reason,
                'reason_label' => $entry->getReasonLabel(),
                'created_at' => $entry->created_at->toISOString(),
            ]),
        ]);
    }

    /**
     * Get the user's match history.
     */
    public function matchHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return response()->json([
                'message' => 'Player profile not found',
            ], 404);
        }

        $limit = min($request->input('limit', 20), 100);
        $offset = $request->input('offset', 0);

        $matches = $user->playerProfile->matchHistory()
            ->with(['tournament', 'opponentProfile'])
            ->orderBy('played_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $profile = $user->playerProfile;

        return response()->json([
            'data' => $matches->map(fn($match) => [
                'id' => $match->id,
                'tournament' => [
                    'id' => $match->tournament_id,
                    'name' => $match->tournament_name,
                    'type' => $match->tournament_type,
                ],
                'opponent' => [
                    'id' => $match->opponent_profile_id,
                    'name' => $match->opponent_name,
                    'photo_url' => $match->opponentProfile?->photo_url,
                    'rating_at_time' => $match->opponent_rating_at_time,
                ],
                'result' => $match->won ? 'win' : 'loss',
                'won' => $match->won,
                'score' => $match->score,
                'stage' => $match->round_name,
                'rating_change' => $match->rating_change,
                'played_at' => $match->played_at->toISOString(),
            ]),
            'stats' => [
                'total_matches' => $profile->total_matches,
                'wins' => $profile->wins,
                'losses' => $profile->total_matches - $profile->wins,
                'win_rate' => $profile->total_matches > 0
                    ? round(($profile->wins / $profile->total_matches) * 100)
                    : 0,
            ],
            'meta' => [
                'current_page' => (int) floor($offset / $limit) + 1,
                'per_page' => $limit,
                'total' => $profile->total_matches,
                'last_page' => (int) ceil($profile->total_matches / $limit),
            ],
        ]);
    }

    /**
     * Get the user's tournament history.
     */
    public function tournamentHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return response()->json([
                'message' => 'Player profile not found',
            ], 404);
        }

        $perPage = min($request->input('per_page', 15), 50);
        $filter = $request->input('filter'); // all, won, active

        $query = $user->playerProfile->tournamentParticipations()
            ->with(['tournament.organizer', 'tournament.geographicScope'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($filter === 'won') {
            $query->where('final_position', 1);
        } elseif ($filter === 'active') {
            $query->whereHas('tournament', function ($q) {
                $q->where('status', 'active');
            });
        }

        $participations = $query->paginate($perPage);

        // Calculate stats
        $allParticipations = $user->playerProfile->tournamentParticipations()->get();
        $stats = [
            'total_played' => $allParticipations->count(),
            'total_won' => $allParticipations->where('final_position', 1)->count(),
            'best_placement' => $allParticipations->whereNotNull('final_position')->min('final_position'),
        ];

        return response()->json([
            'data' => $participations->map(function ($p) {
                $tournament = $p->tournament;
                return [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'status' => [
                        'value' => $tournament->status->value,
                        'label' => $tournament->status->label(),
                    ],
                    'venue' => [
                        'name' => $tournament->venue_name,
                        'address' => $tournament->venue_address,
                    ],
                    'dates' => [
                        'starts_at' => $tournament->starts_at?->toISOString(),
                        'ends_at' => $tournament->ends_at?->toISOString(),
                    ],
                    'participant_count' => $tournament->participants()->count(),
                    'my_result' => [
                        'placement' => $p->final_position,
                        'matches_played' => $p->matches_played ?? 0,
                        'matches_won' => $p->matches_won ?? 0,
                        'rating_change' => $p->rating_change ?? 0,
                    ],
                    'format' => $tournament->format->value,
                ];
            }),
            'meta' => [
                'current_page' => $participations->currentPage(),
                'last_page' => $participations->lastPage(),
                'total' => $participations->total(),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Deactivate the user's account.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', function ($attr, $value, $fail) use ($request) {
                if (!Hash::check($value, $request->user()->password)) {
                    $fail('Password is incorrect.');
                }
            }],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $user->is_active = false;
        $user->save();

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Your account has been deactivated. You can contact support to reactivate it.',
        ]);
    }
}
