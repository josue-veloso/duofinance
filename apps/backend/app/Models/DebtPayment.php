<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'couple_id',
        'payer_id',
        'receiver_id',
        'amount',
        'month',
        'note',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'month' => 'date',
        'created_at' => 'datetime',
    ];

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function toFrontendArray(): array
    {
        $monthStr = null;
        if ($this->month) {
            $monthStr = $this->month instanceof Carbon
                ? $this->month->format('Y-m')
                : substr((string) $this->month, 0, 7);
        }

        return [
            'id' => (string) $this->id,
            'coupleId' => (string) $this->couple_id,
            'payerId' => (string) $this->payer_id,
            'receiverId' => (string) $this->receiver_id,
            'amount' => (float) $this->amount,
            'month' => $monthStr,
            'note' => $this->note,
            'createdAt' => optional($this->created_at)?->toISOString(),
            'payer' => $this->relationLoaded('payer') && $this->payer ? [
                'id' => (string) $this->payer->id,
                'name' => $this->payer->name,
                'email' => $this->payer->email,
                'createdAt' => optional($this->payer->created_at)?->toISOString(),
            ] : null,
            'receiver' => $this->relationLoaded('receiver') && $this->receiver ? [
                'id' => (string) $this->receiver->id,
                'name' => $this->receiver->name,
                'email' => $this->receiver->email,
                'createdAt' => optional($this->receiver->created_at)?->toISOString(),
            ] : null,
        ];
    }
}
