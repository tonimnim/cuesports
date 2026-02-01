<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->is_tournament_entry ? 'tournament_entry' : ($this->is_subscription ? 'subscription' : 'other'),
            'description' => $this->getPayableDescription(),
            'amount' => [
                'value' => $this->amount,
                'formatted' => $this->formatted_amount,
                'currency' => $this->currency,
            ],
            'status' => [
                'value' => $this->status,
                'label' => ucfirst($this->status),
            ],
            'payment_method' => $this->when($this->isSuccessful(), [
                'channel' => $this->channel,
                'display' => $this->payment_method,
            ]),
            'payable' => $this->when($this->relationLoaded('payable'), function () {
                if ($this->is_tournament_entry) {
                    return [
                        'type' => 'tournament',
                        'id' => $this->payable->id,
                        'name' => $this->payable->name,
                    ];
                }
                if ($this->is_subscription) {
                    return [
                        'type' => 'subscription',
                        'id' => $this->payable->id,
                        'plan' => $this->payable->plan?->name,
                    ];
                }
                return null;
            }),
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
