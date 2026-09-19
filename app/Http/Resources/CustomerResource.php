<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'note' => $this->note,
            'balance' => (float) $this->balance,
            'is_debtor' => $this->isDebtor(),
            'open_debts_count' => $this->whenHas('open_debts_count', fn () => (int) $this->open_debts_count),
            'overdue_debts_count' => $this->whenHas('overdue_debts_count', fn () => (int) $this->overdue_debts_count),
            'client_uuid' => $this->client_uuid,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
