<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayoutService;
use App\Models\PayoutMethod;
use App\Models\PayoutRequest;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Resources\PayoutMethodResource;
use App\Http\Resources\PayoutRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrganizerWalletController extends Controller
{
    public function __construct(
        protected PayoutService $payoutService
    ) {}

    /**
     * Get wallet overview.
     * GET /api/organizer/wallet
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $request->user()->organizerProfile;

        if (!$organizer) {
            return response()->json(['message' => 'Organizer profile not found'], 404);
        }

        $overview = $this->payoutService->getWalletOverview($organizer);

        return response()->json([
            'wallet' => new WalletResource($overview['wallet']),
            'pending_payouts' => $overview['pending_payouts'],
            'available_balance' => $overview['available_balance'],
            'formatted_available' => $overview['wallet']->currency . ' ' . number_format($overview['available_balance'] / 100, 2),
        ]);
    }

    /**
     * Get wallet transactions.
     * GET /api/organizer/wallet/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $organizer = $request->user()->organizerProfile;

        if (!$organizer) {
            return response()->json(['message' => 'Organizer profile not found'], 404);
        }

        $wallet = $organizer->getOrCreateWallet();

        $transactions = $this->payoutService->getTransactions($wallet, $request->input('per_page', 20));

        return response()->json([
            'transactions' => WalletTransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get payout methods.
     * GET /api/organizer/payout-methods
     */
    public function payoutMethods(Request $request): JsonResponse
    {
        $organizer = $request->user()->organizerProfile;

        if (!$organizer) {
            return response()->json(['message' => 'Organizer profile not found'], 404);
        }

        $methods = $organizer->payoutMethods()->orderBy('is_default', 'desc')->get();

        return response()->json([
            'payout_methods' => PayoutMethodResource::collection($methods),
        ]);
    }

    /**
     * Add payout method.
     * POST /api/organizer/payout-methods
     */
    public function addPayoutMethod(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:mpesa,airtel,mtn,bank',
            'account_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'bank_code' => 'nullable|string|max:20',
            'is_default' => 'boolean',
        ]);

        $organizer = $request->user()->organizerProfile;

        if (!$organizer) {
            return response()->json(['message' => 'Organizer profile not found'], 404);
        }

        try {
            $method = $this->payoutService->addPayoutMethod($organizer, $request->all());

            return response()->json([
                'message' => 'Payout method added successfully',
                'payout_method' => new PayoutMethodResource($method),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Set default payout method.
     * PUT /api/organizer/payout-methods/{method}/default
     */
    public function setDefaultMethod(Request $request, PayoutMethod $method): JsonResponse
    {
        $organizer = $request->user()->organizerProfile;

        if (!$organizer) {
            return response()->json(['message' => 'Organizer profile not found'], 404);
        }

        try {
            $this->payoutService->setDefaultPayoutMethod($organizer, $method);
            return response()->json(['message' => 'Default payment method updated']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Delete payout method.
     * DELETE /api/organizer/payout-methods/{method}
     */
    public function deletePayoutMethod(Request $request, PayoutMethod $method): JsonResponse
    {
        $organizer = $request->user()->organizerProfile;

        if (!$organizer) {
            return response()->json(['message' => 'Organizer profile not found'], 404);
        }

        if ($method->organizer_profile_id !== $organizer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->payoutService->deletePayoutMethod($method);
            return response()->json(['message' => 'Payout method deleted']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Request a payout.
     * POST /api/organizer/payouts
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|integer|min:100', // Minimum 1 KES
            'payout_method_id' => 'nullable|exists:payout_methods,id',
        ]);

        $organizer = $request->user()->organizerProfile;

        if (!$organizer) {
            return response()->json(['message' => 'Organizer profile not found'], 404);
        }

        try {
            $payout = $this->payoutService->requestPayout($organizer, $request->all());

            return response()->json([
                'message' => 'Payout request submitted for review',
                'payout' => new PayoutRequestResource($payout->load('payoutMethod')),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get payout requests.
     * GET /api/organizer/payouts
     */
    public function payoutRequests(Request $request): JsonResponse
    {
        $organizer = $request->user()->organizerProfile;

        if (!$organizer) {
            return response()->json(['message' => 'Organizer profile not found'], 404);
        }

        $status = $request->input('status');

        $payouts = $this->payoutService->getPayoutRequests($organizer, $status, $request->input('per_page', 20));

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
     * Cancel a payout request.
     * DELETE /api/organizer/payouts/{payout}
     */
    public function cancelPayout(Request $request, PayoutRequest $payout): JsonResponse
    {
        $organizer = $request->user()->organizerProfile;

        if (!$organizer) {
            return response()->json(['message' => 'Organizer profile not found'], 404);
        }

        if ($payout->organizer_profile_id !== $organizer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->payoutService->cancelPayoutRequest($payout);
            return response()->json(['message' => 'Payout request cancelled']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
