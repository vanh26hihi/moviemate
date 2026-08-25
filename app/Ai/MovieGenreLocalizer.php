<?php

namespace App\Ai;

final class MovieGenreLocalizer
{
    /** @var array<string, string> */
    private const VIETNAMESE = [
        'action' => 'Hành động',
        'adventure' => 'Phiêu lưu',
        'animation' => 'Hoạt hình',
        'comedy' => 'Hài',
        'crime' => 'Tội phạm',
        'documentary' => 'Tài liệu',
        'drama' => 'Chính kịch',
        'family' => 'Gia đình',
        'fantasy' => 'Kỳ ảo',
        'history' => 'Lịch sử',
        'horror' => 'Kinh dị',
        'music' => 'Âm nhạc',
        'mystery' => 'Bí ẩn',
        'romance' => 'Lãng mạn',
        'science fiction' => 'Khoa học viễn tưởng',
        'sci-fi' => 'Khoa học viễn tưởng',
        'thriller' => 'Giật gân',
        'war' => 'Chiến tranh',
        'western' => 'Viễn Tây',
    ];

    public function localize(string $genre): string
    {
        $genre = trim($genre);

        return self::VIETNAMESE[mb_strtolower($genre)] ?? $genre;
    }

    /** @param list<string> $genres
     * @return list<string>
     */
    public function localizeList(array $genres): array
    {
        return collect($genres)->map(fn (string $genre): string => $this->localize($genre))
            ->unique()->values()->all();
    }
}
