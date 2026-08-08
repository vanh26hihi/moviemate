<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTicketDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_SENT,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'booking_id',
        'status',
        'attempts',
        'available_at',
        'processing_started_at',
        'lease_expires_at',
        'sent_at',
        'last_error_code',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('ticket_delivery', $this->status);
    }
}
