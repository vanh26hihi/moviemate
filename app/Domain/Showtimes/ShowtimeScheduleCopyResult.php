<?php

namespace App\Domain\Showtimes;

final readonly class ShowtimeScheduleCopyResult
{
    /**
     * @param  list<array{row_key: string, movie_id: int, presentation_format_id: int, room_id: int, show_date: string, show_time: string}>  $rows
     */
    public function __construct(
        public string $scope,
        public int $cinemaId,
        public string $sourceDate,
        public string $targetDate,
        public array $rows,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'cinema_id' => $this->cinemaId,
            'source_date' => $this->sourceDate,
            'target_date' => $this->targetDate,
            'generated_count' => count($this->rows),
            'message' => 'Đã tạo '.count($this->rows).' dòng nháp từ lịch đang hoạt động. Hãy kiểm tra lịch trước khi đăng.',
            'rows' => $this->rows,
        ];
    }
}
