<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaItem extends Model
{
    use HasFactory;

    public const ORDER_DELIVERY = 'delivery';
    public const ORDER_LOCAL = 'local';
    public const ORDER_PARA_LLEVAR = 'para_llevar';

    protected $fillable = [
        'comanda_id', 'product_id', 'combo_id', 'product_name',
        'order_type', 'note', 'quantity', 'unit_price', 'subtotal',
        'delivered_quantity', 'delivered_at', 'collected',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'delivered_quantity' => 'integer',
            'delivered_at' => 'datetime',
            'collected' => 'boolean',
        ];
    }

    public function isDelivered(): bool
    {
        return $this->delivered_quantity >= $this->quantity;
    }

    public function isDelivery(): bool
    {
        return $this->order_type === self::ORDER_DELIVERY;
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

    public function getOrderTypeBadgeAttribute(): string
    {
        return match ($this->order_type) {
            self::ORDER_DELIVERY => 'info',
            self::ORDER_PARA_LLEVAR => 'warning',
            default => 'muted',
        };
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }
}
