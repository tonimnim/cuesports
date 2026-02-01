<?php

namespace App\Services;

use App\Models\PayoutRequest;
use App\Models\PayoutMethod;
use App\Models\User;
use App\Enums\PayoutStatus;
use App\Enums\PayoutMethodType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class PayoutApprovalService
{
    public function __construct(
        protected DarajaService $daraja
    ) {}

    /**
     * Get payout requests for support review.
     */
    public function getPayoutsForSupportReview(int $perPage = 20): LengthAwarePaginator
    {
        return PayoutRequest::with(['organizerProfile.user', 'payoutMethod'])
            ->where('status', PayoutStatus::PENDING_REVIEW)
            ->orderBy('created_at', 'asc') // Oldest first
            ->paginate($perPage);
    }

    /**
     * Get payout requests for admin approval.
     */
    public function getPayoutsForAdminApproval(int $perPage = 20): LengthAwarePaginator
    {
        return PayoutRequest::with(['organizerProfile.user', 'payoutMethod', 'reviewer'])
            ->where('status', PayoutStatus::SUPPORT_CONFIRMED)
            ->orderBy('reviewed_at', 'asc') // Oldest first
            ->paginate($perPage);
    }

    /**
     * Get all payout requests with filters (for admin dashboard).
     */
    public function getAllPayouts(?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = PayoutRequest::with(['organizerProfile.user', 'payoutMethod', 'reviewer', 'approver'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Support confirms a payout request.
     */
    public function supportConfirm(PayoutRequest $payout, User $support, ?string $notes = null): void
    {
        if (!$payout->status->canBeReviewedBySupport()) {
            throw new \Exception('This payout cannot be reviewed at this stage.');
        }

        $payout->confirmBySupport($support, $notes);

        Log::info('Payout confirmed by support', [
            'payout_id' => $payout->id,
            'support_id' => $support->id,
            'amount' => $payout->amount,
        ]);
    }

    /**
     * Admin approves a payout request.
     */
    public function adminApprove(PayoutRequest $payout, User $admin, ?string $notes = null): void
    {
        if (!$payout->status->canBeApprovedByAdmin()) {
            throw new \Exception('This payout must be confirmed by support first.');
        }

        DB::transaction(function () use ($payout, $admin, $notes) {
            $payout->approveByAdmin($admin, $notes);

            $organizerProfile = $payout->organizerProfile;

            // Debit the organizer's wallet
            $wallet = $organizerProfile->wallet;
            if ($wallet) {
                $wallet->debit(
                    amount: $payout->amount,
                    source: 'payout',
                    description: "Payout request #{$payout->id}",
                    reference: $payout
                );
            }

            // Also sync organizer profile totals
            $organizerProfile->decrement('available_balance', $payout->amount);
            $organizerProfile->increment('total_withdrawn', $payout->amount);
        });

        Log::info('Payout approved by admin', [
            'payout_id' => $payout->id,
            'admin_id' => $admin->id,
            'amount' => $payout->amount,
        ]);

        // Trigger payment processing (can be async)
        $this->processPayment($payout);
    }

    /**
     * Reject a payout request (support or admin).
     */
    public function reject(PayoutRequest $payout, User $rejector, string $reason): void
    {
        if ($payout->status->isFinal()) {
            throw new \Exception('This payout has already been finalized.');
        }

        $payout->reject($rejector, $reason);

        Log::info('Payout rejected', [
            'payout_id' => $payout->id,
            'rejector_id' => $rejector->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Process the actual payment via M-Pesa B2C.
     */
    public function processPayment(PayoutRequest $payout): void
    {
        if (!$payout->status->canBeProcessed()) {
            throw new \Exception('Payout must be approved before processing.');
        }

        $payout->markAsProcessing();

        try {
            $method = $payout->payoutMethod;

            // Only M-Pesa supported for now
            if ($method->type !== PayoutMethodType::MPESA) {
                throw new \Exception('Only M-Pesa payouts are supported at this time.');
            }

            // Amount is stored in cents, M-Pesa uses whole KES
            $amountInKes = (int) ($payout->amount / 100);

            // Initiate B2C transfer
            $response = $this->daraja->b2cPayment(
                phoneNumber: $method->account_number,
                amount: $amountInKes,
                remarks: "Payout #{$payout->id}",
                occasion: $payout->organizerProfile->organization_name
            );

            if ($response['success'] && isset($response['data']['conversation_id'])) {
                // Store conversation ID for callback matching
                $payout->update([
                    'mpesa_conversation_id' => $response['data']['conversation_id'],
                    'mpesa_originator_conversation_id' => $response['data']['originator_conversation_id'],
                ]);

                Log::info('M-Pesa B2C payout initiated', [
                    'payout_id' => $payout->id,
                    'conversation_id' => $response['data']['conversation_id'],
                ]);

                // Note: Payout will be marked as completed by the B2C callback
                // For now, keep it in PROCESSING status
            } else {
                throw new \Exception($response['message'] ?? 'B2C transfer failed');
            }
        } catch (\Exception $e) {
            Log::error('Payout processing failed', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);

            // Reset to approved so admin can retry
            $payout->update([
                'status' => PayoutStatus::ADMIN_APPROVED,
                'failure_reason' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get dashboard stats.
     */
    public function getDashboardStats(): array
    {
        return [
            'pending_review' => PayoutRequest::where('status', PayoutStatus::PENDING_REVIEW)->count(),
            'pending_approval' => PayoutRequest::where('status', PayoutStatus::SUPPORT_CONFIRMED)->count(),
            'processing' => PayoutRequest::where('status', PayoutStatus::PROCESSING)->count(),
            'completed_today' => PayoutRequest::where('status', PayoutStatus::COMPLETED)
                ->whereDate('paid_at', today())
                ->count(),
            'total_paid_today' => PayoutRequest::where('status', PayoutStatus::COMPLETED)
                ->whereDate('paid_at', today())
                ->sum('amount'),
        ];
    }
}
