<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RefundCase extends Model
{
    public const STATUS_REQUIRED = 'required';

    public const STATUS_RESOLVED = 'resolved';

    public const RESOLUTION_METHODS = [
        'bank_transfer' => 'Chuyển khoản ngân hàng',
        'cash' => 'Tiền mặt',
        'e_wallet' => 'Ví điện tử',
        'other' => 'Phương thức khác',
    ];

    protected $fillable = ['showtime_cancellation_id', 'showtime_cancellation_impact_id', 'cinema_id', 'booking_id', 'payment_id', 'status', 'required_amount', 'currency', 'resolution_method', 'resolution_reference', 'resolution_note', 'resolved_by_user_id', 'resolved_at'];

    protected function casts(): array
    {
        return ['required_amount' => 'integer', 'resolved_at' => 'datetime'];
    }

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(ShowtimeCancellation::class, 'showtime_cancellation_id');
    }

    public function impact(): BelongsTo
    {
        return $this->belongsTo(ShowtimeCancellationImpact::class, 'showtime_cancellation_impact_id');
    }

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
