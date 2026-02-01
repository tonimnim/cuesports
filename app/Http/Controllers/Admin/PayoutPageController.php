<?php

namespace App\Http\Controllers\Admin;

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
        $status = $request->input('status', 'support_confirmed');

        $query = PayoutRequest::with([
            'organizerProfile.user',
            'payoutMethod',
            'reviewer',
            'approver',
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

        return Inertia::render('Admin/Payouts/Index', [
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
                'pending_approval' => PayoutRequest::where('status', PayoutStatus::SUPPORT_CONFIRMED)->count(),
                'processing' => PayoutRequest::where('status', PayoutStatus::PROCESSING)->count(),
                'completed_today' => PayoutRequest::where('status', PayoutStatus::COMPLETED)
                    ->whereDate('paid_at', today())
                    ->count(),
                'total_paid_today' => PayoutRequest::where('status', PayoutStatus::COMPLETED)
                    ->whereDate('paid_at', today())
                    ->sum('amount'),
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

        $recentTournaments = $payout->organizerProfile->user->tournaments()
            ->select('id', 'name', 'status', 'entry_fee', 'participants_count', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Payouts/Show', [
            'payout' => $this->formatPayoutDetailed($payout),
            'recentTournaments' => $recentTournaments,
        ]);
    }

    public function approve(Request $request, PayoutRequest $payout): RedirectResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->approvalService->adminApprove($payout, $request->user(), $request->notes);

            return redirect()
                ->route('admin.payouts.index')
                ->with('success', 'Payout approved and payment initiated.');
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
                ->route('admin.payouts.index')
                ->with('success', 'Payout request rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function process(PayoutRequest $payout): RedirectResponse
    {
        try {
            $this->approvalService->processPayment($payout);

            return back()->with('success', 'Payment processed successfully.');
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
            'reviewed_by' => $payout->reviewer ? [
                'id' => $payout->reviewer->id,
                'name' => $payout->reviewer->name,
            ] : null,
            'reviewed_at' => $payout->reviewed_at?->toISOString(),
            'review_notes' => $payout->review_notes,
            'created_at' => $payout->created_at->toISOString(),
        ];
    }

    protected function formatPayoutDetailed(PayoutRequest $payout): array
    {
        $data = $this->formatPayout($payout);

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

        if ($payout->payoutMethod) {
            $data['payout_method']['is_verified'] = $payout->payoutMethod->is_verified;
        }

        $data['approved_by'] = $payout->approver ? [
            'id' => $payout->approver->id,
            'name' => $payout->approver->name,
        ] : null;
        $data['approved_at'] = $payout->approved_at?->toISOString();
        $data['approval_notes'] = $payout->approval_notes ?? null;

        $data['rejection_reason'] = $payout->rejection_reason;
        $data['rejected_at'] = $payout->rejected_at?->toISOString();

        $data['paid_at'] = $payout->paid_at?->toISOString();
        $data['payment_reference'] = $payout->payment_reference;

        return $data;
    }
}
