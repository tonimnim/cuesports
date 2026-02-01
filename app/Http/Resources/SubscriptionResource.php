<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => [
                'code' => $this->plan_code,
                'name' => $this->plan_name,
                'amount' => $this->plan_amount,
                'formatted_amount' => $this->formatted_amount,
                'features' => $this->features,
                'limitations' => $this->limitations,
            ],
            'status' => [
                'value' => $this->status,
                'label' => ucfirst(str_replace('_', ' ', $this->status)),
                'is_active' => $this->isActive(),
                'is_cancelled' => $this->isCancelled(),
                'is_expired' => $this->isExpired(),
            ],
            'usage' => [
                'tournaments_used' => $this->tournaments_used,
                'tournaments_limit' => $this->tournaments_limit,
                'tournaments_unlimited' => $this->is_unlimited_tournaments,
                'remaining' => $this->remaining_tournaments,
                'can_host_more' => $this->canHostMoreTournaments(),
            ],
            'capabilities' => [
                'can_collect_entry_fees' => $this->canCollectEntryFees(),
                'players_limit' => $this->players_limit,
                'players_unlimited' => $this->is_unlimited_players,
                'organizer_accounts' => $this->organizer_accounts,
                'show_branding' => $this->show_branding,
            ],
            'billing' => [
                'current_period_start' => $this->current_period_start?->toISOString(),
                'current_period_end' => $this->current_period_end?->toISOString(),
                'days_remaining' => $this->days_remaining,
            ],
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
