<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'comanda_id', 'product_id', 'combo_id', 'product_name',
        'quantity', 'unit_price', 'subtotal', 'delivered_quantity', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'delivered_quantity' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function isDelivered(): bool
    {
        return $this->delivered_quantity >= $this->quantity;
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
