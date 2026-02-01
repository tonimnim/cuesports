<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'payout_method' => new PayoutMethodResource($this->whenLoaded('payoutMethod')),

            // Organizer info (for support/admin views)
            'organizer' => $this->when($this->relationLoaded('organizerProfile'), fn() => [
                'id' => $this->organizerProfile->id,
                'organization_name' => $this->organizerProfile->organization_name,
                'user' => $this->when($this->organizerProfile->relationLoaded('user'), fn() => [
                    'id' => $this->organizerProfile->user->id,
                    'name' => $this->organizerProfile->user->name,
                    'email' => $this->organizerProfile->user->email,
                ]),
            ]),

            // Review info
            'reviewed_by' => $this->when($this->reviewer, fn() => [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ]),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'review_notes' => $this->review_notes,

            // Approval info
            'approved_by' => $this->when($this->approver, fn() => [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ]),
            'approved_at' => $this->approved_at?->toISOString(),
            'approval_notes' => $this->approval_notes,

            // Rejection info
            'rejected_by' => $this->when($this->rejector, fn() => [
                'id' => $this->rejector->id,
                'name' => $this->rejector->name,
            ]),
            'rejection_reason' => $this->rejection_reason,
            'rejected_at' => $this->rejected_at?->toISOString(),

            // Payment info
            'paid_at' => $this->paid_at?->toISOString(),
            'payment_reference' => $this->payment_reference,

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
