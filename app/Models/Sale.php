<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_number', 'user_id', 'customer_id', 'customer_name', 'total', 'status',
        'payment_method',
        'cancel_reason', 'canceled_by', 'canceled_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'canceled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creditMovements(): HasMany
    {
        return $this->hasMany(CreditMovement::class);
    }

    public function getOutstandingUsdAttribute(): float
    {
        $cargos = (float) $this->creditMovements()->where('type', 'cargo')->sum('amount');
        $pagos = (float) $this->creditMovements()->where('type', 'pago')->sum('amount');

        return round($cargos - $pagos, 2);
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function methodAmounts(): \Illuminate\Support\Collection
    {
        if ($this->payment_method === 'credito') {
            return collect([['method' => 'credito', 'amount' => (float) $this->total]]);
        }

        return $this->payments->map(fn ($p) => ['method' => $p->method, 'amount' => (float) $p->amount]);
    }
}
