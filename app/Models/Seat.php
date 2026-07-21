<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'user_name',
        'showtime_id',
        'seat_number',
        'price'
    ];

    protected $casts = [
        'price' => 'decimal:2'
    ];

    public function showtime()
    {
        return $this->belongsTo(Showtime::class);
    }
}