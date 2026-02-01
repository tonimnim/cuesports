<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Services\PayoutApprovalService;
use App\Http\Resources\PayoutRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PayoutController extends Controller
{
    public function __construct(
        protected PayoutApprovalService $approvalService
    ) {}

    /**
     * Get dashboard stats.
     * GET /api/admin/payouts/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->approvalService->getDashboardStats());
    }

    /**
     * Get payouts pending admin approval.
     * GET /api/admin/payouts/pending
     */
    public function pending(Request $request): JsonResponse
    {
        $payouts = $this->approvalService->getPayoutsForAdminApproval($request->input('per_page', 20));

        return response()->json([
            'payouts' => PayoutRequestResource::collection($payouts),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    /**
     * Get all payouts with optional status filter.
     * GET /api/admin/payouts
     */
    public function index(Request $request): JsonResponse
    {
        $payouts = $this->approvalService->getAllPayouts(
            $request->input('status'),
            $request->input('per_page', 20)
        );

        return response()->json([
            'payouts' => PayoutRequestResource::collection($payouts),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }

    /**
     * Get single payout details.
     * GET /api/admin/payouts/{payout}
     */
    public function show(PayoutRequest $payout): JsonResponse
    {
        $payout->load(['organizerProfile.user', 'organizerProfile.wallet', 'payoutMethod', 'reviewer', 'approver', 'rejector']);

        // Include organizer's tournament history
        $tournaments = $payout->organizerProfile->user->tournaments()
            ->select('id', 'name', 'status', 'entry_fee', 'participants_count', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'payout' => new PayoutRequestResource($payout),
            'organizer' => [
                'id' => $payout->organizerProfile->id,
                'name' => $payout->organizerProfile->organization_name,
                'wallet_balance' => $payout->organizerProfile->wallet?->formatted_balance,
                'total_earned' => $payout->organizerProfile->wallet?->total_earned,
                'total_withdrawn' => $payout->organizerProfile->wallet?->total_withdrawn,
            ],
            'recent_tournaments' => $tournaments,
        ]);
    }

    /**
     * Approve payout (admin action).
     * POST /api/admin/payouts/{payout}/approve
     */
    public function approve(Request $request, PayoutRequest $payout): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->approvalService->adminApprove($payout, $request->user(), $request->input('notes'));

            return response()->json([
                'message' => 'Payout approved and payment initiated',
                'payout' => new PayoutRequestResource($payout->fresh(['payoutMethod', 'approver'])),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Reject payout (admin action).
     * POST /api/admin/payouts/{payout}/reject
     */
    public function reject(Request $request, PayoutRequest $payout): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->approvalService->reject($payout, $request->user(), $request->input('reason'));

            return response()->json([
                'message' => 'Payout rejected',
                'payout' => new PayoutRequestResource($payout->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Manually retry payment processing.
     * POST /api/admin/payouts/{payout}/process
     */
    public function process(PayoutRequest $payout): JsonResponse
    {
        try {
            $this->approvalService->processPayment($payout);

            return response()->json([
                'message' => 'Payment processed successfully',
                'payout' => new PayoutRequestResource($payout->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
