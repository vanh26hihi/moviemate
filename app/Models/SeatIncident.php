<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SeatIncident extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_CANCELLED];

    public const REASON_BROKEN = 'seat_broken';

    public const REASON_MAINTENANCE = 'maintenance_required';

    public const REASON_SAFETY = 'safety_issue';

    public const REASON_OTHER = 'other';

    public const REASONS = [self::REASON_BROKEN, self::REASON_MAINTENANCE, self::REASON_SAFETY, self::REASON_OTHER];

    protected $fillable = [
        'cinema_id', 'room_id', 'reported_by_user_id', 'status', 'reason', 'note', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function incidentSeats(): HasMany
    {
        return $this->hasMany(SeatIncidentSeat::class);
    }

    public function impacts(): HasMany
    {
        return $this->hasMany(SeatIncidentImpact::class);
    }
}
