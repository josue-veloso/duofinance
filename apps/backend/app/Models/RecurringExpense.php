<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'couple_id',
        'paid_by_user_id',
        'description',
        'total_amount',
        'installments_count',
        'is_shared',
        'category_id',
        'day_of_month',
        'start_month',
        'end_month',
        'frequency',
        'is_active',
        'last_generated_month',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'installments_count' => 'integer',
        'is_shared' => 'boolean',
        'day_of_month' => 'integer',
        'start_month' => 'date',
        'end_month' => 'date',
        'is_active' => 'boolean',
        'last_generated_month' => 'date',
    ];

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function toFrontendArray(): array
    {
        $startMonthStr = $this->start_month instanceof Carbon
            ? $this->start_month->format('Y-m')
            : substr((string) $this->start_month, 0, 7);

        $endMonthStr = null;
        if ($this->end_month) {
            $endMonthStr = $this->end_month instanceof Carbon
                ? $this->end_month->format('Y-m')
                : substr((string) $this->end_month, 0, 7);
        }

        $lastGeneratedMonthStr = null;
        if ($this->last_generated_month) {
            $lastGeneratedMonthStr = $this->last_generated_month instanceof Carbon
                ? $this->last_generated_month->format('Y-m')
                : substr((string) $this->last_generated_month, 0, 7);
        }

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
            'startMonth' => $startMonthStr,
            'endMonth' => $endMonthStr,
            'frequency' => $this->frequency,
            'isActive' => (bool) $this->is_active,
            'lastGeneratedMonth' => $lastGeneratedMonthStr,
            'createdAt' => optional($this->created_at)?->toISOString(),
            'updatedAt' => optional($this->updated_at)?->toISOString(),
            'paidByUser' => $this->paidByUser ? [
                'id' => (string) $this->paidByUser->id,
                'name' => $this->paidByUser->name,
                'email' => $this->paidByUser->email,
                'createdAt' => optional($this->paidByUser->created_at)?->toISOString(),
            ] : null,
            'category' => $this->category ? $this->category->toFrontendArray() : null,
        ];
    }
}
