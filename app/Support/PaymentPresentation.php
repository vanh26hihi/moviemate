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
        'vnpay_customer_cancelled' => 'Khách hàng đã hủy giao dịch tại VNPAY',
        'payos_cancelled' => 'payOS xác nhận giao dịch đã hủy',
        'payos_pending' => 'payOS đang chờ thanh toán',
        'payos_processing' => 'payOS đang xử lý giao dịch',
        'payos_unknown_status' => 'payOS trả về trạng thái chưa xác định',
        'payos_response_schema_invalid' => 'Phản hồi payOS không đúng cấu trúc',
        'payos_order_code_mismatch' => 'Mã đơn payOS không khớp',
        'payos_payment_link_mismatch' => 'Mã liên kết payOS không khớp',
        'payos_currency_mismatch' => 'Đơn vị tiền payOS không khớp',
        'payos_amount_paid_mismatch' => 'Số tiền payOS ghi nhận đã thanh toán không khớp',
        'late_paid_after_payos_cancelled' => 'payOS ghi nhận thanh toán sau khi liên kết đã được xác nhận hủy',
        'create_response_identity_mismatch' => 'Liên kết thanh toán payOS không khớp lần thử',
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
        if ($payment->provider === Payment::PROVIDER_COUNTER_CASH && $payment->hasAuthoritativeSuccessEvidence()) {
            return 'Đã thu tiền mặt tại quầy';
        }
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

    public static function reviewCategory(?string $category): string
    {
        return match ($category) {
            'query_started' => 'Đã bắt đầu truy vấn provider',
            'authoritative_success' => 'Thành công có bằng chứng provider',
            'validation_rejected' => 'Bằng chứng không vượt qua kiểm tra an toàn',
            'authentication_error', 'authentication_rejected' => 'Provider từ chối xác thực',
            'not_successful' => 'Provider chưa xác nhận thành công',
            'uncertain', 'transport_error', 'invalid_response' => 'Kết quả provider chưa chắc chắn',
            default => 'Cần kiểm tra thêm',
        };
    }

    public static function providerLabel(?string $provider): string
    {
        return match ($provider) {
            Payment::PROVIDER_COUNTER_CASH => 'Tiền mặt tại quầy',
            'vnpay' => 'VNPAY',
            'zalopay' => 'ZaloPay',
            'payos' => 'payOS',
            default => 'Cổng thanh toán',
        };
    }
}
