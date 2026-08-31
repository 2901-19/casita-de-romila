<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'balance', 'credit_limit_type', 'credit_limit_amount', 'is_active'];
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CreditMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function hasDefinedLimit(): bool
    {
        return $this->credit_limit_type === 'monto';
    }

    public function availableCredit(): float
    {
        if (! $this->hasDefinedLimit()) {
            return INF;
        }

        return round((float) $this->credit_limit_amount + (float) $this->balance, 2);
    }

    public function getBalanceInDebtAttribute(): bool
    {
        return $this->balance < 0;
    }

    public function getBalanceInFavorAttribute(): bool
    {
        return $this->balance > 0;
    }
}
