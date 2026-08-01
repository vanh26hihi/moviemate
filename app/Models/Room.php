<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Director extends Model
{
    protected $fillable = [
        'name',
        'avatar',
        'bio'
    ];

    public function movies(): HasMany
    {
        return $this->hasMany(Movie::class);
    }
}