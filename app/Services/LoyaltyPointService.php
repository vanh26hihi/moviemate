<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LoyaltyPointTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LoyaltyPointService
{
    /** Mỗi điểm dùng thanh toán có giá trị 100đ (tỷ lệ hoàn điểm khoảng 10%). */
    public const VALUE_PER_POINT = 100;

    protected function loyaltyLedgerAvailable(): bool
    {
        return class_exists(LoyaltyPointTransaction::class) && Schema::hasTable('loyalty_point_transactions');
    }

    public function calculate(float|int $totalAmount): int
    {
        return max(0, (int) floor((float) $totalAmount / 1000));
    }

    public function awardForBooking(Booking $booking): void
    {
        if (! $this->loyaltyLedgerAvailable()) {
            return;
        }

        if ($booking->loyaltyPointTransactions()->where('type', 'earn')->exists()) {
            return;
        }

        $points = $this->calculate((float) $booking->total_amount);

        if ($points <= 0) {
            return;
        }

        if ((int) $booking->loyalty_points_earned !== $points) {
            $booking->forceFill(['loyalty_points_earned' => $points])->save();
        }

        $user = $booking->user()->lockForUpdate()->first();

        if (! $user) {
            return;
        }

        $user->increment('loyalty_points', $points);
        $user->increment('lifetime_loyalty_points', $points);

        $booking->loyaltyPointTransactions()->create([
            'user_id' => $user->id,
            'points' => $points,
            'type' => 'earn',
            'description' => 'Tich diem tu don dat ve '.$booking->booking_code,
        ]);
    }

    public function reverseForCancelledBooking(Booking $booking): void
    {
        if (! $this->loyaltyLedgerAvailable()) {
            return;
        }

        $points = (int) $booking->loyalty_points_earned;
        $wasAwarded = $booking->loyaltyPointTransactions()->where('type', 'earn')->exists();

        if ($points <= 0 || ! $wasAwarded || $booking->loyaltyPointTransactions()->where('type', 'reverse')->exists()) {
            return;
        }

        $user = $booking->user()->lockForUpdate()->first();

        if (! $user) {
            return;
        }

        if ((int) $user->loyalty_points < $points) {
            throw ValidationException::withMessages([
                'loyalty_points' => 'Khong the huy ve vi diem da duoc su dung. Vui long lien he nhan vien de ho tro hoan/huy ve.',
            ]);
        }

        $user->decrement('loyalty_points', $points);
        $user->decrement('lifetime_loyalty_points', min($points, (int) $user->lifetime_loyalty_points));

        $booking->loyaltyPointTransactions()->create([
            'user_id' => $booking->user_id,
            'points' => -1 * $points,
            'type' => 'reverse',
            'description' => 'Hoan diem do huy ve '.$booking->booking_code,
        ]);
    }

    public function redeemPoints(User $user, int $points, string $description): LoyaltyPointTransaction
    {
        if (! $this->loyaltyLedgerAvailable()) {
            throw ValidationException::withMessages([
                'points' => 'Hệ thống điểm thưởng đã bị vô hiệu hóa.',
            ]);
        }

        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => 'So diem doi phai lon hon 0.',
            ]);
        }

        return DB::transaction(function () use ($user, $points, $description) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedUser->loyalty_points < $points) {
                throw ValidationException::withMessages([
                    'points' => 'So diem kha dung khong du de doi qua.',
                ]);
            }

            $lockedUser->decrement('loyalty_points', $points);

            return LoyaltyPointTransaction::create([
                'user_id' => $lockedUser->id,
                'booking_id' => null,
                'points' => -1 * $points,
                'type' => 'redeem',
                'description' => $description,
            ]);
        });
    }

    public function redeemForBooking(User $user, Booking $booking, int $points): LoyaltyPointTransaction
    {
        if (! $this->loyaltyLedgerAvailable()) {
            throw ValidationException::withMessages(['loyalty_points' => 'Hệ thống điểm thưởng đã bị vô hiệu hóa.']);
        }

        if ($points <= 0) {
            throw ValidationException::withMessages(['loyalty_points' => 'Số điểm sử dụng phải lớn hơn 0.']);
        }

        $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

        if ((int) $lockedUser->loyalty_points < $points) {
            throw ValidationException::withMessages(['loyalty_points' => 'Số điểm khả dụng không đủ.']);
        }

        $lockedUser->decrement('loyalty_points', $points);

        return $booking->loyaltyPointTransactions()->create([
            'user_id' => $lockedUser->id,
            'points' => -$points,
            'type' => 'redeem',
            'description' => 'Dùng điểm giảm giá đơn vé '.$booking->booking_code,
        ]);
    }

    public function restoreRedeemedPoints(Booking $booking): void
    {
        if (! $this->loyaltyLedgerAvailable()) {
            return;
        }

        $points = (int) $booking->loyalty_points_redeemed;

        if ($points <= 0 || $booking->loyaltyPointTransactions()->where('type', 'adjustment')->where('points', $points)->exists()) {
            return;
        }

        $user = $booking->user()->lockForUpdate()->first();
        if (! $user) {
            return;
        }

        $user->increment('loyalty_points', $points);
        $booking->loyaltyPointTransactions()->create([
            'user_id' => $user->id,
            'points' => $points,
            'type' => 'adjustment',
            'description' => 'Hoàn điểm do đơn vé '.$booking->booking_code.' bị hủy',
        ]);
    }
}
