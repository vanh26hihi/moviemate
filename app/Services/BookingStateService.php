<?php

namespace App\Services;

use App\Models\Booking;

final class BookingStateService
{
    public function normalizedStatus(Booking $booking): string
    {
        if ($booking->booking_status === 'paid' || $booking->payment_status === 'paid') {
            return 'paid';
        }

        if ($booking->booking_status === 'cancelled' || $booking->payment_status === 'refunded') {
            return 'cancelled';
        }

        if ($booking->booking_status === 'expired' || $booking->payment_status === 'failed') {
            return 'expired';
        }

        if ($booking->booking_status === 'pending_payment' || $booking->payment_status === 'unpaid') {
            return 'pending';
        }

        return 'unknown';
    }

    public function canBePaid(Booking $booking): bool
    {
        return $booking->booking_status === 'pending_payment'
            && $booking->payment_status === 'unpaid'
            && ($booking->expires_at === null || $booking->expires_at->isFuture());
    }

    public function canBeCancelled(Booking $booking): bool
    {
        return in_array($booking->booking_status, ['pending_payment', 'paid'], true)
            || in_array($booking->payment_status, ['unpaid', 'paid'], true);
    }

    public function isExpired(Booking $booking): bool
    {
        return $booking->booking_status === 'expired'
            || $booking->payment_status === 'failed'
            || ($booking->expires_at !== null && $booking->expires_at->isPast() && $booking->booking_status === 'pending_payment');
    }

    public function remainingMinutes(Booking $booking): int
    {
        if (! $booking->expires_at) {
            return 0;
        }

        return max(0, (int) now()->diffInMinutes($booking->expires_at, false));
    }

    public function summary(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_status' => $booking->booking_status,
            'payment_status' => $booking->payment_status,
            'normalized_status' => $this->normalizedStatus($booking),
            'can_be_paid' => $this->canBePaid($booking),
            'can_be_cancelled' => $this->canBeCancelled($booking),
            'is_expired' => $this->isExpired($booking),
            'remaining_minutes' => $this->remainingMinutes($booking),
            'expires_at' => $booking->expires_at,
        ];
    }

    public function overDeadline(Booking $booking): bool
    {
        return $booking->expires_at !== null && $booking->expires_at->isPast();
    }

    public function isPayable(Booking $booking): bool
    {
        return $this->canBePaid($booking) && ! $this->overDeadline($booking);
    }

    public function statusLabel(Booking $booking): string
    {
        return match ($this->normalizedStatus($booking)) {
            'paid' => 'Đã thanh toán',
            'cancelled' => 'Đã hủy',
            'expired' => 'Đã hết hạn',
            'pending' => 'Chờ thanh toán',
            default => 'Không xác định',
        };
    }

    public function nextActionHint(Booking $booking): string
    {
        if ($this->normalizedStatus($booking) === 'paid') {
            return 'Hoàn tất và gửi vé cho khách.';
        }

        if ($this->normalizedStatus($booking) === 'expired') {
            return 'Yêu cầu khách đặt lại hoặc mở lại đơn.';
        }

        if ($this->normalizedStatus($booking) === 'cancelled') {
            return 'Không cần xử lý thêm.';
        }

        if ($this->overDeadline($booking)) {
            return 'Đơn đã hết thời gian giữ chỗ.';
        }

        return 'Khách cần tiến hành thanh toán trước khi hết hạn.';
    }
}
