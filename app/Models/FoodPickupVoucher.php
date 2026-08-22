<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FoodPickupVoucher extends Model
{
    protected $fillable = ['booking_id', 'voucher_code'];

    protected function casts(): array
    {
        return ['print_count' => 'integer', 'last_printed_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function lastPrintedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_printed_by_user_id');
    }

    public function printEvents(): HasMany
    {
        return $this->hasMany(FoodPickupVoucherPrintEvent::class);
    }
}
