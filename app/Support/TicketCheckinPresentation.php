<?php

namespace App\Support;

final class TicketCheckinPresentation
{
    public static function reason(?string $reason): string
    {
        return match ($reason) {
            'verified_paid_ticket' => 'Vé đã thanh toán và được xác minh',
            'booking_already_used' => 'Vé đã được sử dụng trước đó',
            'booking_not_paid' => 'Đơn chưa có thanh toán được xác minh',
            'booking_cancelled' => 'Đơn đặt vé đã hủy',
            'booking_expired' => 'Đơn đặt vé đã hết hạn',
            'invalid_capability' => 'Mã xác thực vé không hợp lệ',
            default => 'Không có thông tin bổ sung',
        };
    }
}
