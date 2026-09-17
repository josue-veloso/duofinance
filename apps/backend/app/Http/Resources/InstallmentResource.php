<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    protected bool $includeExpense = true;

    public function withoutExpense(): self
    {
        $this->includeExpense = false;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $dueMonthStr = $this->due_month instanceof Carbon
            ? $this->due_month->format('Y-m-d')
            : (string) $this->due_month;

        $data = [
            'id' => (string) $this->id,
            'expenseId' => (string) $this->expense_id,
            'installmentNumber' => (int) $this->installment_number,
            'amount' => (float) $this->amount,
            'dueMonth' => $dueMonthStr,
            'createdAt' => optional($this->created_at)?->toISOString(),
        ];

        if ($this->includeExpense && $this->relationLoaded('expense') && $this->expense) {
            $data['expense'] = (new ExpenseResource($this->expense))->withoutInstallments()->toArray($request);
        }

        return $data;
    }
}
