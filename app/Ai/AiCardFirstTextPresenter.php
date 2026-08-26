<?php

namespace App\Ai;

final class AiCardFirstTextPresenter
{
    /** @param list<array<string, mixed>> $cards */
    public function present(string $text, array $cards): string
    {
        $text = trim($text);
        if ($cards === []) {
            return $text;
        }

        $types = collect($cards)->pluck('type')->filter()->unique()->values();
        $count = count($cards);

        if ($types->count() !== 1) {
            return "Mình tìm thấy {$count} kết quả phù hợp từ MovieMate:";
        }

        return match ($types->first()) {
            'movie' => collect($cards)->every(fn (array $card): bool => ($card['context'] ?? null) === 'recommendation')
                ? "Mình tìm thấy {$count} phim phù hợp với bạn từ dữ liệu MovieMate:"
                : "Mình tìm thấy {$count} phim trên MovieMate:",
            'showtime' => "Mình tìm thấy {$count} suất chiếu phù hợp:",
            'cinema' => "Mình tìm thấy {$count} rạp MovieMate:",
            'food' => "Mình tìm thấy {$count} lựa chọn bắp nước trong danh mục MovieMate:",
            default => "Mình tìm thấy {$count} kết quả phù hợp từ MovieMate:",
        };
    }
}
