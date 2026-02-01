<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Services\PaymentService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private PaystackService $paystackService
    ) {}

    /**
     * List available subscription plans (from config).
     *
     * GET /api/subscriptions/plans
     */
    public function plans(): JsonResponse
    {
        $plans = collect(config('paystack.plans'))->map(function ($plan, $code) {
            return [
                'code' => $code,
                'name' => $plan['name'],
                'description' => $plan['description'] ?? null,
                'amount' => $plan['amount'],
                'currency' => $plan['currency'] ?? 'USD',
                'formatted_amount' => $plan['amount'] === 0
                    ? 'Free'
                    : '$' . number_format($plan['amount'] / 100, 2) . '/month',
                'interval' => $plan['interval'] ?? 'monthly',
                'tournaments_limit' => $plan['tournaments_limit'] ?? null,
                'players_limit' => $plan['players_limit'] ?? null,
                'is_unlimited_tournaments' => is_null($plan['tournaments_limit'] ?? null),
                'is_unlimited_players' => is_null($plan['players_limit'] ?? null),
                'can_collect_entry_fee' => $plan['can_collect_entry_fee'] ?? false,
                'entry_fee_percentage' => $plan['entry_fee_percentage'] ?? 0,
                'entry_fee_flat' => $plan['entry_fee_flat'] ?? 0,
                'show_branding' => $plan['show_branding'] ?? true,
                'organizer_accounts' => $plan['organizer_accounts'] ?? 1,
                'is_popular' => $plan['is_popular'] ?? false,
                'features' => $plan['features'] ?? [],
                'limitations' => $plan['limitations'] ?? [],
            ];
        })->values();

        return response()->json([
            'plans' => $plans,
        ]);
    }

    /**
     * Get current user's subscription.
     *
     * GET /api/subscriptions/current
     */
    public function current(Request $request): JsonResponse
    {
        $subscription = $request->user()
            ->subscriptions()
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'subscription' => null,
                'message' => 'No subscription found',
            ]);
        }

        return response()->json([
            'subscription' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * Subscribe to a plan.
     *
     * POST /api/subscriptions/subscribe/{planCode}
     */
    public function subscribe(Request $request, string $planCode): JsonResponse
    {
        $user = $request->user();
        $plan = config("paystack.plans.{$planCode}");

        if (!$plan) {
            return response()->json([
                'message' => 'Invalid plan selected',
            ], 422);
        }

        $result = $this->paymentService->initiateSubscriptionPayment($planCode, $user);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        // Free plan - activated immediately
        if ($result['is_free'] ?? false) {
            return response()->json([
                'message' => $result['message'],
                'subscription' => new SubscriptionResource($result['subscription']),
                'is_free' => true,
            ], 201);
        }

        // Paid plan - redirect to payment
        return response()->json([
            'message' => $result['message'],
            'authorization_url' => $result['authorization_url'],
            'reference' => $result['payment']->reference,
            'is_free' => false,
        ]);
    }

    /**
     * Cancel current subscription.
     *
     * POST /api/subscriptions/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $subscription = $request->user()
            ->subscriptions()
            ->active()
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription to cancel',
            ], 422);
        }

        // Cancel on Paystack if subscription code exists
        if ($subscription->paystack_subscription_code && $subscription->paystack_email_token) {
            $this->paystackService->disableSubscription(
                $subscription->paystack_subscription_code,
                $subscription->paystack_email_token
            );
        }

        $subscription->cancel($request->input('reason'));

        return response()->json([
            'message' => 'Subscription cancelled successfully',
            'subscription' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * Get subscription history.
     *
     * GET /api/subscriptions/history
     */
    public function history(Request $request): JsonResponse
    {
        $subscriptions = $request->user()
            ->subscriptions()
            ->with('payments')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'subscriptions' => SubscriptionResource::collection($subscriptions),
        ]);
    }

    /**
     * Change subscription plan.
     *
     * POST /api/subscriptions/change/{planCode}
     */
    public function changePlan(Request $request, string $planCode): JsonResponse
    {
        $user = $request->user();
        $plan = config("paystack.plans.{$planCode}");

        if (!$plan) {
            return response()->json([
                'message' => 'Invalid plan selected',
            ], 422);
        }

        $currentSubscription = $user->subscriptions()->active()->first();

        if (!$currentSubscription) {
            // No active subscription, just subscribe to new plan
            return $this->subscribe($request, $planCode);
        }

        if ($currentSubscription->plan_code === $planCode) {
            return response()->json([
                'message' => 'You are already on this plan',
            ], 422);
        }

        // Cancel current and create new
        $currentSubscription->cancel('Changed to ' . $plan['name']);

        $result = $this->paymentService->initiateSubscriptionPayment($planCode, $user);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        // Free plan
        if ($result['is_free'] ?? false) {
            return response()->json([
                'message' => 'Plan changed successfully',
                'subscription' => new SubscriptionResource($result['subscription']),
            ]);
        }

        return response()->json([
            'message' => 'Plan change initiated',
            'authorization_url' => $result['authorization_url'],
            'reference' => $result['payment']->reference,
        ]);
    }

    /**
     * Check if user can host more tournaments.
     *
     * GET /api/subscriptions/can-host
     */
    public function canHost(Request $request): JsonResponse
    {
        $subscription = $request->user()
            ->subscriptions()
            ->active()
            ->first();

        if (!$subscription) {
            return response()->json([
                'can_host' => false,
                'message' => 'No active subscription',
                'subscription' => null,
            ]);
        }

        $canHost = $subscription->canHostMoreTournaments();

        return response()->json([
            'can_host' => $canHost,
            'tournaments_used' => $subscription->tournaments_used,
            'tournaments_limit' => $subscription->tournaments_limit,
            'remaining' => $subscription->remaining_tournaments,
            'subscription' => new SubscriptionResource($subscription),
        ]);
    }
}
