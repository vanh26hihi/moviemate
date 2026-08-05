<?php

namespace App\Support;

final class StatusLabel
{
    /** @var array<string, array<string, string>> */
    private const LABELS = [
        'booking' => [
            'pending' => 'Chờ xử lý',
            'pending_payment' => 'Chờ thanh toán',
            'paid' => 'Chưa sử dụng',
            'used' => 'Đã sử dụng',
            'cancelled' => 'Đã hủy',
            'expired' => 'Đã hết hạn',
            'review' => 'Cần kiểm tra',
        ],
        'payment' => [
            'pending' => 'Chờ thanh toán',
            'processing' => 'Đang xử lý',
            'unresolved' => 'Chưa xác minh',
            'success' => 'Thành công',
            'failed' => 'Thất bại',
            'expired' => 'Đã hết hạn',
            'review' => 'Cần kiểm tra',
            'reversed' => 'Đã hoàn tác',
            'refunded' => 'Đã hoàn tiền',
        ],
        'ticket_delivery' => [
            'pending' => 'Chờ gửi',
            'processing' => 'Đang gửi',
            'claimed' => 'Đã tiếp nhận',
            'retry' => 'Chờ gửi lại',
            'failed' => 'Gửi thất bại',
            'sent' => 'Đã gửi',
            'missing' => 'Thiếu thông tin',
        ],
        'movie' => [
            'now_showing' => 'Đang chiếu',
            'coming_soon' => 'Sắp chiếu',
            'stopped' => 'Ngừng chiếu',
            'archived' => 'Đã lưu trữ',
        ],
        'showtime' => [
            'active' => 'Đang hoạt động',
            'inactive' => 'Ngừng hoạt động',
            'cancelled' => 'Đã hủy',
            'finished' => 'Đã kết thúc',
            'completed' => 'Đã hoàn thành',
        ],
        'user' => [
            'active' => 'Đang hoạt động',
            'inactive' => 'Ngừng hoạt động',
            'locked' => 'Đã khóa',
            'suspended' => 'Đã tạm khóa',
        ],
        'room' => [
            'active' => 'Đang hoạt động',
            'inactive' => 'Ngừng hoạt động',
            'maintenance' => 'Đang bảo trì',
            'archived' => 'Đã lưu trữ',
        ],
        'layout' => [
            'draft' => 'Bản nháp',
            'published' => 'Đã phát hành',
            'retired' => 'Đã ngừng sử dụng',
        ],
        'seat' => [
            'active' => 'Đang sử dụng',
            'maintenance' => 'Đang bảo trì',
            'inactive' => 'Không sử dụng',
            'retired' => 'Đã ngừng sử dụng',
        ],
        'seat_type' => [
            'normal' => 'Ghế thường',
            'vip' => 'Ghế VIP',
            'couple' => 'Ghế đôi',
        ],
        'food_order' => [
            'pending' => 'Chờ xử lý',
            'paid' => 'Đã thanh toán',
            'cancelled' => 'Đã hủy',
            'completed' => 'Đã hoàn thành',
        ],
        'role' => [
            'admin' => 'Quản trị viên',
            'manager' => 'Quản lý',
            'staff' => 'Nhân viên',
            'user' => 'Khách hàng',
        ],
        'generic' => [
            'active' => 'Đang hoạt động',
            'inactive' => 'Ngừng hoạt động',
            'maintenance' => 'Đang bảo trì',
            'archived' => 'Đã lưu trữ',
            'draft' => 'Bản nháp',
            'published' => 'Đã phát hành',
        ],
    ];

    public static function for(string $domain, ?string $status): string
    {
        if ($status === null || $status === '') {
            return 'Chưa xác định';
        }

        return self::LABELS[$domain][$status]
            ?? self::LABELS['generic'][$status]
            ?? 'Chưa xác định';
    }

    /** @return array<string, string> */
    public static function options(string $domain): array
    {
        return self::LABELS[$domain] ?? [];
    }
}
