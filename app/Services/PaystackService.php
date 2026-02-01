<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected string $baseUrl;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('paystack.base_url') ?? 'https://api.paystack.co';
        $this->secretKey = config('paystack.secret_key') ?? '';
    }

    /**
     * Check if Paystack is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey);
    }

    /**
     * Get HTTP client with Paystack headers.
     */
    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Initialize a transaction.
     */
    public function initializeTransaction(array $data): array
    {
        $response = $this->client()->post('/transaction/initialize', [
            'email' => $data['email'],
            'amount' => $data['amount'], // Amount in kobo/cents
            'currency' => $data['currency'] ?? config('paystack.currency'),
            'reference' => $data['reference'],
            'callback_url' => $data['callback_url'] ?? url(config('paystack.callback_url')),
            'metadata' => $data['metadata'] ?? [],
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Verify a transaction.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = $this->client()->get("/transaction/verify/{$reference}");

        return $this->handleResponse($response);
    }

    /**
     * List transactions.
     */
    public function listTransactions(array $params = []): array
    {
        $response = $this->client()->get('/transaction', $params);

        return $this->handleResponse($response);
    }

    /**
     * Charge an authorization (recurring payment).
     */
    public function chargeAuthorization(array $data): array
    {
        $response = $this->client()->post('/transaction/charge_authorization', [
            'email' => $data['email'],
            'amount' => $data['amount'],
            'authorization_code' => $data['authorization_code'],
            'reference' => $data['reference'],
            'currency' => $data['currency'] ?? config('paystack.currency'),
        ]);

        return $this->handleResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Customer Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Create a customer.
     */
    public function createCustomer(array $data): array
    {
        $response = $this->client()->post('/customer', [
            'email' => $data['email'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Get customer by email or code.
     */
    public function getCustomer(string $emailOrCode): array
    {
        $response = $this->client()->get("/customer/{$emailOrCode}");

        return $this->handleResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Plan Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Create a subscription plan on Paystack.
     */
    public function createPlan(array $data): array
    {
        $response = $this->client()->post('/plan', [
            'name' => $data['name'],
            'amount' => $data['amount'],
            'interval' => $data['interval'], // daily, weekly, monthly, annually
            'currency' => $data['currency'] ?? config('paystack.currency'),
            'description' => $data['description'] ?? null,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Get a plan.
     */
    public function getPlan(string $planCode): array
    {
        $response = $this->client()->get("/plan/{$planCode}");

        return $this->handleResponse($response);
    }

    /**
     * List all plans.
     */
    public function listPlans(): array
    {
        $response = $this->client()->get('/plan');

        return $this->handleResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Create a subscription.
     */
    public function createSubscription(array $data): array
    {
        $response = $this->client()->post('/subscription', [
            'customer' => $data['customer'], // email or customer_code
            'plan' => $data['plan'], // plan_code
            'authorization' => $data['authorization'] ?? null, // authorization_code
            'start_date' => $data['start_date'] ?? null,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Enable a subscription.
     */
    public function enableSubscription(string $code, string $emailToken): array
    {
        $response = $this->client()->post('/subscription/enable', [
            'code' => $code,
            'token' => $emailToken,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Disable a subscription.
     */
    public function disableSubscription(string $code, string $emailToken): array
    {
        $response = $this->client()->post('/subscription/disable', [
            'code' => $code,
            'token' => $emailToken,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Get subscription details.
     */
    public function getSubscription(string $subscriptionCode): array
    {
        $response = $this->client()->get("/subscription/{$subscriptionCode}");

        return $this->handleResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Refund Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Create a refund.
     */
    public function createRefund(string $transactionReference, int $amount = null): array
    {
        $data = ['transaction' => $transactionReference];

        if ($amount) {
            $data['amount'] = $amount;
        }

        $response = $this->client()->post('/refund', $data);

        return $this->handleResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Transfer Endpoints (Payouts)
    |--------------------------------------------------------------------------
    */

    /**
     * Create a transfer recipient.
     */
    public function createTransferRecipient(array $data): array
    {
        $response = $this->client()->post('/transferrecipient', [
            'type' => $data['type'], // nuban, mobile_money_kenya, mobile_money_ghana, etc.
            'name' => $data['name'],
            'account_number' => $data['account_number'],
            'bank_code' => $data['bank_code'],
            'currency' => $data['currency'] ?? config('paystack.currency'),
            'description' => $data['description'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Initiate a transfer (payout).
     */
    public function initiateTransfer(array $data): array
    {
        $response = $this->client()->post('/transfer', [
            'source' => $data['source'] ?? 'balance',
            'amount' => $data['amount'], // Amount in kobo/cents
            'recipient' => $data['recipient'], // Recipient code
            'reason' => $data['reason'] ?? null,
            'reference' => $data['reference'] ?? null,
            'currency' => $data['currency'] ?? config('paystack.currency'),
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Verify a transfer.
     */
    public function verifyTransfer(string $reference): array
    {
        $response = $this->client()->get("/transfer/verify/{$reference}");

        return $this->handleResponse($response);
    }

    /**
     * Get a single transfer.
     */
    public function getTransfer(string $idOrCode): array
    {
        $response = $this->client()->get("/transfer/{$idOrCode}");

        return $this->handleResponse($response);
    }

    /**
     * List transfers.
     */
    public function listTransfers(array $params = []): array
    {
        $response = $this->client()->get('/transfer', $params);

        return $this->handleResponse($response);
    }

    /**
     * Finalize a transfer (for OTP-enabled accounts).
     */
    public function finalizeTransfer(string $transferCode, string $otp): array
    {
        $response = $this->client()->post('/transfer/finalize_transfer', [
            'transfer_code' => $transferCode,
            'otp' => $otp,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Fetch transfer recipient.
     */
    public function getTransferRecipient(string $recipientCode): array
    {
        $response = $this->client()->get("/transferrecipient/{$recipientCode}");

        return $this->handleResponse($response);
    }

    /**
     * Delete a transfer recipient.
     */
    public function deleteTransferRecipient(string $recipientCode): array
    {
        $response = $this->client()->delete("/transferrecipient/{$recipientCode}");

        return $this->handleResponse($response);
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook Verification
    |--------------------------------------------------------------------------
    */

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $computed = hash_hmac('sha512', $payload, $this->secretKey);

        return hash_equals($computed, $signature);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Handle API response.
     */
    protected function handleResponse($response): array
    {
        $data = $response->json();

        if ($response->failed()) {
            Log::error('Paystack API Error', [
                'status' => $response->status(),
                'response' => $data,
            ]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Paystack API error',
                'data' => null,
            ];
        }

        return [
            'success' => $data['status'] ?? false,
            'message' => $data['message'] ?? '',
            'data' => $data['data'] ?? null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | High-Level Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Initialize payment for tournament entry.
     */
    public function initializeTournamentPayment(Payment $payment, User $user): array
    {
        return $this->initializeTransaction([
            'email' => $user->email,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'type' => 'tournament_entry',
                'tournament_id' => $payment->payable_id,
            ],
        ]);
    }

    /**
     * Initialize payment for subscription.
     */
    public function initializeSubscriptionPayment(Payment $payment, User $user, string $planCode): array
    {
        $plan = config("paystack.plans.{$planCode}");

        return $this->initializeTransaction([
            'email' => $user->email,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'type' => 'subscription',
                'plan_code' => $planCode,
                'plan_name' => $plan['name'] ?? $planCode,
            ],
        ]);
    }

    /**
     * Charge for subscription renewal using saved authorization.
     */
    public function chargeSubscriptionRenewal(Subscription $subscription): array
    {
        if (!$subscription->authorization_code) {
            return [
                'success' => false,
                'message' => 'No authorization code found for subscription',
                'data' => null,
            ];
        }

        $reference = Payment::generateReference();

        return $this->chargeAuthorization([
            'email' => $subscription->user->email,
            'amount' => $subscription->plan_amount,
            'authorization_code' => $subscription->authorization_code,
            'reference' => $reference,
        ]);
    }
}
