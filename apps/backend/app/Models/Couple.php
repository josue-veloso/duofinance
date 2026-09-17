<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Couple extends Model
{
    use HasFactory;

    protected $fillable = [
        'user1_id',
        'user2_id',
        'user1_quota',
        'user2_quota',
    ];

    protected $casts = [
        'user1_quota' => 'float',
        'user2_quota' => 'float',
    ];

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function recurringExpenses(): HasMany
    {
        return $this->hasMany(RecurringExpense::class);
    }

    public function expenseAudits(): HasMany
    {
        return $this->hasMany(ExpenseAudit::class);
    }

    public function monthlyCloses(): HasMany
    {
        return $this->hasMany(MonthlyClose::class);
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }
}
