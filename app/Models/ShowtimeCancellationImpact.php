<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ShowtimeCancellationImpact extends Model
{
    public const OUTCOME_UNPAID_CANCELLED = 'unpaid_cancelled';

    public const OUTCOME_REFUND_REQUIRED = 'refund_required';

    public const OUTCOME_ALREADY_TERMINAL = 'already_terminal';

    protected $fillable = ['showtime_cancellation_id', 'booking_id', 'outcome', 'booking_status_before', 'payment_status_before', 'authoritative_amount', 'currency', 'seat_count', 'audit_snapshot'];

    protected function casts(): array
    {
        return ['authoritative_amount' => 'integer', 'seat_count' => 'integer', 'audit_snapshot' => 'array'];
    }

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(ShowtimeCancellation::class, 'showtime_cancellation_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function refundCase(): HasOne
    {
        return $this->hasOne(RefundCase::class);
    }
}
