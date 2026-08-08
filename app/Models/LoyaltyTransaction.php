<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoyaltyTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['points_delta' => 'integer', 'balance_after' => 'integer', 'metadata' => 'array'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function getCustomerDescriptionAttribute(): string
    {
        $metadata = $this->metadata ?? [];

        return match ($this->type) {
            'review_reward' => 'Thưởng đánh giá phim #'.($metadata['review_id'] ?? '—'),
            'reserve' => 'Tạm giữ cho đơn #'.($metadata['booking_id'] ?? '—'),
            'redeem' => 'Đã dùng cho đơn #'.($metadata['booking_id'] ?? '—'),
            'release' => ($metadata['reason'] ?? null) === 'expired'
                ? 'Hoàn điểm do đơn hết hạn'
                : 'Hoàn điểm từ đơn #'.($metadata['booking_id'] ?? '—'),
            default => 'Điều chỉnh điểm MovieMate',
        };
    }
}
