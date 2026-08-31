<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'production_id',
        'user_id',
        'type',
        'quantity',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'entrada' ? 'Entrada' : 'Salida';
    }

    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'compra' => 'Compra',
            'merma' => 'Merma',
            'ajuste' => 'Ajuste',
            'venta' => 'Venta',
            'devolucion' => 'Devolución',
            'produccion' => 'Producción',
            default => $this->reason,
        };
    }
}
