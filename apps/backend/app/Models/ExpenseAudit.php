<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAudit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'expense_id',
        'couple_id',
        'actor_user_id',
        'action',
        'before',
        'after',
        'created_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => (string) $this->id,
            'expenseId' => (string) $this->expense_id,
            'coupleId' => (string) $this->couple_id,
            'actorUserId' => (string) $this->actor_user_id,
            'action' => $this->action,
            'before' => $this->before,
            'after' => $this->after,
            'createdAt' => optional($this->created_at)?->toISOString(),
            'actorUser' => $this->actorUser ? [
                'id' => (string) $this->actorUser->id,
                'name' => $this->actorUser->name,
                'email' => $this->actorUser->email,
                'createdAt' => optional($this->actorUser->created_at)?->toISOString(),
            ] : null,
        ];
    }
}
