<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'couple_id',
        'paid_by_user_id',
        'recurring_expense_id',
        'description',
        'total_amount',
        'installments_count',
        'is_shared',
        'split_mode',
        'custom_user1_quota',
        'status',
        'approved_by_user_id',
        'approved_at',
        'category_id',
        'purchase_date',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'installments_count' => 'integer',
        'is_shared' => 'boolean',
        'custom_user1_quota' => 'float',
        'approved_at' => 'datetime',
        'purchase_date' => 'date',
    ];

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('installment_number', 'asc');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ExpenseAudit::class)->orderBy('created_at', 'desc');
    }

    public function toFrontendArray(bool $includeInstallments = true): array
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
                ? $this->category->toFrontendArray()
                : null,
        ];

        if ($includeInstallments && $this->relationLoaded('installments')) {
            $data['installments'] = $this->installments->map(fn (Installment $i) => $i->toFrontendArray(false))->values()->all();
        }

        return $data;
    }
}
