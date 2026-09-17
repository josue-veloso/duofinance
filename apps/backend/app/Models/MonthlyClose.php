<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyClose extends Model
{
    use HasFactory;

    protected $fillable = [
        'couple_id',
        'month',
        'total_shared',
        'user1_paid',
        'user2_paid',
        'verdict_amount',
        'verdict_payer_id',
        'verdict_receiver_id',
        'closed_at',
    ];

    protected $casts = [
        'month' => 'date:Y-m-d',
        'total_shared' => 'float',
        'user1_paid' => 'float',
        'user2_paid' => 'float',
        'verdict_amount' => 'float',
        'closed_at' => 'datetime',
    ];

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function verdictPayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verdict_payer_id');
    }

    public function verdictReceiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verdict_receiver_id');
    }

    public function toFrontendArray(): array
    {
        $monthStr = $this->month instanceof Carbon
            ? $this->month->format('Y-m')
            : substr((string) $this->month, 0, 7);

        return [
            'id' => (string) $this->id,
            'coupleId' => (string) $this->couple_id,
            'month' => $monthStr,
            'totalShared' => (float) $this->total_shared,
            'user1Paid' => (float) $this->user1_paid,
            'user2Paid' => (float) $this->user2_paid,
            'verdictAmount' => (float) $this->verdict_amount,
            'verdictPayerId' => $this->verdict_payer_id ? (string) $this->verdict_payer_id : null,
            'verdictReceiverId' => $this->verdict_receiver_id ? (string) $this->verdict_receiver_id : null,
            'closedAt' => optional($this->closed_at)?->toISOString(),
            'createdAt' => optional($this->created_at)?->toISOString(),
            'updatedAt' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
