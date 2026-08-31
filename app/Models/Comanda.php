<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comanda extends Model
{
    use HasFactory;

    protected $fillable = [
        'comanda_number', 'user_id', 'status', 'order_type',
        'customer_name', 'notes', 'sale_id', 'total',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'delivered_at' => 'datetime',
        ];
    }

    public const STATUS_MONTADA = 'montada';
    public const STATUS_ENTREGADA = 'entregada';
    public const STATUS_COBRADA = 'cobrada';

    public const ORDER_DELIVERY = 'delivery';
    public const ORDER_LOCAL = 'local';
    public const ORDER_PARA_LLEVAR = 'para_llevar';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComandaItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_MONTADA => 'Montada',
            self::STATUS_ENTREGADA => 'Entregada',
            self::STATUS_COBRADA => 'Cobrada',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_MONTADA => 'warning',
            self::STATUS_ENTREGADA => 'info',
            self::STATUS_COBRADA => 'success',
            default => 'muted',
        };
    }

    public function getOrderTypeLabelAttribute(): string
    {
        return match ($this->order_type) {
            self::ORDER_DELIVERY => 'Delivery',
            self::ORDER_LOCAL => 'Consumo local',
            self::ORDER_PARA_LLEVAR => 'Para llevar',
            default => $this->order_type,
        };
    }

    public function getTotalBsAttribute(): float
    {
        return (float) $this->total;
    }

    public function getTotalUsdAttribute(): float
    {
        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);
        return $rate > 0 ? round((float) $this->total / $rate, 2) : 0;
    }

    public function getIsDeliveryAttribute(): bool
    {
        return $this->order_type === self::ORDER_DELIVERY;
    }

    public function allItemsDelivered(): bool
    {
        return $this->items->isNotEmpty()
            && $this->items->every(fn ($item) => $item->isDelivered());
    }

    public function deliveredItemsCount(): int
    {
        return (int) $this->items->sum('delivered_quantity');
    }
}
