<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function resolve(?string $code, float|int $subtotal, ?int $userId = null, bool $lockForUpdate = false): array
    {
        $code = trim((string) $code);

        if ($code === '') {
            return [
                'voucher' => null,
                'code' => null,
                'discount' => 0.0,
                'total' => (float) $subtotal,
            ];
        }

        $voucherQuery = Voucher::where('code', strtoupper($code));

        if ($lockForUpdate) {
            $voucherQuery->lockForUpdate();
        }

        $voucher = $voucherQuery->first();

        if (! $voucher) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher không tồn tại.',
            ]);
        }

        if ($voucher->status !== 'active') {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher không còn hoạt động.',
            ]);
        }

        $now = now();
        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher chưa đến thời gian sử dụng.',
            ]);
        }

        if ($voucher->ends_at && $voucher->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher đã hết hạn.',
            ]);
        }

        if (! is_null($voucher->usage_limit) && $voucher->used_count >= $voucher->usage_limit) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher đã hết lượt sử dụng.',
            ]);
        }

        if ($userId && ! is_null($voucher->per_user_limit)) {
            $userUsageCount = $voucher->bookings()
                ->where('user_id', $userId)
                ->where(function ($query) {
                    $query->whereIn('booking_status', ['paid', 'used'])
                        ->orWhere(function ($query) {
                            $query->where('booking_status', 'pending')
                                ->where('payment_status', 'pending')
                                ->where('hold_expires_at', '>', now());
                        });
                })
                ->count();

            if ($userUsageCount >= $voucher->per_user_limit) {
                throw ValidationException::withMessages([
                    'voucher_code' => 'Bạn đã sử dụng hết số lượt cho phép của voucher này.',
                ]);
            }
        }

        if ((float) $subtotal < (float) $voucher->min_order_amount) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Đơn hàng chưa đạt giá trị tối thiểu để dùng voucher.',
            ]);
        }

        $discount = $voucher->discount_type === 'percent'
            ? ((float) $subtotal * (float) $voucher->discount_value / 100)
            : (float) $voucher->discount_value;

        if (! is_null($voucher->max_discount_amount)) {
            $discount = min($discount, (float) $voucher->max_discount_amount);
        }

        $discount = min($discount, (float) $subtotal);

        return [
            'voucher' => $voucher,
            'code' => $voucher->code,
            'discount' => round($discount, 2),
            'total' => round(max(0, (float) $subtotal - $discount), 2),
        ];
    }
}
