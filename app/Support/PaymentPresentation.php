<?php

namespace App\Support;

use App\Models\Payment;

final class PaymentPresentation
{
    private const REASONS = [
        'amount_mismatch' => 'Số tiền không khớp tổng đơn',
        'ipn_amount_mismatch' => 'Số tiền IPN không khớp tổng đơn',
        'query_amount_invalid' => 'Provider trả về số tiền không hợp lệ',
        'query_identity_mismatch' => 'Mã tham chiếu provider không khớp',
        'zp_trans_id_mismatch' => 'Mã giao dịch ZaloPay không khớp',
        'provider_transaction_id_mismatch' => 'Mã giao dịch provider không khớp',
        'duplicate_zp_trans_id' => 'Mã giao dịch ZaloPay đã được sử dụng',
        'duplicate_provider_transaction_id' => 'Mã giao dịch provider đã được sử dụng',
        'late_payment_after_expiration' => 'Provider ghi nhận thanh toán sau khi đơn hết hạn',
        'reconciliation_window_elapsed' => 'Đã quá thời hạn đối soát tự động',
        'payment_attempt_expired' => 'Lần thử thanh toán đã hết hạn',
        'query_authentication_error' => 'Provider từ chối xác thực truy vấn',
        'query_transport_unknown' => 'Chưa kết nối được provider',
        'query_response_unknown' => 'Phản hồi provider chưa xác định',
        'query_response_schema_invalid' => 'Phản hồi provider không đúng cấu trúc',
        'query_pending' => 'Provider vẫn đang xử lý',
        'query_failed' => 'Provider xác nhận giao dịch thất bại',
        'query_expired' => 'Provider xác nhận giao dịch hết hạn',
        'query_unresolved' => 'Provider chưa xác định kết quả',
        'query_requires_review' => 'Provider yêu cầu kiểm tra thêm',
        'query_unknown_status' => 'Provider trả về trạng thái chưa xác định',
        'create_transport_unknown' => 'Chưa xác định kết quả khởi tạo do lỗi kết nối',
        'create_response_unknown' => 'Phản hồi khởi tạo chưa xác định',
        'create_missing_order_url' => 'Provider không trả về đường dẫn thanh toán hợp lệ',
        'create_rejected' => 'Provider từ chối khởi tạo giao dịch',
        'manual_review' => 'Giao dịch đang chờ kiểm tra',
        'duplicate_active_attempt_migration' => 'Phát hiện nhiều lần thử đang hoạt động',
    ];

    public static function reason(?string $reason): string
    {
        if ($reason === null || $reason === '') {
            return 'Không có';
        }
        if (str_starts_with($reason, 'ipn_status_')) {
            return 'IPN ghi nhận trạng thái provider chưa thành công';
        }

        return self::REASONS[$reason] ?? 'Cần kiểm tra thêm từ lịch sử provider';
    }

    public static function providerCategory(Payment $payment): string
    {
        if ($payment->status === Payment::STATUS_SUCCESS && $payment->verified_at !== null) {
            return 'Kết quả đã được chấp nhận và xác minh';
        }
        if ($payment->failure_reason) {
            return self::reason($payment->failure_reason);
        }
        if ($payment->response_code !== null || $payment->provider_return_code !== null) {
            return 'Đã nhận phản hồi provider';
        }

        return 'Chưa có kết quả provider';
    }
}
