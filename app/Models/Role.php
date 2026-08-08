<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const EDITABLE_SLUGS = ['manager', 'staff'];

    public const MANAGER_PERMISSION_SLUGS = [
        'admin.access', 'dashboard.view',
        'cinema.view', 'cinema.update', 'cinemas.view', 'cinemas.operations.manage',
        'cinema_assignments.view', 'cinema_assignments.manage', 'users.view',
        'rooms.view', 'rooms.create', 'rooms.update',
        'seats.view', 'seats.manage',
        'layout_templates.view', 'room_layouts.apply_template',
        'movies.view', 'movies.create', 'movies.update',
        'genres.view', 'genres.create', 'genres.update', 'genres.delete',
        'showtimes.view', 'showtimes.create', 'showtimes.update', 'showtimes.delete',
        'pricing.view', 'pricing.manage',
        'foods.view', 'foods.create', 'foods.update', 'foods.delete',
        'food-orders.view', 'food-orders.update-status',
        'bookings.view', 'bookings.operate',
        'counter_sales.view', 'counter_sales.create', 'counter_sales.settle', 'counter_sales.cancel',
        'payments.view', 'payments.reconcile',
        'ticket_deliveries.view', 'ticket_deliveries.retry', 'ticket_checkins.view',
        'seats.maintenance.view', 'seats.maintenance.update',
        'discounts.view', 'discounts.manage',
        'reviews.view', 'reviews.moderate',
        'reports.view', 'tickets.print', 'tickets.checkin', 'tickets.lookup',
        'tickets.print.override', 'ticket_prints.view',
    ];

    public const STAFF_PERMISSION_SLUGS = [
        'dashboard.view',
        'rooms.view',
        'seats.view',
        'showtimes.view',
        'food-orders.view',
        'food-orders.update-status',
        'bookings.view',
        'bookings.operate',
        'counter_sales.view',
        'counter_sales.create',
        'counter_sales.settle',
        'counter_sales.cancel',
        'ticket_checkins.view',
        'tickets.print',
        'tickets.checkin',
        'tickets.lookup',
    ];

    protected $fillable = ['name', 'slug', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (Role $role): void {
            if ($role->is_system || $role->users()->exists()) {
                throw new \LogicException('Không thể xóa vai trò hệ thống hoặc vai trò đang được gán cho người dùng.');
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions->contains('slug', $slug);
    }

    public function isEditable(): bool
    {
        return in_array($this->slug, self::EDITABLE_SLUGS, true);
    }

    public function getDisplayNameAttribute(): string
    {
        return StatusLabel::for('role', $this->slug);
    }
}
