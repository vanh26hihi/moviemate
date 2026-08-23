<?php

namespace App\Services\Admin;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentReconciliationExportService
{
    public function __construct(private readonly PaymentReconciliationQuery $queue) {}

    public function download(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Không thể mở luồng xuất hàng đợi đối soát.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Mức ưu tiên', 'Lý do', 'Mã giao dịch nội bộ', 'Mã đặt vé', 'Chi nhánh',
                'Provider', 'Số tiền booking', 'Số tiền giao dịch', 'Tiền tệ',
                'Trạng thái local', 'Lần kiểm tra gần nhất', 'Ngày tạo',
            ]);

            foreach ($this->rows() as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, 'moviemate-payment-reconciliation.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return Generator<int, list<string|int>> */
    private function rows(): Generator
    {
        $query = $this->queue->attentionQuery()
            ->join('cinemas', 'cinemas.id', '=', 'bookings.cinema_id')
            ->select([
                'payments.id', 'payments.booking_id', 'payments.provider', 'payments.amount',
                'payments.currency', 'payments.status', 'payments.last_queried_at',
                'payments.created_at', 'bookings.booking_code', 'bookings.total_amount',
                'cinemas.name as cinema_name',
            ])
            ->selectRaw($this->prioritySql().' as reconciliation_priority')
            ->selectRaw($this->reasonSql().' as reconciliation_reason')
            ->orderByRaw("CASE reconciliation_priority WHEN 'Khẩn cấp' THEN 1 WHEN 'Cao' THEN 2 ELSE 3 END")
            ->orderBy('payments.created_at')
            ->orderBy('payments.id');

        foreach ($query->cursor() as $payment) {
            yield [
                $this->safeCell((string) $payment->reconciliation_priority),
                $this->safeCell((string) $payment->reconciliation_reason),
                (int) $payment->id,
                $this->safeCell((string) $payment->booking_code),
                $this->safeCell((string) $payment->cinema_name),
                $this->safeCell((string) $payment->provider),
                (int) $payment->total_amount,
                (int) $payment->amount,
                $this->safeCell((string) $payment->currency),
                $this->safeCell((string) $payment->status),
                $payment->last_queried_at ?? 'Chưa truy vấn',
                (string) $payment->created_at,
            ];
        }
    }

    private function prioritySql(): string
    {
        return "CASE
            WHEN payments.amount <> bookings.total_amount THEN 'Khẩn cấp'
            WHEN payments.status = 'success' AND payments.verified_at IS NULL THEN 'Khẩn cấp'
            WHEN payments.status = 'success' AND payments.verified_at IS NOT NULL
                AND (bookings.payment_status <> 'paid' OR bookings.booking_status <> 'paid') THEN 'Khẩn cấp'
            WHEN bookings.payment_status = 'paid' AND NOT EXISTS (
                SELECT 1 FROM payments verified_payments
                WHERE verified_payments.booking_id = bookings.id
                AND verified_payments.status = 'success' AND verified_payments.verified_at IS NOT NULL
            ) THEN 'Khẩn cấp'
            WHEN payments.status = 'failed' AND payments.failure_reason = 'query_failed' THEN 'Cao'
            WHEN payments.status IN ('review', 'unresolved') OR payments.reconcile_until <= CURRENT_TIMESTAMP THEN 'Cao'
            ELSE 'Bình thường' END";
    }

    private function reasonSql(): string
    {
        return "CASE
            WHEN payments.amount <> bookings.total_amount THEN 'Số tiền giao dịch không khớp tổng đơn'
            WHEN payments.status = 'success' AND payments.verified_at IS NULL THEN 'Trạng thái thành công chưa có bằng chứng xác minh'
            WHEN payments.status = 'success' AND payments.verified_at IS NOT NULL
                AND bookings.payment_status <> 'paid' THEN 'Giao dịch đã xác minh nhưng đơn chưa đồng bộ'
            WHEN bookings.payment_status = 'paid' AND NOT EXISTS (
                SELECT 1 FROM payments verified_payments
                WHERE verified_payments.booking_id = bookings.id
                AND verified_payments.status = 'success' AND verified_payments.verified_at IS NOT NULL
            ) THEN 'Đơn ghi nhận đã thanh toán nhưng thiếu giao dịch có thẩm quyền'
            WHEN payments.status = 'review' THEN 'Giao dịch đang chờ kiểm tra'
            WHEN payments.status = 'unresolved' THEN 'Kết quả provider chưa xác định'
            WHEN payments.status = 'failed' AND payments.failure_reason = 'query_failed' THEN 'Provider xác nhận giao dịch thất bại qua truy vấn'
            WHEN payments.reconcile_until <= CURRENT_TIMESTAMP THEN 'Đã quá thời hạn đối soát'
            ELSE 'Giao dịch chờ provider đã lâu' END";
    }

    private function safeCell(string $value): string
    {
        $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}