<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SeatType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_pair' => 'boolean', 'status' => 'boolean', 'sort_order' => 'integer'];
    }
}
