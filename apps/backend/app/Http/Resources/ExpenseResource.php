<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    protected bool $includeInstallments = true;

    public function withoutInstallments(): self
    {
        $this->includeInstallments = false;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $purchaseDateStr = $this->purchase_date instanceof Carbon
            ? $this->purchase_date->format('Y-m-d')
            : substr((string) $this->purchase_date, 0, 10);

        $data = [
            'id' => (string) $this->id,
            'coupleId' => (string) $this->couple_id,
            'paidByUserId' => (string) $this->paid_by_user_id,
            'description' => $this->description,
            'totalAmount' => (float) $this->total_amount,
            'installmentsCount' => (int) $this->installments_count,
            'isShared' => (bool) $this->is_shared,
            'splitMode' => $this->split_mode ?? 'DEFAULT',
            'customUser1Quota' => $this->custom_user1_quota !== null ? (float) $this->custom_user1_quota : null,
            'status' => $this->status ?? 'CONFIRMED',
            'approvedByUserId' => $this->approved_by_user_id ? (string) $this->approved_by_user_id : null,
            'approvedAt' => optional($this->approved_at)?->toISOString(),
            'recurringExpenseId' => $this->recurring_expense_id ? (string) $this->recurring_expense_id : null,
            'categoryId' => $this->category_id ? (string) $this->category_id : null,
            'purchaseDate' => $purchaseDateStr,
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

        if ($this->includeInstallments && $this->relationLoaded('installments')) {
            $data['installments'] = $this->installments->map(
                fn ($i) => (new InstallmentResource($i))->withoutExpense()->toArray($request)
            )->values()->all();
        }

        return $data;
    }
}
