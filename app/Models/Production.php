<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Production extends Model
{
    use HasFactory;

    private const UNDO_WINDOW_MINUTES = 20;

    protected $fillable = ['product_id', 'user_id', 'quantity', 'notes'];

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

    public function adjustment(): HasOne
    {
        return $this->hasOne(InventoryAdjustment::class);
    }

    public function isUndoable(): bool
    {
        return $this->created_at->gt(now()->subMinutes(self::UNDO_WINDOW_MINUTES));
    }
}
