<?php

namespace App\Services;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Ai\MovieMateAiRuntime;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class AiChatbotService
{
    public function __construct(
        private readonly MovieMateCinemaAssistant $assistant,
        private readonly MovieMateAiRuntime $runtime,
        private readonly CustomerMovieReadService $movies,
        private readonly CustomerShowtimeReadService $showtimes,
        private readonly PublicCinemaReadService $cinemas,
        private readonly PublicFoodReadService $foods,
    ) {}

    public function answer(string $message): array
    {
        $message = trim($message);

        if ($this->runtime->enabledAndConfigured()) {
            try {
                return [
                    'answer' => $this->runtime->prompt($this->assistant, $message),
                    'source' => $this->runtime->provider(),
                    'message' => null,
                ];
            } catch (\Throwable $exception) {
                Log::warning('AI chatbot failed, using grounded fallback.', ['exception' => $exception::class]);
            }
        }

        return [
            'answer' => $this->fallbackAnswer($message),
            'source' => 'fallback',
            'message' => 'Đang dùng trợ lý dự phòng từ dữ liệu MovieMate vì AI chưa được bật, chưa cấu hình hoặc tạm thời không phản hồi.',
        ];
    }

    private function fallbackAnswer(string $message): string
    {
        $normalized = Str::lower($message);

        if ($this->containsAny($normalized, ['khuyến mãi', 'khuyen mai', 'promotion', 'mã giảm', 'ma giam'])) {
            return 'MovieMate chưa công bố danh mục khuyến mãi để trợ lý có thể liệt kê an toàn. Điều kiện áp dụng và tối đa một khuyến mãi sẽ được xác định trong luồng đặt vé bình thường.';
        }
        if ($this->containsAny($normalized, ['bắp', 'bap', 'nước', 'nuoc', 'đồ ăn', 'do an', 'food', 'combo'])) {
            $catalog = $this->foods->list(limit: 6);
            if ($catalog['items'] === []) {
                return 'Hiện MovieMate chưa có món ăn công khai đang hoạt động.';
            }

            return "Một số món trong danh mục công khai MovieMate:\n".collect($catalog['items'])
                ->map(fn (array $food): string => '- '.$food['name'].' — '.number_format($food['price_vnd'], 0, ',', '.').'đ')
                ->implode("\n")."\nKhả dụng tại từng chi nhánh chỉ được xác nhận trong luồng đặt vé.";
        }
        if ($this->containsAny($normalized, ['đặt vé', 'dat ve', 'booking', 'chọn ghế', 'chon ghe', 'thanh toán', 'thanh toan'])) {
            return "Cách đặt vé trên MovieMate:\n1. Chọn phim và một suất chiếu có nút đặt vé do MovieMate xác nhận.\n2. Chọn ghế và kiểm tra thông tin.\n3. Hoàn tất trong luồng thanh toán MovieMate.\n4. Xem kết quả trong Đơn đặt vé của tôi.";
        }
        if ($this->containsAny($normalized, ['rạp', 'rap', 'cinema', 'địa chỉ', 'dia chi', 'ở đâu', 'o dau'])) {
            $cinemas = $this->cinemas->list(limit: 6);
            if ($cinemas->isEmpty()) {
                return 'Hiện MovieMate chưa có dữ liệu rạp đang hoạt động.';
            }

            return "Các rạp MovieMate đang hoạt động:\n".$cinemas->map(fn (array $cinema): string => '- '.$cinema['name'].' — '.$cinema['address'].', '.$cinema['city'].($cinema['phone'] ? ' — '.$cinema['phone'] : '')
            )->implode("\n");
        }
        if ($this->containsAny($normalized, ['lịch', 'lich', 'suất', 'suat', 'giờ', 'gio', 'hôm nay', 'hom nay', 'tối nay', 'toi nay'])) {
            $showtimes = $this->showtimes->find(limit: 8);
            if ($showtimes->isEmpty()) {
                return 'Hiện chưa có suất chiếu được MovieMate xác nhận là còn nhận đặt vé.';
            }

            return "Các suất chiếu còn nhận đặt vé:\n".$showtimes->map(fn (array $showtime): string => '- '.$showtime['movie']['title'].' — '.$showtime['date'].' '.$showtime['start_time'].' tại '.$showtime['cinema']['name'].' (từ '.number_format((int) $showtime['starting_price_vnd'], 0, ',', '.').'đ)'
            )->implode("\n");
        }
        if ($this->containsAny($normalized, ['giá', 'gia', 'bao nhiêu', 'bao nhieu', 'vé', 've'])) {
            $showtimes = $this->showtimes->find(limit: 6);
            if ($showtimes->isEmpty()) {
                return 'Hiện chưa có suất chiếu còn nhận đặt vé để kiểm tra giá snapshot.';
            }

            return "Giá khởi điểm theo suất chiếu MovieMate:\n".$showtimes->map(fn (array $showtime): string => '- '.$showtime['movie']['title'].' tại '.$showtime['cinema']['name'].' — từ '.number_format((int) $showtime['starting_price_vnd'], 0, ',', '.').'đ'
            )->implode("\n")."\nTổng thanh toán cuối cùng do luồng đặt vé xác định.";
        }
        if ($this->containsAny($normalized, ['phim', 'hay', 'đang chiếu', 'dang chieu', 'thể loại', 'the loai', 'hành động', 'hanh dong', 'gia đình', 'gia dinh'])) {
            $movies = $this->movies->search(limit: 5);
            if ($movies->isEmpty()) {
                return 'Hiện MovieMate chưa có phim công khai phù hợp trong hệ thống.';
            }

            return "Một số phim công khai trên MovieMate:\n".$movies->map(fn (array $movie): string => '- '.$movie['title'].' ('.(implode(', ', $movie['genres']) ?: 'chưa cập nhật thể loại').', '.$movie['duration_minutes'].' phút, '.$movie['age_rating'].', trạng thái '.$movie['status'].')'
            )->implode("\n");
        }

        return 'Tôi có thể hỗ trợ về phim, suất chiếu còn nhận đặt vé, rạp, giá snapshot, món ăn công khai và cách đặt vé trên MovieMate.';
    }

    private function containsAny(string $message, array $needles): bool
    {
        return collect($needles)->contains(fn ($needle): bool => Str::contains($message, $needle));
    }
}
