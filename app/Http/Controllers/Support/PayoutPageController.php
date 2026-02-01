<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Enums\PayoutStatus;
use App\Services\PayoutApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutPageController extends Controller
{
    public function __construct(
        private PayoutApprovalService $approvalService
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->input('status', 'pending_review');

        $query = PayoutRequest::with([
            'organizerProfile.user',
            'payoutMethod',
            'reviewer',
        ])
        ->when($request->search, function ($query, $search) {
            $query->whereHas('organizerProfile', fn($q) =>
                $q->where('organization_name', 'like', "%{$search}%")
            )->orWhereHas('organizerProfile.user', fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            );
        })
        ->when($status !== 'all', function ($query) use ($status) {
            $query->where('status', $status);
        })
        ->orderBy('created_at', 'asc')
        ->paginate(15)
        ->withQueryString();

        return Inertia::render('Support/Payouts/Index', [
            'payouts' => [
                'data' => $query->map(fn($payout) => $this->formatPayout($payout)),
                'current_page' => $query->currentPage(),
                'last_page' => $query->lastPage(),
                'per_page' => $query->perPage(),
                'total' => $query->total(),
            ],
            'filters' => [
                'search' => $request->search,
                'status' => $status,
            ],
            'stats' => [
                'pending_review' => PayoutRequest::where('status', PayoutStatus::PENDING_REVIEW)->count(),
                'support_confirmed' => PayoutRequest::where('status', PayoutStatus::SUPPORT_CONFIRMED)->count(),
            ],
        ]);
    }

    public function show(PayoutRequest $payout): Response
    {
        $payout->load([
            'organizerProfile.user',
            'organizerProfile.wallet',
            'payoutMethod',
            'reviewer',
            'approver',
            'rejector',
        ]);

        // Get recent tournaments by this organizer
        $recentTournaments = $payout->organizerProfile->user->tournaments()
            ->select('id', 'name', 'status', 'entry_fee', 'participants_count', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Support/Payouts/Show', [
            'payout' => $this->formatPayoutDetailed($payout),
            'recentTournaments' => $recentTournaments,
        ]);
    }

    public function confirm(Request $request, PayoutRequest $payout): RedirectResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->approvalService->supportConfirm($payout, $request->user(), $request->notes);

            return redirect()
                ->route('support.payouts.index')
                ->with('success', 'Payout confirmed and sent to admin for approval.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, PayoutRequest $payout): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->approvalService->reject($payout, $request->user(), $request->reason);

            return redirect()
                ->route('support.payouts.index')
                ->with('success', 'Payout request rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function formatPayout(PayoutRequest $payout): array
    {
        return [
            'id' => $payout->id,
            'amount' => $payout->amount,
            'formatted_amount' => $payout->formatted_amount,
            'currency' => $payout->currency,
            'status' => $payout->status->value,
            'status_label' => $payout->status->label(),
            'payout_method' => $payout->payoutMethod ? [
                'id' => $payout->payoutMethod->id,
                'type' => $payout->payoutMethod->type->value,
                'type_label' => $payout->payoutMethod->type->label(),
                'account_name' => $payout->payoutMethod->account_name,
                'account_number_masked' => $payout->payoutMethod->masked_account_number,
                'display_name' => $payout->payoutMethod->display_name,
            ] : null,
            'organizer' => $payout->organizerProfile ? [
                'id' => $payout->organizerProfile->id,
                'organization_name' => $payout->organizerProfile->organization_name,
                'user' => $payout->organizerProfile->user ? [
                    'id' => $payout->organizerProfile->user->id,
                    'name' => $payout->organizerProfile->user->name,
                    'email' => $payout->organizerProfile->user->email,
                ] : null,
            ] : null,
            'review_notes' => $payout->review_notes,
            'created_at' => $payout->created_at->toISOString(),
        ];
    }

    protected function formatPayoutDetailed(PayoutRequest $payout): array
    {
        $data = $this->formatPayout($payout);

        // Add detailed organizer info
        if ($payout->organizerProfile) {
            $data['organizer']['phone'] = $payout->organizerProfile->phone;

            if ($payout->organizerProfile->wallet) {
                $data['organizer']['wallet'] = [
                    'balance' => $payout->organizerProfile->wallet->balance,
                    'formatted_balance' => $payout->organizerProfile->wallet->formatted_balance,
                    'total_earned' => $payout->organizerProfile->wallet->total_earned,
                    'total_withdrawn' => $payout->organizerProfile->wallet->total_withdrawn,
                ];
            }
        }

        // Add payout method verification status
        if ($payout->payoutMethod) {
            $data['payout_method']['is_verified'] = $payout->payoutMethod->is_verified;
        }

        // Add review info
        $data['reviewed_by'] = $payout->reviewer ? [
            'id' => $payout->reviewer->id,
            'name' => $payout->reviewer->name,
        ] : null;
        $data['reviewed_at'] = $payout->reviewed_at?->toISOString();

        // Add approval info
        $data['approved_by'] = $payout->approver ? [
            'id' => $payout->approver->id,
            'name' => $payout->approver->name,
        ] : null;
        $data['approved_at'] = $payout->approved_at?->toISOString();

        // Add rejection info
        $data['rejection_reason'] = $payout->rejection_reason;
        $data['rejected_at'] = $payout->rejected_at?->toISOString();

        // Add payment info
        $data['paid_at'] = $payout->paid_at?->toISOString();
        $data['payment_reference'] = $payout->payment_reference;

        $data['updated_at'] = $payout->updated_at->toISOString();

        return $data;
    }
}
