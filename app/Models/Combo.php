<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $image
 * @property string $sale_price
 * @property int|null $round_bs
 * @property bool $is_active
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $inventariableComponents
 */
class Combo extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'image',
        'sale_price',
        'round_bs',
        'is_active',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'round_bs' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsToMany<Product, $this, ComboProduct, 'pivot'>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'combo_product')
            ->using(ComboProduct::class)
            ->withPivot('quantity');
    }

    /**
     * @return BelongsToMany<Product, $this, ComboProduct, 'pivot'>
     */
    public function inventariableComponents(): BelongsToMany
    {
        return $this->products()
            ->whereIn('control_type', ['inventariable', 'produccion']);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
