<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatHold extends Model
{
    protected $fillable = ['user_id', 'showtime_id', 'seat_id', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];
}
