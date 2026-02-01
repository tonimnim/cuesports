<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'source' => $this->source,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'balance_after' => $this->balance_after,
            'currency' => $this->currency,
            'description' => $this->description,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
