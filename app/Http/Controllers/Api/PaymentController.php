<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Tournament;
use App\Services\PaymentService;
use App\Services\PaystackService;
use App\Services\TournamentPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private PaystackService $paystackService,
        private TournamentPaymentService $tournamentPaymentService
    ) {}

    /**
     * Initiate payment for tournament entry.
     *
     * POST /api/payments/tournament/{tournament}
     */
    public function initiateTournamentPayment(Request $request, Tournament $tournament): JsonResponse
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return response()->json([
                'message' => 'You need a player profile to register for tournaments',
            ], 422);
        }

        $result = $this->paymentService->initiateTournamentPayment($tournament, $user);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'authorization_url' => $result['authorization_url'],
            'reference' => $result['payment']->reference,
        ]);
    }

    /**
     * Verify payment after callback.
     *
     * GET /api/payments/verify/{reference}
     */
    public function verify(string $reference): JsonResponse
    {
        $result = $this->paymentService->verifyPayment($reference);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'status' => 'failed',
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'status' => 'success',
            'payment' => new PaymentResource($result['payment']),
        ]);
    }

    /**
     * Paystack callback URL (redirect from Paystack).
     *
     * GET /api/payments/callback
     */
    public function callback(Request $request): JsonResponse
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return response()->json([
                'message' => 'No reference provided',
            ], 400);
        }

        $result = $this->paymentService->verifyPayment($reference);

        return response()->json([
            'message' => $result['message'],
            'success' => $result['success'],
            'payment' => isset($result['payment']) ? new PaymentResource($result['payment']) : null,
        ]);
    }

    /**
     * Paystack webhook handler.
     *
     * POST /api/payments/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        // Verify webhook signature
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        if (!$this->paystackService->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $request->all();
        $this->paymentService->processWebhook($event);

        return response()->json(['message' => 'Webhook processed']);
    }

    /**
     * Get user's payment history.
     *
     * GET /api/payments
     */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->with('payable')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'payments' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Get a single payment.
     *
     * GET /api/payments/{payment}
     */
    public function show(Payment $payment): JsonResponse
    {
        if ($payment->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'payment' => new PaymentResource($payment->load('payable')),
        ]);
    }

    /**
     * Initialize tournament entry fee payment (new flow).
     * Uses the new TournamentPaymentService for cleaner separation.
     *
     * POST /api/tournaments/{tournament}/pay
     */
    public function initializeTournamentEntryPayment(Tournament $tournament, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->playerProfile) {
            return response()->json([
                'message' => 'You must have a player profile to pay',
            ], 400);
        }

        try {
            $result = $this->tournamentPaymentService->initializeEntryFeePayment($tournament, $user);

            return response()->json([
                'message' => 'Payment initialized',
                'authorization_url' => $result['authorization_url'],
                'reference' => $result['reference'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Verify payment after redirect from Paystack.
     * Uses the new TournamentPaymentService for tournament entry verification.
     *
     * GET /api/payments/verify/{reference}
     */
    public function verifyTournamentPayment(string $reference): JsonResponse
    {
        try {
            $payment = $this->tournamentPaymentService->verifyAndProcessPayment($reference);

            return response()->json([
                'message' => 'Payment verified successfully',
                'payment' => new PaymentResource($payment),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payment status for a tournament participant.
     *
     * GET /api/tournaments/{tournament}/payment-status
     */
    public function tournamentPaymentStatus(Tournament $tournament, Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $this->tournamentPaymentService->getParticipantPaymentStatus($tournament, $user);

        return response()->json([
            'registered' => $status['registered'],
            'payment_required' => $status['payment_required'],
            'payment_status' => $status['payment_status'],
            'payment' => $status['payment'] ? new PaymentResource($status['payment']) : null,
        ]);
    }
}
