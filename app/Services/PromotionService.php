<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPromotion;
use App\Models\Cinema;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class PromotionService
{
    public function quote(int $grossAmount, ?string $requestedCode, ?int $userId, int $cinemaId): PromotionQuote
    {
        $code = $this->normalizeCode($requestedCode);
        if ($code === null) {
            return new PromotionQuote($grossAmount, 0, $grossAmount, collect());
        }

        $promotion = Promotion::query()->with('cinemas:id,code,name,timezone')->where('code', $code)->first();
        if ($promotion === null) {
            throw ValidationException::withMessages(['promotion_code' => 'Mã khuyến mãi không tồn tại.']);
        }

        return $this->quotePromotion($promotion, $grossAmount, $userId, $this->cinema($cinemaId));
    }

    public function reserveForBooking(Booking $booking, ?string $requestedCode, int $grossAmount): PromotionQuote
    {
        $code = $this->normalizeCode($requestedCode);
        if ($code === null) {
            return new PromotionQuote($grossAmount, 0, $grossAmount, collect());
        }

        // The outer Booking transaction owns this lock and the aggregate commit.
        $promotion = Promotion::query()->where('code', $code)->lockForUpdate()->first();
        if ($promotion === null) {
            throw ValidationException::withMessages(['promotion_code' => 'Mã khuyến mãi không tồn tại.']);
        }
        $promotion->load('cinemas:id,code,name,timezone');
        $cinema = $this->cinema((int) $booking->cinema_id);
        $quote = $this->quotePromotion($promotion, $grossAmount, $booking->user_id, $cinema);
        $line = $quote->lines->sole();

        BookingPromotion::query()->create([
            'booking_id' => $booking->id,
            'promotion_id' => $promotion->id,
            'user_id' => $booking->user_id,
            'code_snapshot' => $promotion->code,
            'name_snapshot' => $promotion->name,
            'type_snapshot' => $promotion->type,
            'discount_amount_vnd_snapshot' => $promotion->discount_amount_vnd,
            'discount_percent_snapshot' => $promotion->discount_percent,
            'maximum_discount_vnd_snapshot' => $promotion->maximum_discount_vnd,
            'minimum_order_vnd_snapshot' => $promotion->minimum_order_vnd,
            'scope_kind_snapshot' => $promotion->cinemas->isEmpty() ? 'global' : 'cinema',
            'booking_cinema_id_snapshot' => $cinema->id,
            'booking_cinema_code_snapshot' => $cinema->code,
            'booking_cinema_name_snapshot' => $cinema->name,
            'eligible_cinemas_snapshot' => $promotion->cinemas->isEmpty() ? null : $promotion->cinemas
                ->map(fn (Cinema $eligible): array => ['id' => (int) $eligible->id, 'code' => $eligible->code, 'name' => $eligible->name])
                ->values()->all(),
            'registered_users_only_snapshot' => $promotion->registered_users_only,
            'first_order_only_snapshot' => $promotion->first_order_only,
            'global_usage_limit_snapshot' => $promotion->global_usage_limit,
            'per_user_usage_limit_snapshot' => $promotion->per_user_usage_limit,
            'applied_discount_vnd' => $line['discount_amount'],
            'gross_before_vnd' => $grossAmount,
            'final_after_vnd' => $quote->finalAmount,
            'status' => BookingPromotion::STATUS_RESERVED,
            'reserved_at' => now(),
        ]);

        return $quote;
    }

    public function redeem(Booking $booking): void
    {
        $booking->promotionUsage()->where('status', BookingPromotion::STATUS_RESERVED)->update([
            'status' => BookingPromotion::STATUS_REDEEMED, 'redeemed_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function release(Booking $booking): void
    {
        $booking->promotionUsage()->where('status', BookingPromotion::STATUS_RESERVED)->update([
            'status' => BookingPromotion::STATUS_RELEASED, 'released_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function quotePromotion(Promotion $promotion, int $gross, ?int $userId, Cinema $cinema): PromotionQuote
    {
        $this->assertEligible($promotion, $gross, $userId, $cinema);
        $discount = $promotion->type === Promotion::TYPE_PERCENTAGE
            ? intdiv($gross * (int) $promotion->discount_percent, 100)
            : (int) $promotion->discount_amount_vnd;
        if ($promotion->type === Promotion::TYPE_PERCENTAGE && $promotion->maximum_discount_vnd !== null) {
            $discount = min($discount, (int) $promotion->maximum_discount_vnd);
        }
        $discount = min($gross, max(0, $discount));

        return new PromotionQuote($gross, $discount, $gross - $discount, collect([[
            'promotion' => $promotion, 'discount_amount' => $discount,
            'subtotal_before' => $gross, 'subtotal_after' => $gross - $discount,
        ]]));
    }

    private function assertEligible(Promotion $promotion, int $gross, ?int $userId, Cinema $cinema): void
    {
        $timezone = is_string($cinema->timezone) && trim($cinema->timezone) !== ''
            ? trim($cinema->timezone) : (string) config('app.timezone', 'UTC');
        $localNow = CarbonImmutable::now($timezone)->format('Y-m-d H:i:s');
        $startsAt = $promotion->getRawOriginal('starts_at');
        $endsAt = $promotion->getRawOriginal('ends_at');
        if (! $promotion->is_active || $promotion->archived_at !== null
            || ($startsAt !== null && $localNow < $startsAt)
            || ($endsAt !== null && $localNow >= $endsAt)) {
            throw ValidationException::withMessages(['promotion_code' => "Mã {$promotion->code} hiện không khả dụng."]);
        }
        if ($gross < (int) $promotion->minimum_order_vnd) {
            throw ValidationException::withMessages(['promotion_code' => "Đơn hàng chưa đạt giá trị tối thiểu của mã {$promotion->code}."]);
        }
        if ($promotion->registered_users_only && $userId === null) {
            throw ValidationException::withMessages(['promotion_code' => 'Mã này chỉ dành cho khách hàng đã đăng nhập.']);
        }
        if ($promotion->cinemas->isNotEmpty() && ! $promotion->cinemas->contains('id', $cinema->id)) {
            throw ValidationException::withMessages(['promotion_code' => 'Mã khuyến mãi không áp dụng tại chi nhánh này.']);
        }

        $consumed = $promotion->usages()->whereIn('status', [BookingPromotion::STATUS_RESERVED, BookingPromotion::STATUS_REDEEMED]);
        if ($promotion->global_usage_limit !== null && (clone $consumed)->count() >= (int) $promotion->global_usage_limit) {
            throw ValidationException::withMessages(['promotion_code' => 'Mã khuyến mãi đã hết lượt sử dụng.']);
        }
        // Guests have no stable account identity; per-user quota intentionally applies only to accounts.
        if ($userId !== null && $promotion->per_user_usage_limit !== null
            && (clone $consumed)->where('user_id', $userId)->count() >= (int) $promotion->per_user_usage_limit) {
            throw ValidationException::withMessages(['promotion_code' => 'Bạn đã dùng hết lượt của mã khuyến mãi này.']);
        }
        if ($promotion->first_order_only && ($userId === null
            || Booking::query()->where('user_id', $userId)->where('payment_status', 'paid')->exists()
            || (clone $consumed)->where('user_id', $userId)->exists())) {
            throw ValidationException::withMessages(['promotion_code' => 'Mã này chỉ áp dụng cho đơn hàng đầu tiên.']);
        }
    }

    private function normalizeCode(?string $code): ?string
    {
        $normalized = mb_strtoupper(trim((string) $code));

        return $normalized === '' ? null : $normalized;
    }

    private function cinema(int $cinemaId): Cinema
    {
        return Cinema::query()->select(['id', 'code', 'name', 'timezone'])->findOrFail($cinemaId);
    }
}
