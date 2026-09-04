<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'comanda_id', 'amount', 'method', 'customer_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            'efectivo' => 'Efectivo',
            'biopago' => 'Biopago',
            'pago_movil' => 'Pago Móvil',
            'pdv' => 'PDV',
            'credito' => 'Crédito',
            default => $this->method,
        };
    }
}
