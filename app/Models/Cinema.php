<?php

namespace App\Models;

use App\Services\CinemaContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cinema extends Model
{
    use HasFactory;

    protected $fillable = [
        'canonical_key',
        'name',
        'school_name',
        'address',
        'city',
        'country',
        'phone',
        'latitude',
        'longitude',
        'image',
        'description',
        'status',
        'is_primary',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:14',
            'longitude' => 'decimal:14',
            'is_primary' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Cinema $cinema): void {
            if ($cinema->is_primary
                || $cinema->canonical_key === CinemaContext::CANONICAL_KEY
                || $cinema->rooms()->exists()
                || $cinema->showtimes()->exists()
                || $cinema->pickupOrders()->exists()) {
                throw new \LogicException('The canonical or referenced cinema cannot be deleted.');
            }
        });
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNull('archived_at');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    public function pickupOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'pickup_cinema_id');
    }

    public function getMapUrlAttribute(): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->latitude.','.$this->longitude);
    }
}
