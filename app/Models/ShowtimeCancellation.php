<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShowtimeCancellation extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const REASONS = [
        'technical_issue' => 'Sự cố kỹ thuật',
        'safety_issue' => 'An toàn vận hành',
        'facility_issue' => 'Sự cố cơ sở vật chất',
        'staffing_issue' => 'Thiếu nhân sự vận hành',
        'schedule_change' => 'Điều chỉnh lịch từ rạp',
        'other' => 'Lý do khác',
    ];

    protected $fillable = ['showtime_id', 'cinema_id', 'reason_code', 'reason_note', 'status', 'cancelled_by_user_id', 'cancelled_at', 'resolved_by_user_id', 'resolved_at'];

    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function impacts(): HasMany
    {
        return $this->hasMany(ShowtimeCancellationImpact::class);
    }

    public function refundCases(): HasMany
    {
        return $this->hasMany(RefundCase::class);
    }
}
