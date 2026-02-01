<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'balance' => $this->balance,
            'formatted_balance' => $this->formatted_balance,
            'pending_balance' => $this->pending_balance,
            'total_earned' => $this->total_earned,
            'total_withdrawn' => $this->total_withdrawn,
            'currency' => $this->currency,
            'recent_transactions' => WalletTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}
