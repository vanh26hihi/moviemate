<?php

namespace App\Models;

use App\Support\StatusLabel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function cinemaAssignments(): HasMany
    {
        return $this->hasMany(UserCinemaAssignment::class);
    }

    public function activeCinemaAssignments(): HasMany
    {
        return $this->cinemaAssignments()->where('status', UserCinemaAssignment::STATUS_ACTIVE);
    }

    public function assignedCinemas(): BelongsToMany
    {
        return $this->belongsToMany(Cinema::class, 'user_cinema_assignments')
            ->wherePivot('status', UserCinemaAssignment::STATUS_ACTIVE)
            ->withPivot(['id', 'assigned_by_user_id', 'status', 'assigned_at'])
            ->withTimestamps();
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return $this->role !== null && in_array($this->role->slug, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->role !== null && $this->role->permissions->contains('slug', $permission);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('user', $this->status);
    }
}
