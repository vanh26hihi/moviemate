<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Services\CinemaAccessService;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserBookingExportService
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    public function download(User $actor, User $target, array $filters): StreamedResponse
    {
        $filename = sprintf('moviemate-user-%d-bookings-%s.csv', $target->id, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($actor, $target, $filters): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Không thể mở luồng xuất dữ liệu.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Mã đơn', 'Ngày tạo', 'Phim', 'Suất chiếu', 'Chi nhánh', 'Phòng',
                'Trạng thái đơn', 'Trạng thái thanh toán', 'Tổng tiền', 'Tiền tệ',
            ]);

            foreach ($this->rows($actor, $target, $filters) as $row) {
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
    private function rows(User $actor, User $target, array $filters): Generator
    {
        $query = Booking::query()
            ->where('user_id', $target->id)
            ->with([
                'cinema:id,name',
                'showtime:id,movie_id,room_id,show_date,show_time',
                'showtime.movie:id,title',
                'showtime.room:id,name',
            ]);

        $this->cinemaAccess->scope($query, $actor, 'bookings.cinema_id');
        $this->applyFilters($query, $filters);

        foreach ($query->latest('id')->lazyById(200, column: 'id', alias: 'id') as $booking) {
            yield [
                $this->safeCell($booking->booking_code),
                $booking->created_at?->format('Y-m-d H:i:s') ?? '',
                $this->safeCell($booking->showtime?->movie?->title ?? ''),
                $booking->showtime?->show_date?->format('Y-m-d').' '.substr((string) $booking->showtime?->show_time, 0, 5),
                $this->safeCell($booking->cinema?->name ?? ''),
                $this->safeCell($booking->showtime?->room?->name ?? ''),
                $booking->booking_status,
                $booking->payment_status,
                (int) $booking->total_amount,
                strtoupper($booking->currency ?: 'VND'),
            ];
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['booking_search'] ?? null, function (Builder $query, string $search): void {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search));
                $query->where(function (Builder $query) use ($escaped): void {
                    $query->where('booking_code', 'like', "%{$escaped}%")
                        ->orWhereHas('showtime.movie', fn (Builder $movie) => $movie->where('title', 'like', "%{$escaped}%"));
                });
            })
            ->when($filters['booking_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('booking_status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date));
    }

    private function safeCell(string $value): string
    {
        $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
