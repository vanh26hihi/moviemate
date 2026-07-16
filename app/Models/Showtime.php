<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Showtime extends Model
{
    protected $fillable = [
        'movie_id',
        'cinema_id',
        'room_id',
        'show_date',
        'show_time',
        'price',
        'vip_price',
        'status',
    ];

    protected $casts = [
        'show_date' => 'date',
        'price'     => 'decimal:2',
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}