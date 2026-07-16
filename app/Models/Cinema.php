<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cinema extends Model
{
    protected $fillable = [
        'name',
        'address',
        'city',
        'phone',
        'image',
        'description',
        'status',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }
}