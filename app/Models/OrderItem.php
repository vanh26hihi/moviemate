<?php

namespace App\Models;

use App\Services\Tickets\TicketArtifactProvisioner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'food_item_id', 'quantity', 'snapshot_name',
        'unit_price', 'line_total', 'price', 'total',
    ];

    protected $casts = [
        'unit_price' => 'integer',
        'line_total' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (OrderItem $item): void {
            app(TicketArtifactProvisioner::class)->provisionFoodForOrderItem($item);
        });
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class, 'food_item_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
