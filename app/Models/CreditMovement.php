<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditMovement extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'sale_id', 'user_id', 'type', 'amount', 'rate', 'notes'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'cargo' => 'Cargo',
            'abono' => 'Abono',
            'pago' => 'Pago',
            default => ucfirst($this->type ?? ''),
        };
    }
}
