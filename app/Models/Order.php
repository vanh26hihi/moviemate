<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'user_id', 'customer_name', 'customer_phone', 'customer_email',
        'pickup_cinema_id', 'subtotal', 'total_amount', 'status',
    ];

    protected $casts = [
        'booking_id' => 'integer',
        'subtotal' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function pickupCinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class, 'pickup_cinema_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('food_order', $this->status);
    }
}
