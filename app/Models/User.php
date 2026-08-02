<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'phone',
        'password',
        'role_id',
        'avatar',
        'status',
        'loyalty_points',
        'lifetime_loyalty_points',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'role_id' => 'integer',
        'loyalty_points' => 'integer',
        'lifetime_loyalty_points' => 'integer',
        'password' => 'hashed',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function aiChats(): HasMany
    {
        return $this->hasMany(AiChat::class);
    }

    public function aiRecommendations(): HasMany
    {
        return $this->hasMany(AiRecommendation::class);
    }

    public function loyaltyPointTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    public function getMembershipTierAttribute(): string
    {
        $points = (int) $this->lifetime_loyalty_points;

        return match (true) {
            $points >= 2000 => 'Kim cÆ°Æ¡ng',
            $points >= 1000 => 'VÃ ng',
            $points >= 500 => 'Báº¡c',
            default => 'ThÃ nh viÃªn',
        };
    }

    public function getPointsToNextTierAttribute(): int
    {
        $points = (int) $this->lifetime_loyalty_points;

        return match (true) {
            $points < 500 => 500 - $points,
            $points < 1000 => 1000 - $points,
            $points < 2000 => 2000 - $points,
            default => 0,
        };
    }
}
