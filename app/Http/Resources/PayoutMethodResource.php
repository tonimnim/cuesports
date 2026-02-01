<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'provider' => $this->provider,
            'account_name' => $this->account_name,
            'account_number_masked' => $this->masked_account_number,
            'display_name' => $this->display_name,
            'is_default' => $this->is_default,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
