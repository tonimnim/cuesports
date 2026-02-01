<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Services\PaystackService;
use App\Services\TournamentPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private PaystackService $paystackService,
        private TournamentPaymentService $tournamentPaymentService
    ) {}

    /**
     * Handle Paystack webhook events.
     *
     * POST /api/webhooks/paystack
     */
    public function handle(Request $request): JsonResponse
    {
        // Verify webhook signature
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        if (!$this->paystackService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Invalid Paystack webhook signature', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $request->all();
        $eventType = $event['event'] ?? null;

        Log::info('Paystack webhook received', [
            'event' => $eventType,
            'reference' => $event['data']['reference'] ?? null,
        ]);

        switch ($eventType) {
            case 'charge.success':
                $this->handleChargeSuccess($event['data']);
                break;

            case 'transfer.success':
                $this->handleTransferSuccess($event['data']);
                break;

            case 'transfer.failed':
                $this->handleTransferFailed($event['data']);
                break;

            case 'transfer.reversed':
                $this->handleTransferReversed($event['data']);
                break;

            default:
                Log::info('Unhandled Paystack webhook event', ['event' => $eventType]);
        }

        return response()->json(['message' => 'Webhook processed']);
    }

    /**
     * Handle successful charge (payment) event.
     */
    protected function handleChargeSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (!$reference) {
            Log::warning('Paystack charge.success missing reference');
            return;
        }

        try {
            $this->tournamentPaymentService->verifyAndProcessPayment($reference);
            Log::info('Paystack payment processed via webhook', ['reference' => $reference]);
        } catch (\Exception $e) {
            Log::error('Failed to process Paystack payment via webhook', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle successful transfer (payout) event.
     */
    protected function handleTransferSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (!$reference || !str_starts_with($reference, 'PAYOUT_')) {
            Log::info('Paystack transfer success (non-payout)', [
                'reference' => $reference,
            ]);
            return;
        }

        // Extract payout ID from reference (format: PAYOUT_{id}_{timestamp})
        $parts = explode('_', $reference);
        $payoutId = $parts[1] ?? null;

        if ($payoutId) {
            $payout = PayoutRequest::find($payoutId);
            if ($payout && $payout->status === PayoutStatus::PROCESSING) {
                $payout->markAsCompleted(
                    $data['transfer_code'] ?? $reference,
                    $data
                );
                Log::info('Payout completed via webhook', [
                    'payout_id' => $payoutId,
                    'transfer_code' => $data['transfer_code'] ?? null,
                ]);
            }
        }
    }

    /**
     * Handle failed transfer (payout) event.
     */
    protected function handleTransferFailed(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (!$reference || !str_starts_with($reference, 'PAYOUT_')) {
            Log::warning('Paystack transfer failed (non-payout)', [
                'reference' => $reference,
                'reason' => $data['reason'] ?? 'Unknown',
            ]);
            return;
        }

        $parts = explode('_', $reference);
        $payoutId = $parts[1] ?? null;

        if ($payoutId) {
            $payout = PayoutRequest::find($payoutId);
            if ($payout && $payout->status === PayoutStatus::PROCESSING) {
                // Reset to approved so admin can retry
                $payout->update([
                    'status' => PayoutStatus::ADMIN_APPROVED,
                    'payment_response' => array_merge($payout->payment_response ?? [], [
                        'failed_attempt' => $data,
                    ]),
                ]);
                Log::warning('Payout transfer failed via webhook', [
                    'payout_id' => $payoutId,
                    'reason' => $data['reason'] ?? 'Unknown',
                    'data' => $data,
                ]);
            }
        }
    }

    /**
     * Handle reversed transfer event.
     */
    protected function handleTransferReversed(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (!$reference || !str_starts_with($reference, 'PAYOUT_')) {
            Log::warning('Paystack transfer reversed (non-payout)', [
                'reference' => $reference,
            ]);
            return;
        }

        $parts = explode('_', $reference);
        $payoutId = $parts[1] ?? null;

        if ($payoutId) {
            $payout = PayoutRequest::find($payoutId);
            if ($payout) {
                // Credit back to the wallet
                $wallet = $payout->organizerProfile->wallet;
                if ($wallet && $payout->status === PayoutStatus::COMPLETED) {
                    $wallet->credit(
                        amount: $payout->amount,
                        source: 'payout_reversal',
                        description: "Payout #{$payout->id} reversed",
                        reference: $payout
                    );
                }

                // Reset to approved for retry
                $payout->update([
                    'status' => PayoutStatus::ADMIN_APPROVED,
                    'payment_response' => array_merge($payout->payment_response ?? [], [
                        'reversal' => $data,
                    ]),
                ]);

                Log::warning('Payout transfer reversed via webhook', [
                    'payout_id' => $payoutId,
                    'data' => $data,
                ]);
            }
        }
    }
}
