<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Services\DarajaService;
use App\Services\TournamentPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function __construct(
        protected DarajaService $daraja,
        protected TournamentPaymentService $paymentService
    ) {}

    /**
     * Handle STK Push callback (payment collection).
     * POST /api/payments/mpesa/callback
     */
    public function stkCallback(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('M-Pesa STK callback received', ['payload' => $payload]);

        try {
            $parsed = $this->daraja->parseCallback($payload);

            if (!$parsed['checkout_request_id']) {
                Log::warning('M-Pesa callback missing checkout_request_id');
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Find the payment by checkout_request_id
            $payment = Payment::where('mpesa_checkout_request_id', $parsed['checkout_request_id'])->first();

            if (!$payment) {
                Log::warning('Payment not found for checkout_request_id', [
                    'checkout_request_id' => $parsed['checkout_request_id'],
                ]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            if ($parsed['success']) {
                // Payment successful
                $payment->markAsSuccessful([
                    'mpesa_receipt_number' => $parsed['mpesa_receipt_number'],
                    'transaction_date' => $parsed['transaction_date'],
                    'phone_number' => $parsed['phone_number'],
                    'amount' => $parsed['amount'],
                ]);

                // Process the payment (credit organizer, etc.)
                $this->paymentService->processSuccessfulPayment($payment);

                Log::info('M-Pesa payment successful', [
                    'payment_id' => $payment->id,
                    'receipt' => $parsed['mpesa_receipt_number'],
                    'amount' => $parsed['amount'],
                ]);
            } else {
                // Payment failed
                $payment->markAsFailed($parsed['result_desc']);

                Log::info('M-Pesa payment failed', [
                    'payment_id' => $payment->id,
                    'result_code' => $parsed['result_code'],
                    'result_desc' => $parsed['result_desc'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('M-Pesa callback processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Always return success to M-Pesa
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }

    /**
     * Handle B2C result callback (payout).
     * POST /api/payouts/mpesa/result
     */
    public function b2cResult(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('M-Pesa B2C result received', ['payload' => $payload]);

        try {
            $parsed = $this->daraja->parseB2CResult($payload);

            if (!$parsed['conversation_id']) {
                Log::warning('M-Pesa B2C callback missing conversation_id');
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Find the payout request by conversation_id
            $payout = PayoutRequest::where('mpesa_conversation_id', $parsed['conversation_id'])->first();

            if (!$payout) {
                Log::warning('Payout not found for conversation_id', [
                    'conversation_id' => $parsed['conversation_id'],
                ]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            if ($parsed['success']) {
                // Payout successful
                $payout->markAsCompleted(
                    reference: $parsed['transaction_id'],
                    response: $parsed
                );

                Log::info('M-Pesa payout successful', [
                    'payout_id' => $payout->id,
                    'transaction_id' => $parsed['transaction_id'],
                    'amount' => $parsed['amount'],
                ]);
            } else {
                // Payout failed - revert to approved status for retry
                $payout->update([
                    'status' => \App\Enums\PayoutStatus::ADMIN_APPROVED,
                    'failure_reason' => $parsed['result_desc'],
                ]);

                Log::info('M-Pesa payout failed', [
                    'payout_id' => $payout->id,
                    'result_code' => $parsed['result_code'],
                    'result_desc' => $parsed['result_desc'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('M-Pesa B2C callback processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }

    /**
     * Handle B2C timeout callback.
     * POST /api/payouts/mpesa/timeout
     */
    public function b2cTimeout(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::warning('M-Pesa B2C timeout', ['payload' => $payload]);

        // For timeouts, we don't change status - admin can retry
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }
}
