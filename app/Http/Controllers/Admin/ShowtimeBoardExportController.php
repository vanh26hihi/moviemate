<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShowtimeBoardRequest;
use App\Services\Admin\ShowtimeOperationsBoard;
use App\Services\CinemaAccessService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ShowtimeBoardExportController extends Controller
{
    public function __invoke(
        ShowtimeBoardRequest $request,
        ShowtimeOperationsBoard $board,
        CinemaAccessService $cinemaAccess,
    ): StreamedResponse {
        $filters = $request->validated();
        $currentCinema = $cinemaAccess->currentCinema($request->user());
        $timezone = $currentCinema?->timezone ?: (string) config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $period = $request->period($timezone);
        $data = $board->build($request->user(), $filters, $period['from'], $period['to']);
        $filename = 'lich-suat-chieu-'.$period['from']->format('Ymd').'-'.$period['to']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Mã suất', 'Ngày', 'Bắt đầu', 'Hết phim', 'Phòng sẵn sàng',
                'Rạp', 'Phòng', 'Phim', 'Định dạng', 'Trạng thái',
                'Lượt đặt', 'Đã thanh toán',
            ]);

            foreach ($data['entries'] as $entry) {
                fputcsv($stream, array_map($this->csvCell(...), [
                    $entry['id'],
                    $entry['starts_at']->format('d/m/Y'),
                    $entry['starts_at']->format('H:i'),
                    $entry['movie_ends_at']?->format('H:i d/m/Y') ?? '',
                    $entry['room_ready_at']?->format('H:i d/m/Y') ?? '',
                    $entry['cinema'],
                    $entry['room'],
                    $entry['movie'],
                    $entry['format'],
                    $entry['lifecycle_label'],
                    $entry['bookings_count'],
                    $entry['paid_bookings_count'],
                ]));
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function csvCell(mixed $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', trim((string) $value));

        return preg_match('/^[=+\-@\t]/u', $value) ? "'{$value}" : $value;
    }
}
