<?php

namespace App\Models;

use App\Domain\Money\VndAmount;
use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Showtime extends Model
{
    public const MAX_PRICE = 99_999_999;

    public function priceForSeatType(string $seatType): int
    {
        $basePrice = VndAmount::fromDatabase($this->getRawOriginal('price') ?? $this->price);
        $vipPrice = $this->vip_price === null
            ? $basePrice
            : VndAmount::fromDatabase($this->getRawOriginal('vip_price') ?? $this->vip_price);

        return match ($seatType) {
            'vip' => $vipPrice->value(),
            'couple' => $basePrice->multiply(config('booking.couple_price_multiplier', 2))->value(),
            default => $basePrice->value(),
        };
    }

    protected $fillable = [
        'movie_id',
        'cinema_id',
        'room_id',
        'room_layout_id',
        'show_date',
        'show_time',
        'price',
        'vip_price',
        'status',
    ];

    protected $casts = [
        'show_date' => 'date',
        'price' => 'decimal:2',
        'vip_price' => 'decimal:2',
    ];

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomLayout(): BelongsTo
    {
        return $this->belongsTo(RoomLayout::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('showtime', $this->status);
    }
}
