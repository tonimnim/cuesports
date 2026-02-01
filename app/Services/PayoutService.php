<?php

namespace App\Services;

use App\Models\OrganizerProfile;
use App\Models\OrganizerWallet;
use App\Models\PayoutMethod;
use App\Models\PayoutRequest;
use App\Enums\PayoutStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class PayoutService
{
    /**
     * Get wallet with recent transactions.
     */
    public function getWalletOverview(OrganizerProfile $organizer): array
    {
        $wallet = $organizer->getOrCreateWallet();
        $wallet->load(['transactions' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }]);

        $pendingPayouts = $organizer->payoutRequests()
            ->whereNotIn('status', [PayoutStatus::COMPLETED, PayoutStatus::REJECTED])
            ->sum('amount');

        return [
            'wallet' => $wallet,
            'pending_payouts' => $pendingPayouts,
            'available_balance' => max(0, $wallet->balance - $pendingPayouts),
        ];
    }

    /**
     * Get paginated wallet transactions.
     */
    public function getTransactions(OrganizerWallet $wallet, int $perPage = 20): LengthAwarePaginator
    {
        return $wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Add a new payout method.
     */
    public function addPayoutMethod(OrganizerProfile $organizer, array $data): PayoutMethod
    {
        // If this is the first method or marked as default, ensure it's the only default
        if ($data['is_default'] ?? false) {
            $organizer->payoutMethods()->update(['is_default' => false]);
        }

        // If no methods exist, make this the default
        if ($organizer->payoutMethods()->count() === 0) {
            $data['is_default'] = true;
        }

        return $organizer->payoutMethods()->create([
            'type' => $data['type'],
            'provider' => $data['provider'] ?? 'paystack',
            'account_name' => $data['account_name'],
            'account_number' => $data['account_number'],
            'bank_code' => $data['bank_code'] ?? null,
            'is_default' => $data['is_default'] ?? false,
            'is_verified' => false,
        ]);
    }

    /**
     * Set a payout method as default.
     */
    public function setDefaultPayoutMethod(OrganizerProfile $organizer, PayoutMethod $method): void
    {
        if ($method->organizer_profile_id !== $organizer->id) {
            throw new \Exception('This payment method does not belong to you.');
        }

        $organizer->payoutMethods()->update(['is_default' => false]);
        $method->update(['is_default' => true]);
    }

    /**
     * Delete a payout method.
     */
    public function deletePayoutMethod(PayoutMethod $method): void
    {
        // Check if there are pending payouts using this method
        $pendingCount = PayoutRequest::where('payout_method_id', $method->id)
            ->whereNotIn('status', [PayoutStatus::COMPLETED, PayoutStatus::REJECTED])
            ->count();

        if ($pendingCount > 0) {
            throw new \Exception('Cannot delete: there are pending payout requests using this method.');
        }

        $wasDefault = $method->is_default;
        $organizerId = $method->organizer_profile_id;

        $method->delete();

        // If deleted method was default, make another one default
        if ($wasDefault) {
            PayoutMethod::where('organizer_profile_id', $organizerId)
                ->first()
                ?->update(['is_default' => true]);
        }
    }

    /**
     * Request a payout.
     */
    public function requestPayout(OrganizerProfile $organizer, array $data): PayoutRequest
    {
        $wallet = $organizer->getOrCreateWallet();
        $amount = $data['amount'];

        // Get pending payout amounts
        $pendingPayouts = $organizer->payoutRequests()
            ->whereNotIn('status', [PayoutStatus::COMPLETED, PayoutStatus::REJECTED])
            ->sum('amount');

        $availableBalance = $wallet->balance - $pendingPayouts;

        if ($amount > $availableBalance) {
            throw new \Exception("Insufficient balance. Available: {$wallet->currency} " . number_format($availableBalance / 100, 2));
        }

        if ($amount < 100) { // Minimum 1 KES (100 cents)
            throw new \Exception('Minimum payout amount is 1 ' . $wallet->currency);
        }

        // Get payout method
        $methodId = $data['payout_method_id'] ?? null;
        $method = $methodId
            ? $organizer->payoutMethods()->findOrFail($methodId)
            : $organizer->defaultPayoutMethod();

        if (!$method) {
            throw new \Exception('Please add a payout method first.');
        }

        return DB::transaction(function () use ($organizer, $method, $amount, $wallet) {
            return PayoutRequest::create([
                'organizer_profile_id' => $organizer->id,
                'payout_method_id' => $method->id,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'status' => PayoutStatus::PENDING_REVIEW,
            ]);
        });
    }

    /**
     * Cancel a payout request (only if still pending review).
     */
    public function cancelPayoutRequest(PayoutRequest $request): void
    {
        if ($request->status !== PayoutStatus::PENDING_REVIEW) {
            throw new \Exception('Cannot cancel: payout is already being processed.');
        }

        $request->delete();
    }

    /**
     * Get payout requests for an organizer.
     */
    public function getPayoutRequests(OrganizerProfile $organizer, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $organizer->payoutRequests()
            ->with(['payoutMethod', 'reviewer', 'approver'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }
}
