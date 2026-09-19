<?php

namespace App\Http\Resources;

use App\Models\DebtPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DebtPayment
 */
class DebtPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'debt_id' => $this->debt_id,
            'customer_id' => $this->customer_id,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'note' => $this->note,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'client_uuid' => $this->client_uuid,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
