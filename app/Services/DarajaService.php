<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DarajaService
{
    protected string $environment;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $shortcode;
    protected string $passkey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->environment = config('daraja.environment') ?? 'sandbox';
        $this->consumerKey = config('daraja.consumer_key') ?? '';
        $this->consumerSecret = config('daraja.consumer_secret') ?? '';
        $this->shortcode = config('daraja.shortcode') ?? '';
        $this->passkey = config('daraja.passkey') ?? '';
        $this->baseUrl = config("daraja.base_url.{$this->environment}") ?? 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Check if Daraja is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->consumerKey) && !empty($this->consumerSecret);
    }

    /**
     * Get OAuth access token.
     */
    public function getAccessToken(): ?string
    {
        $cacheKey = 'daraja_access_token';

        return Cache::remember($cacheKey, 3000, function () {
            $credentials = base64_encode("{$this->consumerKey}:{$this->consumerSecret}");

            $response = Http::withHeaders([
                'Authorization' => "Basic {$credentials}",
            ])->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error('Daraja OAuth failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        });
    }

    /**
     * Clear cached access token.
     */
    public function clearAccessToken(): void
    {
        Cache::forget('daraja_access_token');
    }

    /*
    |--------------------------------------------------------------------------
    | STK Push (Lipa Na M-Pesa Online) - For collecting payments
    |--------------------------------------------------------------------------
    */

    /**
     * Initiate STK Push to collect payment from customer.
     *
     * @param string $phoneNumber Customer phone (254XXXXXXXXX)
     * @param int $amount Amount in KES (whole number, not cents)
     * @param string $accountReference Your reference (e.g., tournament ID)
     * @param string $transactionDesc Description shown to customer
     * @return array
     */
    public function stkPush(
        string $phoneNumber,
        int $amount,
        string $accountReference,
        string $transactionDesc = 'Payment'
    ): array {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to get access token',
                'data' => null,
            ];
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $callbackUrl = url(config('daraja.stk_callback_url'));

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $this->formatPhoneNumber($phoneNumber),
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $this->formatPhoneNumber($phoneNumber),
            'CallBackURL' => $callbackUrl,
            'AccountReference' => substr($accountReference, 0, 12), // Max 12 chars
            'TransactionDesc' => substr($transactionDesc, 0, 13), // Max 13 chars
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", $payload);

        $data = $response->json();

        Log::info('Daraja STK Push request', [
            'phone' => $this->maskPhoneNumber($phoneNumber),
            'amount' => $amount,
            'reference' => $accountReference,
            'response_code' => $data['ResponseCode'] ?? null,
        ]);

        if ($response->successful() && ($data['ResponseCode'] ?? '') === '0') {
            return [
                'success' => true,
                'message' => $data['ResponseDescription'] ?? 'Success',
                'data' => [
                    'checkout_request_id' => $data['CheckoutRequestID'],
                    'merchant_request_id' => $data['MerchantRequestID'],
                ],
            ];
        }

        return [
            'success' => false,
            'message' => $data['errorMessage'] ?? $data['ResponseDescription'] ?? 'STK Push failed',
            'data' => $data,
        ];
    }

    /**
     * Query STK Push transaction status.
     */
    public function stkQuery(string $checkoutRequestId): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to get access token',
                'data' => null,
            ];
        }

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", $payload);

        $data = $response->json();

        if ($response->successful() && ($data['ResultCode'] ?? '') === '0') {
            return [
                'success' => true,
                'message' => $data['ResultDesc'] ?? 'Success',
                'data' => $data,
            ];
        }

        return [
            'success' => false,
            'message' => $data['ResultDesc'] ?? $data['errorMessage'] ?? 'Query failed',
            'data' => $data,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | B2C (Business to Customer) - For payouts
    |--------------------------------------------------------------------------
    */

    /**
     * Send money to customer (payout).
     *
     * @param string $phoneNumber Recipient phone (254XXXXXXXXX)
     * @param int $amount Amount in KES
     * @param string $remarks Remarks for the transaction
     * @param string $occasion Occasion for the transaction
     * @return array
     */
    public function b2cPayment(
        string $phoneNumber,
        int $amount,
        string $remarks = 'Payout',
        string $occasion = ''
    ): array {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to get access token',
                'data' => null,
            ];
        }

        $b2cShortcode = config('daraja.b2c_shortcode', $this->shortcode);
        $initiatorName = config('daraja.initiator_name');
        $securityCredential = config('daraja.security_credential');

        $resultUrl = url(config('daraja.b2c_result_url'));
        $timeoutUrl = url(config('daraja.b2c_timeout_url'));

        $payload = [
            'InitiatorName' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => 'BusinessPayment', // or 'SalaryPayment', 'PromotionPayment'
            'Amount' => $amount,
            'PartyA' => $b2cShortcode,
            'PartyB' => $this->formatPhoneNumber($phoneNumber),
            'Remarks' => substr($remarks, 0, 100),
            'QueueTimeOutURL' => $timeoutUrl,
            'ResultURL' => $resultUrl,
            'Occasion' => substr($occasion, 0, 100),
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/b2c/v1/paymentrequest", $payload);

        $data = $response->json();

        Log::info('Daraja B2C request', [
            'phone' => $this->maskPhoneNumber($phoneNumber),
            'amount' => $amount,
            'response_code' => $data['ResponseCode'] ?? null,
        ]);

        if ($response->successful() && ($data['ResponseCode'] ?? '') === '0') {
            return [
                'success' => true,
                'message' => $data['ResponseDescription'] ?? 'Success',
                'data' => [
                    'conversation_id' => $data['ConversationID'],
                    'originator_conversation_id' => $data['OriginatorConversationID'],
                ],
            ];
        }

        return [
            'success' => false,
            'message' => $data['errorMessage'] ?? $data['ResponseDescription'] ?? 'B2C payment failed',
            'data' => $data,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Account Balance Query
    |--------------------------------------------------------------------------
    */

    /**
     * Query account balance.
     */
    public function accountBalance(): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to get access token',
                'data' => null,
            ];
        }

        $initiatorName = config('daraja.initiator_name');
        $securityCredential = config('daraja.security_credential');

        $payload = [
            'Initiator' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => 'AccountBalance',
            'PartyA' => $this->shortcode,
            'IdentifierType' => '4', // 4 for Paybill
            'Remarks' => 'Balance query',
            'QueueTimeOutURL' => url(config('daraja.b2c_timeout_url')),
            'ResultURL' => url(config('daraja.b2c_result_url')),
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/accountbalance/v1/query", $payload);

        $data = $response->json();

        if ($response->successful() && ($data['ResponseCode'] ?? '') === '0') {
            return [
                'success' => true,
                'message' => 'Balance query initiated',
                'data' => $data,
            ];
        }

        return [
            'success' => false,
            'message' => $data['errorMessage'] ?? 'Balance query failed',
            'data' => $data,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Status Query
    |--------------------------------------------------------------------------
    */

    /**
     * Query transaction status.
     */
    public function transactionStatus(string $transactionId): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to get access token',
                'data' => null,
            ];
        }

        $initiatorName = config('daraja.initiator_name');
        $securityCredential = config('daraja.security_credential');

        $payload = [
            'Initiator' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => $transactionId,
            'PartyA' => $this->shortcode,
            'IdentifierType' => '4',
            'Remarks' => 'Status query',
            'QueueTimeOutURL' => url(config('daraja.b2c_timeout_url')),
            'ResultURL' => url(config('daraja.b2c_result_url')),
            'Occasion' => '',
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/mpesa/transactionstatus/v1/query", $payload);

        $data = $response->json();

        if ($response->successful() && ($data['ResponseCode'] ?? '') === '0') {
            return [
                'success' => true,
                'message' => 'Status query initiated',
                'data' => $data,
            ];
        }

        return [
            'success' => false,
            'message' => $data['errorMessage'] ?? 'Status query failed',
            'data' => $data,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Format phone number to 254XXXXXXXXX format.
     */
    public function formatPhoneNumber(string $phone): string
    {
        // Remove any spaces, dashes, or plus signs
        $phone = preg_replace('/[\s\-\+]/', '', $phone);

        // Handle different formats
        if (str_starts_with($phone, '0')) {
            // 0712345678 -> 254712345678
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            // 712345678 -> 254712345678
            $phone = '254' . $phone;
        } elseif (str_starts_with($phone, '+254')) {
            // +254712345678 -> 254712345678
            $phone = substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Validate Kenyan phone number.
     */
    public function isValidKenyanPhone(string $phone): bool
    {
        $formatted = $this->formatPhoneNumber($phone);

        // Must be 12 digits starting with 254
        if (!preg_match('/^254[17]\d{8}$/', $formatted)) {
            return false;
        }

        return true;
    }

    /**
     * Mask phone number for logging.
     */
    protected function maskPhoneNumber(string $phone): string
    {
        $formatted = $this->formatPhoneNumber($phone);
        return substr($formatted, 0, 6) . '****' . substr($formatted, -2);
    }

    /**
     * Parse callback data from Daraja.
     */
    public function parseCallback(array $data): array
    {
        $body = $data['Body']['stkCallback'] ?? $data['Body'] ?? $data;

        $resultCode = $body['ResultCode'] ?? null;
        $resultDesc = $body['ResultDesc'] ?? '';
        $checkoutRequestId = $body['CheckoutRequestID'] ?? null;
        $merchantRequestId = $body['MerchantRequestID'] ?? null;

        $metadata = [];
        $callbackMetadata = $body['CallbackMetadata']['Item'] ?? [];

        foreach ($callbackMetadata as $item) {
            $metadata[$item['Name']] = $item['Value'] ?? null;
        }

        return [
            'success' => $resultCode === 0,
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
            'amount' => $metadata['Amount'] ?? null,
            'mpesa_receipt_number' => $metadata['MpesaReceiptNumber'] ?? null,
            'transaction_date' => $metadata['TransactionDate'] ?? null,
            'phone_number' => $metadata['PhoneNumber'] ?? null,
        ];
    }

    /**
     * Parse B2C result callback.
     */
    public function parseB2CResult(array $data): array
    {
        $result = $data['Result'] ?? $data;

        $resultCode = $result['ResultCode'] ?? null;
        $resultDesc = $result['ResultDesc'] ?? '';
        $conversationId = $result['ConversationID'] ?? null;
        $transactionId = $result['TransactionID'] ?? null;

        $metadata = [];
        $resultParams = $result['ResultParameters']['ResultParameter'] ?? [];

        foreach ($resultParams as $item) {
            $metadata[$item['Key']] = $item['Value'] ?? null;
        }

        return [
            'success' => $resultCode === 0,
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'conversation_id' => $conversationId,
            'transaction_id' => $transactionId,
            'amount' => $metadata['TransactionAmount'] ?? null,
            'recipient' => $metadata['ReceiverPartyPublicName'] ?? null,
            'completed_at' => $metadata['TransactionCompletedDateTime'] ?? null,
        ];
    }
}
