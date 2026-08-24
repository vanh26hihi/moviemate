<?php

namespace App\Support;

use App\Models\Showtime;
use Carbon\CarbonInterface;

final class ShowtimePresentation
{
    public static function statusMeta(Showtime $showtime): array
    {
        $now = now();

        $startAt = self::startAt($showtime);

        if ($showtime->status === 'cancelled') {
            return [
                'key' => 'cancelled',
                'label' => 'Đã hủy',
                'description' => 'Suất chiếu đã bị hủy và không còn nhận đặt vé.',
                'icon' => 'ph-x-circle',
                'class' => 'bg-error/10 text-error border-error/20',
            ];
        }

        if ($showtime->status === 'finished') {
            return [
                'key' => 'finished',
                'label' => 'Đã kết thúc',
                'description' => 'Suất chiếu đã kết thúc.',
                'icon' => 'ph-check-circle',
                'class' => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
            ];
        }

        if ($startAt === null) {
            return [
                'key' => 'unknown',
                'label' => 'Chưa xác định',
                'description' => 'Thời gian suất chiếu chưa đầy đủ.',
                'icon' => 'ph-question',
                'class' => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
            ];
        }

        if ($startAt->isPast()) {
            return [
                'key' => 'started',
                'label' => 'Đã bắt đầu',
                'description' => 'Suất chiếu đã bắt đầu và không còn mở bán.',
                'icon' => 'ph-play-circle',
                'class' => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
            ];
        }

        if ($startAt->diffInMinutes($now) <= 30) {
            return [
                'key' => 'starting_soon',
                'label' => 'Sắp bắt đầu',
                'description' => 'Suất chiếu sẽ bắt đầu trong ít phút nữa.',
                'icon' => 'ph-clock-countdown',
                'class' => 'bg-warning/10 text-warning border-warning/20',
            ];
        }

        return [
            'key' => 'available',
            'label' => 'Đang mở bán',
            'description' => 'Suất chiếu đang nhận đặt vé.',
            'icon' => 'ph-ticket',
            'class' => 'bg-success/10 text-success border-success/20',
        ];
    }

    public static function startAt(Showtime $showtime): ?CarbonInterface
    {
        if (! $showtime->show_date || ! $showtime->show_time) {
            return null;
        }

        return $showtime->show_date
            ->copy()
            ->setTimeFromTimeString(
                (string) $showtime->show_time
            );
    }

    public static function endAt(Showtime $showtime): ?CarbonInterface
    {
        $startAt = self::startAt($showtime);

        if ($startAt === null) {
            return null;
        }

        $duration = (int) ($showtime->movie?->duration ?? 0);

        if ($duration <= 0) {
            return null;
        }

        return $startAt
            ->copy()
            ->addMinutes($duration);
    }

    public static function timeLabel(Showtime $showtime): string
    {
        $startAt = self::startAt($showtime);

        if ($startAt === null) {
            return '--:--';
        }

        return $startAt->format('H:i');
    }

    public static function dateLabel(Showtime $showtime): string
    {
        if (! $showtime->show_date) {
            return 'Chưa cập nhật';
        }

        return $showtime->show_date->format('d/m/Y');
    }

    public static function fullDateTimeLabel(Showtime $showtime): string
    {
        $startAt = self::startAt($showtime);

        if ($startAt === null) {
            return 'Chưa cập nhật';
        }

        return $startAt->format('H:i d/m/Y');
    }

    public static function timeRangeLabel(Showtime $showtime): string
    {
        $startAt = self::startAt($showtime);

        if ($startAt === null) {
            return '--:--';
        }

        $endAt = self::endAt($showtime);

        if ($endAt === null) {
            return $startAt->format('H:i');
        }

        return $startAt->format('H:i')
            .' - '
            .$endAt->format('H:i');
    }

    public static function durationLabel(Showtime $showtime): string
    {
        $duration = (int) ($showtime->movie?->duration ?? 0);

        if ($duration <= 0) {
            return 'Chưa cập nhật';
        }

        return $duration.' phút';
    }

    public static function roomLabel(Showtime $showtime): string
    {
        $room = $showtime->room;

        if (! $room) {
            return 'Phòng đang cập nhật';
        }

        return $room->name
            ?: $room->code
            ?: 'Phòng chiếu';
    }

    public static function cinemaLabel(Showtime $showtime): string
    {
        $cinema = $showtime->cinema;

        if (! $cinema) {
            return 'Rạp đang cập nhật';
        }

        return $cinema->name
            ?: 'Rạp chiếu phim';
    }

    public static function priceLabel(Showtime $showtime): string
    {
        return number_format(
            (int) $showtime->price,
            0,
            ',',
            '.'
        ).' VNĐ';
    }

    public static function vipPriceLabel(Showtime $showtime): ?string
    {
        if ($showtime->vip_price === null) {
            return null;
        }

        return number_format(
            (int) $showtime->vip_price,
            0,
            ',',
            '.'
        ).' VNĐ';
    }

    public static function countdownLabel(Showtime $showtime): ?string
    {
        $startAt = self::startAt($showtime);

        if ($startAt === null || $startAt->isPast()) {
            return null;
        }

        $minutes = now()->diffInMinutes(
            $startAt,
            false
        );

        if ($minutes <= 0) {
            return 'Đang bắt đầu';
        }

        if ($minutes < 60) {
            return 'Còn '.$minutes.' phút';
        }

        $hours = intdiv($minutes, 60);

        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return 'Còn '.$hours.' giờ';
        }

        return 'Còn '
            .$hours
            .' giờ '
            .$remainingMinutes
            .' phút';
    }

    public static function seatAvailabilityMeta(
        int $availableSeats,
        int $totalSeats
    ): array {
        if ($totalSeats <= 0) {
            return [
                'key' => 'unknown',
                'label' => 'Chưa có dữ liệu ghế',
                'class' => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
                'percentage' => 0,
            ];
        }

        $availableSeats = max(
            0,
            min($availableSeats, $totalSeats)
        );

        $percentage = (int) round(
            ($availableSeats / $totalSeats) * 100
        );

        if ($availableSeats === 0) {
            return [
                'key' => 'sold_out',
                'label' => 'Hết ghế',
                'class' => 'bg-error/10 text-error border-error/20',
                'percentage' => 0,
            ];
        }

        if ($percentage <= 15) {
            return [
                'key' => 'almost_sold_out',
                'label' => 'Sắp hết ghế',
                'class' => 'bg-error/10 text-error border-error/20',
                'percentage' => $percentage,
            ];
        }

        if ($percentage <= 40) {
            return [
                'key' => 'limited',
                'label' => 'Còn ít ghế',
                'class' => 'bg-warning/10 text-warning border-warning/20',
                'percentage' => $percentage,
            ];
        }

        return [
            'key' => 'available',
            'label' => 'Còn nhiều ghế',
            'class' => 'bg-success/10 text-success border-success/20',
            'percentage' => $percentage,
        ];
    }

    public static function canSelect(
        Showtime $showtime,
        ?int $availableSeats = null
    ): bool {
        if ($showtime->status !== 'active') {
            return false;
        }

        $startAt = self::startAt($showtime);

        if ($startAt === null || $startAt->isPast()) {
            return false;
        }

        if ($availableSeats !== null && $availableSeats <= 0) {
            return false;
        }

        return true;
    }

    public static function selectionReason(
        Showtime $showtime,
        ?int $availableSeats = null
    ): ?string {
        if ($showtime->status === 'cancelled') {
            return 'Suất chiếu đã bị hủy.';
        }

        if ($showtime->status === 'finished') {
            return 'Suất chiếu đã kết thúc.';
        }

        if ($showtime->status !== 'active') {
            return 'Suất chiếu hiện không mở bán.';
        }

        $startAt = self::startAt($showtime);

        if ($startAt === null) {
            return 'Thời gian suất chiếu chưa hợp lệ.';
        }

        if ($startAt->isPast()) {
            return 'Suất chiếu đã bắt đầu.';
        }

        if ($availableSeats !== null && $availableSeats <= 0) {
            return 'Suất chiếu đã hết ghế.';
        }

        return null;
    }

    public static function compactSummary(
        Showtime $showtime
    ): array {
        return [
            'time' => self::timeLabel($showtime),
            'date' => self::dateLabel($showtime),
            'range' => self::timeRangeLabel($showtime),
            'duration' => self::durationLabel($showtime),
            'room' => self::roomLabel($showtime),
            'cinema' => self::cinemaLabel($showtime),
            'price' => self::priceLabel($showtime),
            'vip_price' => self::vipPriceLabel($showtime),
            'countdown' => self::countdownLabel($showtime),
            'status' => self::statusMeta($showtime),
        ];
    }
}