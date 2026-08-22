<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'group'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function getDisplayNameAttribute(): string
    {
        return str_ireplace(
            ['dashboard', 'layout', 'booking'],
            ['tổng quan', 'sơ đồ', 'đơn đặt vé'],
            $this->name
        );
    }
}
