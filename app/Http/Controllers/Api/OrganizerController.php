<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizerProfileResource;
use App\Http\Resources\UserResource;
use App\Models\OrganizerProfile;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizerController extends Controller
{
    public function __construct(
        private CloudinaryService $cloudinaryService
    ) {}

    /**
     * Become an organizer (for existing players).
     */
    public function becomeOrganizer(Request $request): JsonResponse
    {
        $request->validate([
            'organization_name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // Must be a player first
        if (!$user->is_player || !$user->playerProfile) {
            return response()->json([
                'message' => 'You must complete player registration first',
            ], 422);
        }

        // Check if already an organizer
        if ($user->is_organizer && $user->organizerProfile) {
            return response()->json([
                'message' => 'You are already registered as an organizer',
                'organizer_profile' => new OrganizerProfileResource($user->organizerProfile),
            ], 422);
        }

        DB::transaction(function () use ($user, $request) {
            // Create organizer profile
            OrganizerProfile::create([
                'user_id' => $user->id,
                'organization_name' => $request->organization_name,
                'description' => $request->description,
            ]);

            // Update user role
            $user->is_organizer = true;
            $user->save();
        });

        $user->load(['organizerProfile', 'playerProfile.geographicUnit', 'country']);

        return response()->json([
            'message' => 'You are now registered as an organizer',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Get organizer profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $profile = $user->organizerProfile;
        $profile->load('tournaments');

        return response()->json([
            'organizer_profile' => new OrganizerProfileResource($profile),
            'stats' => [
                'tournaments_hosted' => $profile->tournaments_hosted,
                'active_tournaments' => $profile->tournaments()->where('status', 'active')->count(),
                'total_participants' => $profile->tournaments()->withCount('participants')->get()->sum('participants_count'),
            ],
        ]);
    }

    /**
     * Update organizer profile.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'organization_name' => ['sometimes', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $user->organizerProfile->update($request->only(['organization_name', 'description']));

        return response()->json([
            'message' => 'Organizer profile updated',
            'organizer_profile' => new OrganizerProfileResource($user->organizerProfile),
        ]);
    }

    /**
     * Upload organization logo.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100'],
        ]);

        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $profile = $user->organizerProfile;

        // Delete old logo if exists
        if ($profile->logo_url) {
            $this->cloudinaryService->delete($profile->logo_url);
        }

        // Upload new logo
        $logoUrl = $this->cloudinaryService->uploadOrganizationLogo(
            $request->file('logo'),
            $profile->id
        );

        if (!$logoUrl) {
            return response()->json([
                'message' => 'Failed to upload logo. Please try again.',
            ], 500);
        }

        $profile->logo_url = $logoUrl;
        $profile->save();

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'logo_url' => $logoUrl,
        ]);
    }

    /**
     * Delete organization logo.
     */
    public function deleteLogo(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $profile = $user->organizerProfile;

        if (!$profile->logo_url) {
            return response()->json([
                'message' => 'No logo to delete',
            ], 422);
        }

        $this->cloudinaryService->delete($profile->logo_url);

        $profile->logo_url = null;
        $profile->save();

        return response()->json([
            'message' => 'Logo deleted successfully',
        ]);
    }

    /**
     * Generate API credentials (for integrations).
     */
    public function generateApiCredentials(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $credentials = $user->organizerProfile->regenerateApiCredentials();

        return response()->json([
            'message' => 'API credentials generated. Save the secret - it won\'t be shown again!',
            'credentials' => $credentials,
        ]);
    }

    /**
     * Revoke API credentials.
     */
    public function revokeApiCredentials(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $user->organizerProfile->revokeApiCredentials();

        return response()->json([
            'message' => 'API credentials revoked',
        ]);
    }
}
