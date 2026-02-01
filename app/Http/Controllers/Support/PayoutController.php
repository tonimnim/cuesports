<?php

namespace App\Http\Controllers\Support;

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
     * Get payouts pending support review.
     * GET /api/support/payouts/pending
     */
    public function pending(Request $request): JsonResponse
    {
        $payouts = $this->approvalService->getPayoutsForSupportReview($request->input('per_page', 20));

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
     * GET /api/support/payouts
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
     * GET /api/support/payouts/{payout}
     */
    public function show(PayoutRequest $payout): JsonResponse
    {
        $payout->load(['organizerProfile.user', 'payoutMethod', 'reviewer', 'approver', 'rejector']);

        return response()->json([
            'payout' => new PayoutRequestResource($payout),
        ]);
    }

    /**
     * Confirm payout (support action).
     * POST /api/support/payouts/{payout}/confirm
     */
    public function confirm(Request $request, PayoutRequest $payout): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->approvalService->supportConfirm($payout, $request->user(), $request->input('notes'));

            return response()->json([
                'message' => 'Payout confirmed and sent for admin approval',
                'payout' => new PayoutRequestResource($payout->fresh(['payoutMethod', 'reviewer'])),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Reject payout (support action).
     * POST /api/support/payouts/{payout}/reject
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
}
