<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function expensesPaid(): HasMany
    {
        return $this->hasMany(Expense::class, 'paid_by_user_id');
    }

    public function recurringExpensesPaid(): HasMany
    {
        return $this->hasMany(RecurringExpense::class, 'paid_by_user_id');
    }

    public function debtPaymentsSent(): HasMany
    {
        return $this->hasMany(DebtPayment::class, 'payer_id');
    }

    public function debtPaymentsReceived(): HasMany
    {
        return $this->hasMany(DebtPayment::class, 'receiver_id');
    }

    public function currentCouple(): ?Couple
    {
        return Couple::query()
            ->where('user1_id', $this->id)
            ->orWhere('user2_id', $this->id)
            ->first();
    }
}
