<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDiscountCode;
use App\Models\DiscountCode;
use Illuminate\Validation\ValidationException;

final class PromotionService
{
    /** @param list<string> $requestedCodes */
    public function quote(int $grossAmount, array $requestedCodes, ?int $userId, int $cinemaId, bool $lock = false): PromotionQuote
    {
        $codes = collect($requestedCodes)->map(fn ($code) => mb_strtoupper(trim((string) $code)))->filter()->unique()->values();
        $maximum = max(1, (int) config('promotions.max_discount_codes_per_booking', 3));
        if ($codes->count() > $maximum) {
            throw ValidationException::withMessages(['discount_code' => "Mỗi đơn chỉ được dùng tối đa {$maximum} mã giảm giá."]);
        }
        if ($codes->isEmpty()) {
            return new PromotionQuote($grossAmount, 0, $grossAmount, collect());
        }

        $query = DiscountCode::query()->with('cinemas')->whereIn('code', $codes);
        if ($lock) {
            $query->lockForUpdate();
        }
        $models = $query->get()->sortBy([['priority', 'desc'], ['id', 'asc']])->values();
        if ($models->count() !== $codes->count()) {
            throw ValidationException::withMessages(['discount_code' => 'Mã giảm giá không tồn tại.']);
        }
        if ($models->count() > 1 && $models->contains(fn (DiscountCode $code) => ! $code->can_combine)) {
            throw ValidationException::withMessages(['discount_code' => 'Một trong các mã đã chọn không cho phép kết hợp.']);
        }

        $remaining = $grossAmount;
        $lines = collect();
        foreach ($models as $code) {
            $this->assertEligible($code, $grossAmount, $userId, $cinemaId);
            $discount = $code->discount_type === 'percent'
                ? intdiv($remaining * min(100, $code->discount_value), 100)
                : $code->discount_value;
            if ($code->maximum_discount_amount !== null) {
                $discount = min($discount, $code->maximum_discount_amount);
            }
            $discount = max(0, min($remaining, $discount));
            $lines->push(['code' => $code, 'subtotal_before' => $remaining, 'discount_amount' => $discount, 'subtotal_after' => $remaining - $discount]);
            $remaining -= $discount;
        }

        return new PromotionQuote($grossAmount, $grossAmount - $remaining, $remaining, $lines);
    }

    /** @param list<string> $codes */
    public function reserveForBooking(Booking $booking, array $codes, int $grossAmount): PromotionQuote
    {
        $quote = $this->quote($grossAmount, $codes, $booking->user_id, (int) $booking->cinema_id, true);
        foreach ($quote->lines as $line) {
            $code = $line['code'];
            BookingDiscountCode::query()->create([
                'booking_id' => $booking->id, 'discount_code_id' => $code->id, 'user_id' => $booking->user_id,
                'code_snapshot' => $code->code, 'name_snapshot' => $code->name,
                'discount_type_snapshot' => $code->discount_type, 'discount_value_snapshot' => $code->discount_value,
                'discount_amount' => $line['discount_amount'], 'subtotal_before' => $line['subtotal_before'],
                'subtotal_after' => $line['subtotal_after'], 'status' => BookingDiscountCode::STATUS_RESERVED, 'reserved_at' => now(),
            ]);
        }

        return $quote;
    }

    public function redeem(Booking $booking): void
    {
        $booking->discountCodeRedemptions()->where('status', BookingDiscountCode::STATUS_RESERVED)
            ->update(['status' => BookingDiscountCode::STATUS_REDEEMED, 'redeemed_at' => now(), 'updated_at' => now()]);
    }

    public function release(Booking $booking): void
    {
        $booking->discountCodeRedemptions()->where('status', BookingDiscountCode::STATUS_RESERVED)
            ->update(['status' => BookingDiscountCode::STATUS_RELEASED, 'released_at' => now(), 'updated_at' => now()]);
    }

    private function assertEligible(DiscountCode $code, int $gross, ?int $userId, int $cinemaId): void
    {
        $invalid = ! $code->is_active || $code->archived_at !== null
            || ($code->starts_at && $code->starts_at->isFuture()) || ($code->ends_at && $code->ends_at->isPast());
        if ($invalid) {
            throw ValidationException::withMessages(['discount_code' => "Mã {$code->code} hiện không khả dụng."]);
        }
        if ($gross < $code->minimum_order_amount) {
            throw ValidationException::withMessages(['discount_code' => "Đơn hàng chưa đạt giá trị tối thiểu của mã {$code->code}."]);
        }
        if ($code->registered_users_only && $userId === null) {
            throw ValidationException::withMessages(['discount_code' => 'Mã này chỉ dành cho khách hàng đã đăng nhập.']);
        }
        if ($code->cinemas->isNotEmpty() && ! $code->cinemas->contains('id', $cinemaId)) {
            throw ValidationException::withMessages(['discount_code' => 'Mã giảm giá không áp dụng tại chi nhánh này.']);
        }
        $used = $code->redemptions()->whereIn('status', [BookingDiscountCode::STATUS_RESERVED, BookingDiscountCode::STATUS_REDEEMED]);
        if ($code->total_quota !== null && (clone $used)->count() >= $code->total_quota) {
            throw ValidationException::withMessages(['discount_code' => 'Mã giảm giá đã hết lượt sử dụng.']);
        }
        if ($userId !== null && $code->per_user_quota !== null && (clone $used)->where('user_id', $userId)->count() >= $code->per_user_quota) {
            throw ValidationException::withMessages(['discount_code' => 'Bạn đã dùng hết lượt của mã giảm giá này.']);
        }
        if ($code->first_order_only && ($userId === null
            || Booking::query()->where('user_id', $userId)->where('payment_status', 'paid')->exists()
            || (clone $used)->where('user_id', $userId)->exists())) {
            throw ValidationException::withMessages(['discount_code' => 'Mã này chỉ áp dụng cho đơn hàng đầu tiên.']);
        }
    }
}
