<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonthlyCloseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'coupleId' => (string) $this->couple_id,
            'month' => substr((string) $this->month, 0, 7),
            'totalShared' => (float) $this->total_shared,
            'user1Paid' => (float) $this->user1_paid,
            'user2Paid' => (float) $this->user2_paid,
            'verdictAmount' => (float) $this->verdict_amount,
            'verdictPayerId' => $this->verdict_payer_id ? (string) $this->verdict_payer_id : null,
            'verdictReceiverId' => $this->verdict_receiver_id ? (string) $this->verdict_receiver_id : null,
            'closedAt' => optional($this->closed_at)?->toISOString(),
            'createdAt' => optional($this->created_at)?->toISOString(),
            'updatedAt' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
