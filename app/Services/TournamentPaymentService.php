<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentPaymentService
{
    public function __construct(
        protected DarajaService $daraja
    ) {}

    /**
     * Initialize payment for tournament registration via M-Pesa STK Push.
     * Sends prompt directly to player's phone.
     */
    public function initializeEntryFeePayment(Tournament $tournament, User $user, string $phoneNumber): array
    {
        if ($tournament->isFree()) {
            throw new \Exception('This tournament is free. No payment required.');
        }

        if (!$user->playerProfile) {
            throw new \Exception('You must have a player profile to register for tournaments.');
        }

        // Validate phone number
        if (!$this->daraja->isValidKenyanPhone($phoneNumber)) {
            throw new \Exception('Invalid phone number. Please use a valid Kenyan M-Pesa number.');
        }

        $participant = $tournament->participants()
            ->where('player_profile_id', $user->playerProfile->id)
            ->first();

        if (!$participant) {
            throw new \Exception('You must register for the tournament first.');
        }

        if ($participant->payment_status === 'paid') {
            throw new \Exception('Entry fee has already been paid.');
        }

        // Check for existing pending payment (less than 5 minutes old)
        $existingPayment = Payment::where('user_id', $user->id)
            ->where('payable_type', Tournament::class)
            ->where('payable_id', $tournament->id)
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(5))
            ->first();

        if ($existingPayment && $existingPayment->mpesa_checkout_request_id) {
            return [
                'payment' => $existingPayment,
                'message' => 'Payment prompt already sent. Please check your phone.',
                'checkout_request_id' => $existingPayment->mpesa_checkout_request_id,
            ];
        }

        // Create payment record
        // Amount is stored in cents/smallest unit, but M-Pesa uses whole KES
        $amountInKes = (int) ($tournament->entry_fee / 100);

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => Tournament::class,
            'payable_id' => $tournament->id,
            'amount' => $tournament->entry_fee, // Store in cents
            'currency' => 'KES',
            'status' => 'pending',
            'payment_method' => 'mpesa',
            'phone_number' => $this->daraja->formatPhoneNumber($phoneNumber),
            'metadata' => [
                'participant_id' => $participant->id,
                'tournament_name' => $tournament->name,
                'player_name' => $user->playerProfile->display_name,
            ],
        ]);

        // Initiate STK Push
        $response = $this->daraja->stkPush(
            phoneNumber: $phoneNumber,
            amount: $amountInKes,
            accountReference: "T{$tournament->id}",
            transactionDesc: 'Entry Fee'
        );

        if (!$response['success']) {
            $payment->markAsFailed($response['message']);
            throw new \Exception($response['message'] ?? 'Failed to send payment prompt');
        }

        // Update payment with M-Pesa reference
        $payment->update([
            'mpesa_checkout_request_id' => $response['data']['checkout_request_id'],
            'mpesa_merchant_request_id' => $response['data']['merchant_request_id'],
        ]);

        Log::info('M-Pesa STK Push initiated', [
            'payment_id' => $payment->id,
            'tournament_id' => $tournament->id,
            'checkout_request_id' => $response['data']['checkout_request_id'],
        ]);

        return [
            'payment' => $payment,
            'message' => 'Payment prompt sent to your phone. Please enter your M-Pesa PIN.',
            'checkout_request_id' => $response['data']['checkout_request_id'],
        ];
    }

    /**
     * Check payment status by querying M-Pesa.
     */
    public function checkPaymentStatus(Payment $payment): array
    {
        if ($payment->isSuccessful()) {
            return [
                'status' => 'success',
                'message' => 'Payment completed',
                'payment' => $payment,
            ];
        }

        if (!$payment->mpesa_checkout_request_id) {
            return [
                'status' => 'unknown',
                'message' => 'No checkout request found',
                'payment' => $payment,
            ];
        }

        $response = $this->daraja->stkQuery($payment->mpesa_checkout_request_id);

        if ($response['success']) {
            // Payment was successful - process it
            $this->processSuccessfulPayment($payment, $response['data']);

            return [
                'status' => 'success',
                'message' => 'Payment completed',
                'payment' => $payment->fresh(),
            ];
        }

        // Check if it's a terminal failure or still pending
        $resultCode = $response['data']['ResultCode'] ?? null;

        if ($resultCode === '1032') {
            // User cancelled
            $payment->markAsFailed('Payment cancelled by user');
            return [
                'status' => 'cancelled',
                'message' => 'Payment was cancelled',
                'payment' => $payment,
            ];
        }

        if ($resultCode === '1037' || $resultCode === '1' || $resultCode === '2001') {
            // Timeout or other failure
            $payment->markAsFailed($response['message']);
            return [
                'status' => 'failed',
                'message' => $response['message'],
                'payment' => $payment,
            ];
        }

        // Still pending
        return [
            'status' => 'pending',
            'message' => 'Payment is still being processed',
            'payment' => $payment,
        ];
    }

    /**
     * Process a successful payment (called from callback or manual check).
     */
    public function processSuccessfulPayment(Payment $payment, array $mpesaData = []): void
    {
        if ($payment->isSuccessful()) {
            return; // Already processed
        }

        DB::transaction(function () use ($payment, $mpesaData) {
            // Mark payment as successful
            $payment->markAsSuccessful($mpesaData);

            // Update M-Pesa receipt if provided
            if (!empty($mpesaData['MpesaReceiptNumber'])) {
                $payment->update([
                    'mpesa_receipt_number' => $mpesaData['MpesaReceiptNumber'],
                ]);
            }

            // Update participant payment status
            $participantId = $payment->metadata['participant_id'] ?? null;
            if ($participantId) {
                TournamentParticipant::where('id', $participantId)->update([
                    'payment_status' => 'paid',
                    'payment_id' => $payment->id,
                ]);
            }

            // Credit organizer's wallet
            $this->creditOrganizerBalance($payment);
        });
    }

    /**
     * Credit the tournament organizer's wallet.
     * Uses OrganizerWallet for proper transaction audit trail.
     * Player pays EXACT amount - fees are absorbed by the system.
     */
    protected function creditOrganizerBalance(Payment $payment): void
    {
        $tournament = $payment->payable;

        if (!$tournament instanceof Tournament) {
            Log::warning('Payment payable is not a tournament', ['payment_id' => $payment->id]);
            return;
        }

        $organizer = $tournament->createdBy;

        if (!$organizer) {
            Log::warning('Tournament has no creator', [
                'tournament_id' => $tournament->id,
            ]);
            return;
        }

        $organizerProfile = $organizer->organizerProfile;

        if (!$organizerProfile) {
            Log::warning('Tournament organizer has no organizer profile', [
                'tournament_id' => $tournament->id,
                'user_id' => $tournament->created_by,
            ]);
            return;
        }

        // Get or create the organizer's wallet
        $wallet = $organizerProfile->getOrCreateWallet();

        // Get player name for description
        $playerName = $payment->metadata['player_name'] ?? 'Unknown player';

        // Credit the wallet with full audit trail
        $wallet->credit(
            amount: $payment->amount,
            source: 'tournament_entry',
            description: "Entry fee from {$playerName} for {$tournament->name}",
            reference: $payment
        );

        // Also update organizer profile totals for quick access
        $organizerProfile->increment('available_balance', $payment->amount);
        $organizerProfile->increment('total_earnings', $payment->amount);

        Log::info('Credited organizer wallet', [
            'organizer_profile_id' => $organizerProfile->id,
            'wallet_id' => $wallet->id,
            'amount' => $payment->amount,
            'payment_id' => $payment->id,
            'tournament_id' => $tournament->id,
            'new_balance' => $wallet->balance,
        ]);
    }

    /**
     * Handle refund for a tournament entry.
     * Note: M-Pesa doesn't support automatic refunds - this deducts from organizer
     * and marks for manual B2C transfer.
     */
    public function refundEntryFee(TournamentParticipant $participant, string $reason): void
    {
        $payment = Payment::where('payable_type', Tournament::class)
            ->where('payable_id', $participant->tournament_id)
            ->whereJsonContains('metadata->participant_id', $participant->id)
            ->where('status', 'success')
            ->first();

        if (!$payment) {
            throw new \Exception('No completed payment found for this participant.');
        }

        DB::transaction(function () use ($payment, $participant, $reason) {
            $tournament = $participant->tournament;
            $organizerProfile = $tournament->createdBy->organizerProfile;

            if ($organizerProfile) {
                // Debit from wallet with audit trail
                $wallet = $organizerProfile->wallet;
                if ($wallet && $wallet->balance >= $payment->amount) {
                    $playerName = $participant->playerProfile->display_name ?? 'Unknown';
                    $wallet->debit(
                        amount: $payment->amount,
                        source: 'refund',
                        description: "Refund to {$playerName} for {$tournament->name}: {$reason}",
                        reference: $payment
                    );
                }

                // Also update organizer profile totals
                if ($organizerProfile->available_balance >= $payment->amount) {
                    $organizerProfile->decrement('available_balance', $payment->amount);
                    $organizerProfile->decrement('total_earnings', $payment->amount);
                }
            }

            // Update payment status
            $payment->markAsRefunded();
            $payment->update([
                'failure_reason' => "Refund: {$reason}",
            ]);

            // Update participant
            $participant->update([
                'payment_status' => 'refunded',
            ]);

            Log::info('Refunded tournament entry fee', [
                'payment_id' => $payment->id,
                'participant_id' => $participant->id,
                'amount' => $payment->amount,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Check if a participant has paid for their tournament entry.
     */
    public function hasParticipantPaid(TournamentParticipant $participant): bool
    {
        return $participant->payment_status === 'paid';
    }

    /**
     * Get payment status for a tournament participant.
     */
    public function getParticipantPaymentStatus(Tournament $tournament, User $user): array
    {
        if (!$user->playerProfile) {
            return [
                'registered' => false,
                'payment_required' => $tournament->requires_payment,
                'payment_status' => null,
                'payment' => null,
            ];
        }

        $participant = $tournament->participants()
            ->where('player_profile_id', $user->playerProfile->id)
            ->first();

        if (!$participant) {
            return [
                'registered' => false,
                'payment_required' => $tournament->requires_payment,
                'payment_status' => null,
                'payment' => null,
            ];
        }

        $payment = Payment::where('user_id', $user->id)
            ->where('payable_type', Tournament::class)
            ->where('payable_id', $tournament->id)
            ->latest()
            ->first();

        return [
            'registered' => true,
            'payment_required' => $tournament->requires_payment,
            'payment_status' => $participant->payment_status,
            'payment' => $payment,
        ];
    }
}
