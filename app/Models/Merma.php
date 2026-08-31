<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Merma extends Model
{
    use HasFactory;
    protected $fillable = ['product_id', 'user_id', 'quantity', 'reason', 'notes'];

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

    public function getReasonLabelAttribute(): string
    {
        return match($this->reason) {
            'vencido' => 'Vencido',
            'danado' => 'Dañado',
            'otro' => 'Otro',
            default => ucfirst($this->reason ?? ''),
        };
    }
}
