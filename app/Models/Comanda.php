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
        'comanda_number', 'user_id', 'status',
        'customer_name', 'sale_id', 'total',
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

    // Aliases to item-level types, keeping the same constant values for compatibility.
    public const ORDER_DELIVERY = ComandaItem::ORDER_DELIVERY;
    public const ORDER_LOCAL = ComandaItem::ORDER_LOCAL;
    public const ORDER_PARA_LLEVAR = ComandaItem::ORDER_PARA_LLEVAR;

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

    public function payments(): HasMany
    {
        return $this->hasMany(ComandaPayment::class);
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

    public function getTotalBsAttribute(): float
    {
        return (float) $this->total;
    }

    public function getTotalUsdAttribute(): float
    {
        $rate = (float) (ExchangeRate::latest()->first()?->rate ?? 1);
        return $rate > 0 ? round((float) $this->total / $rate, 2) : 0;
    }

    public function hasDeliveryItems(): bool
    {
        return $this->items->contains(fn ($item) => $item->isDelivery());
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

    public function collectedTotal(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function pendingTotal(): float
    {
        return round((float) $this->total - $this->collectedTotal(), 2);
    }

    public function isFullyCollected(): bool
    {
        return $this->payments()->exists()
            && $this->pendingTotal() <= 0;
    }

    public function typeBadges(): array
    {
        return $this->items
            ->pluck('order_type')
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($t) => [
                'label' => (new ComandaItem)->setAttribute('order_type', $t)->order_type_label,
                'badge' => (new ComandaItem)->setAttribute('order_type', $t)->order_type_badge,
            ])
            ->all();
    }
}
