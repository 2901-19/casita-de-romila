<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Merma extends Model
{
    use HasFactory;
    protected $fillable = ['product_id', 'user_id', 'quantity', 'cost', 'reason', 'type', 'notes'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cost' => 'decimal:2',
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

    public function isConsumption(): bool
    {
        return $this->type === 'consumo';
    }

    public function scopeMermaType(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('type')->orWhere('type', 'merma'));
    }

    public function scopeConsumption(Builder $query): Builder
    {
        return $query->where('type', 'consumo');
    }

    public function scopeInRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);
    }

    public function subtotal(): ?float
    {
        if (! $this->isConsumption() || $this->cost === null) {
            return null;
        }

        return round((float) $this->quantity * (float) $this->cost, 2);
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->isConsumption() ? 'Consumo interno' : 'Merma';
    }

    public function getTypeBadgeAttribute(): string
    {
        return $this->isConsumption() ? 'info' : 'danger';
    }

    public function getReasonLabelAttribute(): string
    {
        if ($this->isConsumption()) {
            return match ($this->reason) {
                'autoconsumo' => 'Consumo del dueño',
                'otro' => 'Consumo del dueño',
                default => ucfirst($this->reason ?? ''),
            };
        }

        return match ($this->reason) {
            'vencido' => 'Vencido',
            'danado' => 'Dañado',
            'otro' => 'Otro',
            default => ucfirst($this->reason ?? ''),
        };
    }
}
