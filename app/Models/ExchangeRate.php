<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'rate',
        'source',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSourceLabelAttribute(): string
    {
        return match($this->source) {
            'bcv' => 'Oficial',
            'paralelo' => 'Paralelo',
            'binance' => 'Binance USDT',
            'enzona' => 'EnZona',
            'manual' => 'Manual',
            default => ucfirst($this->source ?? ''),
        };
    }
}
