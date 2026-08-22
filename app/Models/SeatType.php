<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SeatType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_pair' => 'boolean', 'status' => 'boolean', 'sort_order' => 'integer'];
    }

    public function showtimeTicketPrices(): HasMany
    {
        return $this->hasMany(ShowtimeTicketPrice::class);
    }
}
