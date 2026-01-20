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

        return response()->json([
            'matches' => $matches->map(fn($match) => [
                'id' => $match->id,
                'tournament' => [
                    'id' => $match->tournament_id,
                    'name' => $match->tournament_name,
                    'type' => $match->tournament_type,
                ],
                'opponent' => [
                    'id' => $match->opponent_profile_id,
                    'name' => $match->opponent_name,
                    'rating_at_time' => $match->opponent_rating_at_time,
                ],
                'result' => $match->result,
                'won' => $match->won,
                'score' => $match->score,
                'match_type' => $match->match_type,
                'round' => [
                    'number' => $match->round_number,
                    'name' => $match->round_name,
                ],
                'rating' => [
                    'before' => $match->rating_before,
                    'after' => $match->rating_after,
                    'change' => $match->rating_change,
                    'formatted_change' => $match->rating_change_formatted,
                ],
                'played_at' => $match->played_at->toISOString(),
            ]),
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => $matches->count() === $limit,
            ],
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
