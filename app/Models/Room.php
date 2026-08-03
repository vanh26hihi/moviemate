<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'cinema_id',
        'code',
        'name',
        'room_type',
        'total_seats',
        'status',
    ];

    protected $casts = [
        'total_seats' => 'integer',
    ];

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
