<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'couple_id',
        'name',
        'color',
        'icon',
    ];

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function toFrontendArray(): array
    {
        return [
            'id' => (string) $this->id,
            'coupleId' => (string) $this->couple_id,
            'name' => $this->name,
            'color' => $this->color,
            'icon' => $this->icon,
        ];
    }
}
