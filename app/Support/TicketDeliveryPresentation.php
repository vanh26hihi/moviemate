<?php

namespace App\Support;

final class TicketDeliveryPresentation
{
    public static function error(?string $code): string
    {
        return match ($code) {
            null, '' => 'Không có lỗi được ghi nhận',
            'smtp_authentication_failed' => 'Xác thực SMTP thất bại',
            'smtp_connection_failed' => 'Không thể kết nối máy chủ email',
            'recipient_missing', 'recipient_invalid' => 'Địa chỉ email không hợp lệ',
            'timeout', 'connection_timed_out' => 'Quá thời gian gửi',
            'temporary_server_error' => 'Lỗi tạm thời của máy chủ email',
            'booking_not_paid' => 'Đơn không còn đủ điều kiện nhận vé',
            'delivery_lease_lost' => 'Quyền xử lý đã được chuyển cho tiến trình khác',
            default => 'Lỗi gửi tài liệu nhận vé chưa xác định',
        };
    }
}
