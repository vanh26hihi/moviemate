<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class SeatIncidentResolution extends Model
{
    public const TYPE_EQUIVALENT = 'equivalent';

    public const TYPE_UPGRADE = 'upgrade';

    public const TYPE_REQUIRES_REFUND = 'requires_refund';

    public const TYPES = [self::TYPE_EQUIVALENT, self::TYPE_UPGRADE, self::TYPE_REQUIRES_REFUND];

    protected $fillable = [
        'seat_incident_impact_id', 'operation_id', 'resolution_type', 'original_seat_id',
        'replacement_seat_id', 'resolved_by_user_id', 'original_pre_promotion_amount',
        'replacement_hypothetical_amount', 'reprint_required', 'reprint_satisfied_at',
        'operational_note',
    ];

    protected function casts(): array
    {
        return [
            'original_pre_promotion_amount' => 'integer',
            'replacement_hypothetical_amount' => 'integer',
            'reprint_required' => 'boolean',
            'reprint_satisfied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $resolution): void {
            $allowed = ['reprint_satisfied_at', 'updated_at'];
            if (array_diff(array_keys($resolution->getDirty()), $allowed) !== []) {
                throw new LogicException('Seat incident relocation history is immutable.');
            }
        });
        self::deleting(fn () => throw new LogicException('Seat incident relocation history cannot be deleted.'));
    }

    public function impact(): BelongsTo
    {
        return $this->belongsTo(SeatIncidentImpact::class, 'seat_incident_impact_id');
    }

    public function originalSeat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'original_seat_id');
    }

    public function replacementSeat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'replacement_seat_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
