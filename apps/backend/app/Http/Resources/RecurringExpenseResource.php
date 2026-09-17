<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'coupleId' => (string) $this->couple_id,
            'paidByUserId' => (string) $this->paid_by_user_id,
            'description' => $this->description,
            'totalAmount' => (float) $this->total_amount,
            'installmentsCount' => (int) $this->installments_count,
            'isShared' => (bool) $this->is_shared,
            'categoryId' => $this->category_id ? (string) $this->category_id : null,
            'dayOfMonth' => (int) $this->day_of_month,
            'startMonth' => substr((string) $this->start_month, 0, 7),
            'endMonth' => $this->end_month ? substr((string) $this->end_month, 0, 7) : null,
            'frequency' => $this->frequency ?? 'MONTHLY',
            'isActive' => (bool) $this->is_active,
            'lastGeneratedMonth' => $this->last_generated_month ? substr((string) $this->last_generated_month, 0, 7) : null,
            'createdAt' => optional($this->created_at)?->toISOString(),
            'paidByUser' => $this->relationLoaded('paidByUser') && $this->paidByUser ? [
                'id' => (string) $this->paidByUser->id,
                'name' => $this->paidByUser->name,
                'email' => $this->paidByUser->email,
                'createdAt' => optional($this->paidByUser->created_at)?->toISOString(),
            ] : null,
            'category' => $this->relationLoaded('category') && $this->category
                ? (new CategoryResource($this->category))->toArray($request)
                : null,
        ];
    }
}
