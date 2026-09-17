<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installment extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'installment_number',
        'amount',
        'due_month',
    ];

    protected $casts = [
        'amount' => 'float',
        'due_month' => 'date',
        'installment_number' => 'integer',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function toFrontendArray(bool $includeExpense = true): array
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
        ];

        if ($includeExpense && $this->relationLoaded('expense') && $this->expense) {
            $data['expense'] = $this->expense->toFrontendArray(false);
        }

        return $data;
    }
}
