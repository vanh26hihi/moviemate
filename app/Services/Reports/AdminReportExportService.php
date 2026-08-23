<?php

namespace App\Services\Reports;

use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminReportExportService
{
    public function __construct(private readonly AdminReportingService $reports) {}

    public function download(ReportScope $scope): StreamedResponse
    {
        $filename = sprintf(
            'moviemate-report-%s-to-%s.csv',
            $scope->from->format('Ymd'),
            $scope->to->format('Ymd'),
        );

        return response()->streamDownload(function () use ($scope): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Không thể mở luồng xuất báo cáo.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Mã bản ghi', 'Ngày ghi nhận', 'Phim', 'Ngày chiếu', 'Giờ chiếu',
                'Chi nhánh', 'Kênh bán', 'Phương thức thanh toán', 'Đơn vị vé',
                'Số chỗ', 'Doanh thu', 'Tiền tệ',
            ]);

            foreach ($this->rows($scope) as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return Generator<int, list<string|int>> */
    private function rows(ReportScope $scope): Generator
    {
        foreach ($this->reports->financeRows($scope) as $row) {
            yield [
                $this->safeCell((string) $row->booking_id),
                $this->safeCell((string) $row->finance_paid_at),
                $this->safeCell((string) $row->movie_title),
                $this->safeCell((string) $row->show_date),
                $this->safeCell(substr((string) $row->show_time, 0, 8)),
                $this->safeCell((string) $row->cinema_name),
                $this->safeCell((string) $row->sales_channel),
                $this->safeCell((string) $row->provider),
                (int) $row->logical_tickets,
                (int) $row->physical_seats,
                (int) $row->amount,
                'VND',
            ];
        }
    }

    private function safeCell(string $value): string
    {
        $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}