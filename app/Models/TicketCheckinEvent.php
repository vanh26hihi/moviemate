<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class TicketCheckinEvent extends Model
{
    public const UPDATED_AT = null;

    public const RESULT_ACCEPTED = 'accepted';

    public const RESULT_ALREADY_USED = 'already_used';

    public const RESULT_UNPAID = 'unpaid';

    public const RESULT_CANCELLED = 'cancelled';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_INVALID_TOKEN = 'invalid_token';

    public const RESULT_REJECTED = 'rejected';

    public const RESULTS = [
        self::RESULT_ACCEPTED,
        self::RESULT_ALREADY_USED,
        self::RESULT_UNPAID,
        self::RESULT_CANCELLED,
        self::RESULT_EXPIRED,
        self::RESULT_INVALID_TOKEN,
        self::RESULT_REJECTED,
    ];

    protected $fillable = [
        'admission_ticket_id', 'accepted_ticket_id', 'booking_id', 'showtime_id', 'actor_user_id', 'actor_role_snapshot',
        'result', 'reason_code', 'scanned_at', 'request_id', 'route_name',
        'safe_ip_hash', 'user_agent_summary', 'context',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'context' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Ticket check-in events are append-only and cannot be updated.'));
        self::deleting(fn () => throw new LogicException('Ticket check-in events are append-only and cannot be deleted.'));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function admissionTicket(): BelongsTo
    {
        return $this->belongsTo(AdmissionTicket::class);
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
