<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cinema extends Model
{
    use HasFactory;

    protected $fillable = [
        'canonical_key',
        'code',
        'name',
        'school_name',
        'address',
        'city',
        'district',
        'country',
        'timezone',
        'default_cleaning_buffer_minutes',
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
            'default_cleaning_buffer_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Cinema $cinema): void {
            // The primary branch anchors the customer default context, so it stays undeletable
            // even when it currently has no dependent records.
            if ($cinema->is_primary
                || $cinema->rooms()->exists()
                || $cinema->showtimes()->exists()
                || $cinema->bookings()->exists()
                || $cinema->pickupOrders()->exists()
                || $cinema->assignments()->exists()) {
                throw new \LogicException('The primary or a referenced cinema cannot be deleted.');
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

    public function operatingHours(): HasMany
    {
        return $this->hasMany(CinemaOperatingHour::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(CinemaPricingRule::class);
    }

    public function pickupOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'pickup_cinema_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserCinemaAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->where('status', UserCinemaAssignment::STATUS_ACTIVE);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_cinema_assignments')
            ->withPivot(['id', 'assigned_by_user_id', 'status', 'assigned_at'])
            ->withTimestamps();
    }

    public function getMapUrlAttribute(): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->latitude.','.$this->longitude);
    }
}
