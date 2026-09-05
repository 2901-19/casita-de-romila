<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
        'image',
        'control_type',
        'cost_price',
        'margin_percent',
        'sale_price',
        'price_override',
        'round_bs',
        'stock_min',
        'stock_current',
        'schedule',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'description' => 'string',
            'control_type' => 'string',
            'cost_price' => 'decimal:2',
            'margin_percent' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'price_override' => 'boolean',
            'round_bs' => 'integer',
            'stock_min' => 'integer',
            'stock_current' => 'integer',
            'schedule' => 'string',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
