<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'coupleId' => (string) $this->couple_id,
            'payerId' => (string) $this->payer_id,
            'receiverId' => (string) $this->receiver_id,
            'amount' => (float) $this->amount,
            'note' => $this->note,
            'month' => $this->month ? substr((string) $this->month, 0, 7) : null,
            'createdAt' => optional($this->created_at)?->toISOString(),
            'payer' => $this->relationLoaded('payer') && $this->payer ? [
                'id' => (string) $this->payer->id,
                'name' => $this->payer->name,
                'email' => $this->payer->email,
            ] : null,
            'receiver' => $this->relationLoaded('receiver') && $this->receiver ? [
                'id' => (string) $this->receiver->id,
                'name' => $this->receiver->name,
                'email' => $this->receiver->email,
            ] : null,
        ];
    }
}
