<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizerProfileResource;
use App\Http\Resources\UserResource;
use App\Models\OrganizerPayout;
use App\Models\OrganizerProfile;
use App\Models\Payment;
use App\Models\Tournament;
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

    /**
     * Get financial overview for the organizer.
     *
     * GET /api/organizer/finances
     */
    public function finances(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $profile = $user->organizerProfile;

        // Get tournament IDs for this organizer
        $tournamentIds = Tournament::where('created_by', $user->id)->pluck('id');

        // Calculate earnings from tournament entry fees
        $totalEarnings = Payment::whereIn('payable_id', $tournamentIds)
            ->where('payable_type', Tournament::class)
            ->where('status', 'success')
            ->sum('amount');

        // Platform fee (10%)
        $platformFee = (int) ($totalEarnings * 0.10);
        $netEarnings = $totalEarnings - $platformFee;

        // Pending payouts
        $pendingPayouts = $profile->payouts()->pending()->sum('net_amount');

        // Completed payouts
        $completedPayouts = $profile->payouts()->successful()->sum('net_amount');

        // Monthly earnings (last 12 months)
        $monthlyEarnings = Payment::whereIn('payable_id', $tournamentIds)
            ->where('payable_type', Tournament::class)
            ->where('status', 'success')
            ->where('paid_at', '>=', now()->subMonths(12))
            ->selectRaw("DATE_TRUNC('month', paid_at) as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn($item) => [
                'month' => $item->month,
                'total' => $item->total,
                'net' => (int) ($item->total * 0.90), // After 10% platform fee
            ]);

        return response()->json([
            'balance' => [
                'available' => $profile->available_balance,
                'pending' => $profile->pending_balance,
                'formatted_available' => 'KES ' . number_format($profile->available_balance / 100, 2),
                'formatted_pending' => 'KES ' . number_format($profile->pending_balance / 100, 2),
            ],
            'earnings' => [
                'total_gross' => $totalEarnings,
                'platform_fee' => $platformFee,
                'total_net' => $netEarnings,
                'formatted_gross' => 'KES ' . number_format($totalEarnings / 100, 2),
                'formatted_net' => 'KES ' . number_format($netEarnings / 100, 2),
            ],
            'payouts' => [
                'pending' => $pendingPayouts,
                'completed' => $completedPayouts,
                'formatted_pending' => 'KES ' . number_format($pendingPayouts / 100, 2),
                'formatted_completed' => 'KES ' . number_format($completedPayouts / 100, 2),
            ],
            'monthly_earnings' => $monthlyEarnings,
        ]);
    }

    /**
     * Get earnings history for the organizer.
     *
     * GET /api/organizer/earnings
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        // Get tournament IDs for this organizer
        $tournamentIds = Tournament::where('created_by', $user->id)->pluck('id');

        $earnings = Payment::whereIn('payable_id', $tournamentIds)
            ->where('payable_type', Tournament::class)
            ->where('status', 'success')
            ->with('payable:id,name,slug')
            ->orderByDesc('paid_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'earnings' => [
                'data' => $earnings->map(fn($payment) => [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                    'platform_fee' => (int) ($payment->amount * 0.10),
                    'net_amount' => (int) ($payment->amount * 0.90),
                    'formatted_amount' => $payment->formatted_amount,
                    'formatted_net' => 'KES ' . number_format(($payment->amount * 0.90) / 100, 2),
                    'tournament' => $payment->payable ? [
                        'id' => $payment->payable->id,
                        'name' => $payment->payable->name,
                        'slug' => $payment->payable->slug,
                    ] : null,
                    'player' => $payment->user ? [
                        'id' => $payment->user->id,
                        'name' => $payment->user->playerProfile?->display_name ?? 'Unknown',
                    ] : null,
                    'paid_at' => $payment->paid_at?->toISOString(),
                ]),
                'current_page' => $earnings->currentPage(),
                'last_page' => $earnings->lastPage(),
                'per_page' => $earnings->perPage(),
                'total' => $earnings->total(),
            ],
        ]);
    }

    /**
     * Get payout history for the organizer.
     *
     * GET /api/organizer/payouts
     */
    public function payouts(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $payouts = $user->organizerProfile->payouts()
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'payouts' => [
                'data' => $payouts->map(fn($payout) => [
                    'id' => $payout->id,
                    'reference' => $payout->reference,
                    'amount' => $payout->amount,
                    'platform_fee' => $payout->platform_fee,
                    'net_amount' => $payout->net_amount,
                    'formatted_amount' => $payout->formatted_amount,
                    'formatted_net' => $payout->formatted_net_amount,
                    'status' => $payout->status,
                    'status_label' => $payout->status_label,
                    'bank_name' => $payout->bank_name,
                    'account_number' => $payout->account_number ? '****' . substr($payout->account_number, -4) : null,
                    'account_name' => $payout->account_name,
                    'tournament_count' => $payout->getTournamentCount(),
                    'failure_reason' => $payout->failure_reason,
                    'requested_at' => $payout->requested_at?->toISOString(),
                    'completed_at' => $payout->completed_at?->toISOString(),
                ]),
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    /**
     * Request a payout.
     *
     * POST /api/organizer/payouts/request
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'integer', 'min:100000'], // Minimum 1000 KES (in cents)
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $profile = $user->organizerProfile;
        $amount = $request->amount;

        // Check available balance
        if ($profile->available_balance < $amount) {
            return response()->json([
                'message' => 'Insufficient balance',
                'available_balance' => $profile->available_balance,
                'requested_amount' => $amount,
            ], 422);
        }

        // Check for pending payouts
        $hasPendingPayout = $profile->payouts()
            ->whereIn('status', [OrganizerPayout::STATUS_PENDING, OrganizerPayout::STATUS_PROCESSING])
            ->exists();

        if ($hasPendingPayout) {
            return response()->json([
                'message' => 'You have a pending payout request. Please wait for it to complete.',
            ], 422);
        }

        // Calculate platform fee (10%)
        $platformFee = (int) ($amount * 0.10);
        $netAmount = $amount - $platformFee;

        // Create payout request
        $payout = DB::transaction(function () use ($profile, $amount, $platformFee, $netAmount, $request) {
            // Deduct from available balance
            $profile->decrement('available_balance', $amount);
            $profile->increment('pending_balance', $netAmount);

            // Get tournament IDs with earnings
            $tournamentIds = Tournament::where('created_by', $profile->user_id)
                ->whereHas('payments', fn($q) => $q->where('status', 'success'))
                ->pluck('id')
                ->toArray();

            return OrganizerPayout::create([
                'organizer_profile_id' => $profile->id,
                'amount' => $amount,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
                'currency' => 'KES',
                'status' => OrganizerPayout::STATUS_PENDING,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'account_name' => $request->account_name,
                'tournaments' => $tournamentIds,
            ]);
        });

        return response()->json([
            'message' => 'Payout request submitted successfully',
            'payout' => [
                'id' => $payout->id,
                'reference' => $payout->reference,
                'amount' => $payout->amount,
                'net_amount' => $payout->net_amount,
                'formatted_net' => $payout->formatted_net_amount,
                'status' => $payout->status,
                'bank_name' => $payout->bank_name,
                'account_name' => $payout->account_name,
            ],
        ], 201);
    }

    /**
     * Get overall organizer statistics.
     *
     * GET /api/organizer/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->organizerProfile) {
            return response()->json([
                'message' => 'You are not registered as an organizer',
            ], 404);
        }

        $profile = $user->organizerProfile;

        // Get tournaments
        $tournaments = Tournament::where('created_by', $user->id);
        $tournamentIds = $tournaments->pluck('id');

        // Tournament stats
        $tournamentStats = [
            'total' => $tournaments->count(),
            'draft' => (clone $tournaments)->where('status', 'draft')->count(),
            'registration' => (clone $tournaments)->where('status', 'registration')->count(),
            'active' => (clone $tournaments)->where('status', 'active')->count(),
            'completed' => (clone $tournaments)->where('status', 'completed')->count(),
            'cancelled' => (clone $tournaments)->where('status', 'cancelled')->count(),
        ];

        // Participant stats
        $totalParticipants = DB::table('tournament_participants')
            ->whereIn('tournament_id', $tournamentIds)
            ->count();

        $uniqueParticipants = DB::table('tournament_participants')
            ->whereIn('tournament_id', $tournamentIds)
            ->distinct('player_profile_id')
            ->count('player_profile_id');

        // Match stats
        $matchStats = DB::table('matches')
            ->whereIn('tournament_id', $tournamentIds)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed,
                SUM(CASE WHEN match_type = 'bye' THEN 1 ELSE 0 END) as byes
            ")
            ->first();

        // Revenue stats
        $revenueStats = Payment::whereIn('payable_id', $tournamentIds)
            ->where('payable_type', Tournament::class)
            ->where('status', 'success')
            ->selectRaw('SUM(amount) as total, COUNT(*) as count')
            ->first();

        return response()->json([
            'tournaments' => $tournamentStats,
            'participants' => [
                'total' => $totalParticipants,
                'unique' => $uniqueParticipants,
                'average_per_tournament' => $tournamentStats['total'] > 0
                    ? round($totalParticipants / $tournamentStats['total'], 1)
                    : 0,
            ],
            'matches' => [
                'total' => $matchStats->total ?? 0,
                'completed' => $matchStats->completed ?? 0,
                'disputed' => $matchStats->disputed ?? 0,
                'byes' => $matchStats->byes ?? 0,
                'completion_rate' => $matchStats->total > 0
                    ? round(($matchStats->completed / $matchStats->total) * 100, 1)
                    : 0,
            ],
            'revenue' => [
                'total_gross' => $revenueStats->total ?? 0,
                'total_net' => (int) (($revenueStats->total ?? 0) * 0.90),
                'transaction_count' => $revenueStats->count ?? 0,
                'formatted_gross' => 'KES ' . number_format(($revenueStats->total ?? 0) / 100, 2),
                'formatted_net' => 'KES ' . number_format((($revenueStats->total ?? 0) * 0.90) / 100, 2),
            ],
            'balance' => [
                'available' => $profile->available_balance,
                'pending' => $profile->pending_balance,
                'total_withdrawn' => $profile->total_withdrawn,
            ],
        ]);
    }
}
