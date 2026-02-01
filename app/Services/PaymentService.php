<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        protected PaystackService $paystack,
        protected TournamentService $tournamentService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Tournament Entry Fee Payments
    |--------------------------------------------------------------------------
    */

    /**
     * Initiate tournament entry payment.
     */
    public function initiateTournamentPayment(Tournament $tournament, User $user): array
    {
        if (!$tournament->requires_payment) {
            return [
                'success' => false,
                'message' => 'This tournament does not require payment',
            ];
        }

        // Check if already registered
        $existing = TournamentParticipant::where('tournament_id', $tournament->id)
            ->where('player_profile_id', $user->playerProfile->id)
            ->first();

        if ($existing && $existing->payment_status === 'paid') {
            return [
                'success' => false,
                'message' => 'You have already paid for this tournament',
            ];
        }

        // Check for pending payment
        $pendingPayment = Payment::where('user_id', $user->id)
            ->where('payable_type', Tournament::class)
            ->where('payable_id', $tournament->id)
            ->where('status', 'pending')
            ->first();

        if ($pendingPayment && $pendingPayment->authorization_url) {
            return [
                'success' => true,
                'message' => 'Pending payment found',
                'payment' => $pendingPayment,
                'authorization_url' => $pendingPayment->authorization_url,
            ];
        }

        return DB::transaction(function () use ($tournament, $user) {
            // Create payment record
            $payment = Payment::create([
                'user_id' => $user->id,
                'payable_type' => Tournament::class,
                'payable_id' => $tournament->id,
                'amount' => $tournament->entry_fee,
                'currency' => $tournament->entry_fee_currency,
                'status' => 'pending',
                'metadata' => [
                    'tournament_name' => $tournament->name,
                ],
            ]);

            // Initialize with Paystack
            $response = $this->paystack->initializeTournamentPayment($payment, $user);

            if (!$response['success']) {
                $payment->markAsFailed($response['message']);
                return [
                    'success' => false,
                    'message' => $response['message'],
                ];
            }

            // Update payment with Paystack details
            $payment->update([
                'access_code' => $response['data']['access_code'],
                'authorization_url' => $response['data']['authorization_url'],
            ]);

            return [
                'success' => true,
                'message' => 'Payment initialized',
                'payment' => $payment,
                'authorization_url' => $response['data']['authorization_url'],
            ];
        });
    }

    /**
     * Handle successful tournament payment.
     */
    public function handleTournamentPaymentSuccess(Payment $payment, array $paystackData): void
    {
        DB::transaction(function () use ($payment, $paystackData) {
            // Mark payment as successful
            $payment->markAsSuccessful($paystackData);

            $tournament = $payment->payable;
            $user = $payment->user;

            // Check if participant exists
            $participant = TournamentParticipant::where('tournament_id', $tournament->id)
                ->where('player_profile_id', $user->playerProfile->id)
                ->first();

            if ($participant) {
                // Update existing participant
                $participant->update([
                    'payment_status' => 'paid',
                    'payment_id' => $payment->id,
                ]);
            } else {
                // Register player for tournament
                $participant = $this->tournamentService->registerPlayer(
                    $tournament,
                    $user->playerProfile
                );

                $participant->update([
                    'payment_status' => 'paid',
                    'payment_id' => $payment->id,
                ]);
            }

            Log::info('Tournament payment successful', [
                'payment_id' => $payment->id,
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription Payments
    |--------------------------------------------------------------------------
    */

    /**
     * Initiate subscription payment (or activate free plan).
     */
    public function initiateSubscriptionPayment(string $planCode, User $user): array
    {
        $plan = config("paystack.plans.{$planCode}");

        if (!$plan) {
            return [
                'success' => false,
                'message' => 'Invalid plan selected',
            ];
        }

        // Check for active subscription
        $activeSubscription = $user->subscriptions()
            ->active()
            ->first();

        if ($activeSubscription) {
            return [
                'success' => false,
                'message' => 'You already have an active subscription',
            ];
        }

        // Handle free plans (Starter)
        if (($plan['amount'] ?? 0) === 0) {
            return $this->activateFreeSubscription($planCode, $user);
        }

        return DB::transaction(function () use ($planCode, $plan, $user) {
            // Create subscription record
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_code' => $planCode,
                'status' => 'pending',
            ]);

            // Create payment record
            $payment = Payment::create([
                'user_id' => $user->id,
                'payable_type' => Subscription::class,
                'payable_id' => $subscription->id,
                'amount' => $plan['amount'],
                'currency' => $plan['currency'] ?? 'USD',
                'status' => 'pending',
                'metadata' => [
                    'plan_name' => $plan['name'],
                    'plan_code' => $planCode,
                ],
            ]);

            // Initialize with Paystack
            $response = $this->paystack->initializeSubscriptionPayment($payment, $user, $planCode);

            if (!$response['success']) {
                $payment->markAsFailed($response['message']);
                $subscription->delete();

                return [
                    'success' => false,
                    'message' => $response['message'],
                ];
            }

            // Update payment with Paystack details
            $payment->update([
                'access_code' => $response['data']['access_code'],
                'authorization_url' => $response['data']['authorization_url'],
            ]);

            return [
                'success' => true,
                'message' => 'Payment initialized',
                'payment' => $payment,
                'subscription' => $subscription,
                'authorization_url' => $response['data']['authorization_url'],
            ];
        });
    }

    /**
     * Activate a free subscription (Starter plan).
     */
    protected function activateFreeSubscription(string $planCode, User $user): array
    {
        return DB::transaction(function () use ($planCode, $user) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_code' => $planCode,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'tournaments_used' => 0,
            ]);

            // Update user as organizer
            if (!$user->is_organizer) {
                $user->update(['is_organizer' => true]);
            }

            Log::info('Free subscription activated', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_code' => $planCode,
            ]);

            return [
                'success' => true,
                'message' => 'Free plan activated successfully',
                'subscription' => $subscription,
                'is_free' => true,
            ];
        });
    }

    /**
     * Handle successful subscription payment.
     */
    public function handleSubscriptionPaymentSuccess(Payment $payment, array $paystackData): void
    {
        DB::transaction(function () use ($payment, $paystackData) {
            // Mark payment as successful
            $payment->markAsSuccessful($paystackData);

            $subscription = $payment->payable;

            // Save authorization for recurring charges
            $authorizationCode = $paystackData['authorization']['authorization_code'] ?? null;

            // Activate subscription
            $subscription->activate($authorizationCode);

            // Update user as organizer if not already
            $user = $subscription->user;
            if (!$user->is_organizer) {
                $user->update(['is_organizer' => true]);
            }

            Log::info('Subscription payment successful', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Verification
    |--------------------------------------------------------------------------
    */

    /**
     * Verify payment with Paystack.
     */
    public function verifyPayment(string $reference): array
    {
        $payment = Payment::where('reference', $reference)->first();

        if (!$payment) {
            return [
                'success' => false,
                'message' => 'Payment not found',
            ];
        }

        if ($payment->isSuccessful()) {
            return [
                'success' => true,
                'message' => 'Payment already verified',
                'payment' => $payment,
            ];
        }

        // Verify with Paystack
        $response = $this->paystack->verifyTransaction($reference);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => $response['message'],
            ];
        }

        $data = $response['data'];

        if ($data['status'] === 'success') {
            // Handle based on payment type
            if ($payment->is_tournament_entry) {
                $this->handleTournamentPaymentSuccess($payment, $data);
            } elseif ($payment->is_subscription) {
                $this->handleSubscriptionPaymentSuccess($payment, $data);
            }

            return [
                'success' => true,
                'message' => 'Payment verified successfully',
                'payment' => $payment->fresh(),
            ];
        }

        // Payment failed
        $payment->markAsFailed($data['gateway_response'] ?? 'Payment failed');

        return [
            'success' => false,
            'message' => $data['gateway_response'] ?? 'Payment failed',
            'payment' => $payment,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook Processing
    |--------------------------------------------------------------------------
    */

    /**
     * Process Paystack webhook event.
     */
    public function processWebhook(array $event): void
    {
        $eventType = $event['event'] ?? null;
        $data = $event['data'] ?? [];

        Log::info('Paystack webhook received', ['event' => $eventType]);

        match ($eventType) {
            'charge.success' => $this->handleChargeSuccess($data),
            'subscription.create' => $this->handleSubscriptionCreated($data),
            'subscription.disable' => $this->handleSubscriptionDisabled($data),
            'invoice.payment_failed' => $this->handleInvoiceFailed($data),
            default => Log::info('Unhandled webhook event', ['event' => $eventType]),
        };
    }

    protected function handleChargeSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (!$reference) {
            return;
        }

        $this->verifyPayment($reference);
    }

    protected function handleSubscriptionCreated(array $data): void
    {
        // Subscription created on Paystack side
        Log::info('Subscription created on Paystack', $data);
    }

    protected function handleSubscriptionDisabled(array $data): void
    {
        $subscriptionCode = $data['subscription_code'] ?? null;

        $subscription = Subscription::where('paystack_subscription_code', $subscriptionCode)->first();

        if ($subscription) {
            $subscription->cancel('Disabled via Paystack');
        }
    }

    protected function handleInvoiceFailed(array $data): void
    {
        $subscriptionCode = $data['subscription']['subscription_code'] ?? null;

        $subscription = Subscription::where('paystack_subscription_code', $subscriptionCode)->first();

        if ($subscription) {
            $subscription->markPastDue();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Refunds
    |--------------------------------------------------------------------------
    */

    /**
     * Refund a tournament entry payment.
     */
    public function refundTournamentPayment(Payment $payment): array
    {
        if (!$payment->isSuccessful()) {
            return [
                'success' => false,
                'message' => 'Can only refund successful payments',
            ];
        }

        $response = $this->paystack->createRefund($payment->paystack_reference);

        if ($response['success']) {
            $payment->markAsRefunded();

            // Update participant status
            if ($payment->is_tournament_entry) {
                TournamentParticipant::where('payment_id', $payment->id)
                    ->update(['payment_status' => 'refunded']);
            }
        }

        return $response;
    }
}
