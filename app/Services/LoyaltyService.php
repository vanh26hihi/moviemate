<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPointRedemption;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTransaction;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LoyaltyService
{
    public function quote(?int $userId, int $amountAfterPromotions, int $requestedPoints): LoyaltyQuote
    {
        if ($requestedPoints < 0) {
            throw ValidationException::withMessages(['points' => 'Số điểm sử dụng không hợp lệ.']);
        }
        $available = $userId ? (int) LoyaltyAccount::query()->where('user_id', $userId)->value('points_balance') : 0;
        if ($requestedPoints > 0 && $userId === null) {
            throw ValidationException::withMessages(['points' => 'Vui lòng đăng nhập để sử dụng điểm.']);
        }
        if ($requestedPoints > $available) {
            throw ValidationException::withMessages(['points' => 'Số điểm yêu cầu vượt quá số dư khả dụng.']);
        }
        $settings = LoyaltySetting::current();
        if ($requestedPoints > 0 && $requestedPoints < $settings->minimum_points_redemption) {
            throw ValidationException::withMessages(['points' => 'Số điểm chưa đạt mức tối thiểu.']);
        }
        $maximumDiscount = intdiv($amountAfterPromotions * $settings->max_points_discount_percent, 100);
        $maximumPoints = intdiv($maximumDiscount, max(1, $settings->point_value_vnd));
        $used = min($requestedPoints, $maximumPoints);
        $discount = min($amountAfterPromotions, $used * $settings->point_value_vnd);

        return new LoyaltyQuote($available, $used, $settings->point_value_vnd, $discount, $amountAfterPromotions - $discount);
    }

    public function reserveForBooking(Booking $booking, int $requestedPoints, int $amountAfterPromotions): LoyaltyQuote
    {
        if ($requestedPoints === 0) {
            return $this->quote($booking->user_id, $amountAfterPromotions, 0);
        }
        $account = LoyaltyAccount::query()->where('user_id', $booking->user_id)->lockForUpdate()->first();
        $quote = $this->quote($booking->user_id, $amountAfterPromotions, $requestedPoints);
        if (! $account || $quote->pointsUsed === 0) {
            return $quote;
        }
        $account->decrement('points_balance', $quote->pointsUsed);
        $account->refresh();
        BookingPointRedemption::query()->create([
            'booking_id' => $booking->id,
            'loyalty_account_id' => $account->id,
            'points' => $quote->pointsUsed,
            'point_value_vnd_snapshot' => $quote->pointValueVnd,
            'discount_amount' => $quote->discountAmount,
            'status' => 'reserved',
            'reserved_at' => now(),
        ]);
        LoyaltyTransaction::query()->create(['loyalty_account_id' => $account->id, 'source_key' => 'booking:'.$booking->id.':reserve', 'type' => 'reserve', 'points_delta' => -$quote->pointsUsed, 'balance_after' => $account->points_balance, 'metadata' => ['booking_id' => $booking->id]]);

        return $quote;
    }

    public function redeem(Booking $booking): void
    {
        $redemption = $booking->pointRedemption()->where('status', 'reserved')->lockForUpdate()->first();
        if (! $redemption) {
            return;
        }
        $redemption->update(['status' => 'redeemed', 'redeemed_at' => now()]);
        LoyaltyTransaction::query()->firstOrCreate(['source_key' => 'booking:'.$booking->id.':redeem'], ['loyalty_account_id' => $redemption->loyalty_account_id, 'type' => 'redeem', 'points_delta' => 0, 'balance_after' => $redemption->account()->value('points_balance'), 'metadata' => ['booking_id' => $booking->id]]);
    }

    public function release(Booking $booking, string $reason = 'booking_released'): void
    {
        $redemption = $booking->pointRedemption()->where('status', 'reserved')->lockForUpdate()->first();
        if (! $redemption) {
            return;
        }
        $account = LoyaltyAccount::query()->lockForUpdate()->findOrFail($redemption->loyalty_account_id);
        $account->increment('points_balance', $redemption->points);
        $account->refresh();
        $reason = mb_substr($reason, 0, 100);
        $redemption->update(['status' => 'released', 'released_at' => now(), 'release_reason' => $reason]);
        LoyaltyTransaction::query()->firstOrCreate(['source_key' => 'booking:'.$booking->id.':release'], ['loyalty_account_id' => $account->id, 'type' => 'release', 'points_delta' => $redemption->points, 'balance_after' => $account->points_balance, 'metadata' => ['booking_id' => $booking->id, 'reason' => $reason]]);
    }

    public function rewardPublishedReview(Review $review): void
    {
        if ($review->reward_awarded_at || $review->moderation_status !== Review::MODERATION_PUBLISHED) {
            return;
        }
        DB::transaction(function () use ($review): void {
            $locked = Review::query()->lockForUpdate()->findOrFail($review->id);
            if ($locked->reward_awarded_at) {
                return;
            }
            LoyaltyAccount::query()->insertOrIgnore([
                'user_id' => $locked->user_id,
                'points_balance' => 0,
                'lifetime_earned' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $account = LoyaltyAccount::query()->where('user_id', $locked->user_id)->lockForUpdate()->firstOrFail();
            $points = LoyaltySetting::current()->review_reward_points;
            $transaction = LoyaltyTransaction::query()->firstOrCreate(['source_key' => 'review:'.$locked->id.':published'], ['loyalty_account_id' => $account->id, 'type' => 'review_reward', 'points_delta' => $points, 'balance_after' => $account->points_balance + $points, 'metadata' => ['review_id' => $locked->id]]);
            if ($transaction->wasRecentlyCreated) {
                $account->increment('points_balance', $points);
                $account->increment('lifetime_earned', $points);
            }
            $locked->update(['reward_awarded_at' => now(), 'first_published_at' => $locked->first_published_at ?: now()]);
        });
    }
}
