<?php

namespace App\Services;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiChatbotService
{
    public function answer(string $message): array
    {
        $message = trim($message);
        $context = $this->databaseContext();
        $source = 'fallback';

        if ($this->hasApiKey()) {
            try {
                $answer = $this->requestAiAnswer($message, $context);
                $source = $this->provider();

                return [
                    'answer' => $answer,
                    'source' => $source,
                    'message' => null,
                ];
            } catch (\Throwable $exception) {
                Log::warning('AI chatbot failed, using fallback.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'answer' => $this->fallbackAnswer($message, $context),
            'source' => $source,
            'message' => 'Đang dùng chatbot fallback từ dữ liệu MovieMate vì chưa cấu hình API key hoặc AI tạm thời không phản hồi.',
        ];
    }

    protected function databaseContext(): array
    {
        $now = now('Asia/Ho_Chi_Minh');

        $showtimes = Showtime::query()
            ->with(['movie.genres', 'cinema', 'room'])
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereDate('show_date', '>', $now->toDateString())
                    ->orWhere(function ($query) use ($now) {
                        $query->whereDate('show_date', $now->toDateString())
                            ->whereTime('show_time', '>=', $now->copy()->subMinutes(30)->format('H:i:s'));
                    });
            })
            ->whereHas('movie', function ($query) {
                $query->whereIn('status', ['now_showing', 'coming_soon']);
            })
            ->whereHas('cinema', function ($query) {
                $query->where('status', 'active');
            })
            ->orderBy('show_date')
            ->orderBy('show_time')
            ->limit(40)
            ->get();

        $movies = Movie::query()
            ->with('genres')
            ->whereIn('status', ['now_showing', 'coming_soon'])
            ->orderByRaw("case when status = 'now_showing' then 0 else 1 end")
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $cinemas = Cinema::query()
            ->where('status', 'active')
            ->orderBy('city')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return [
            'movies' => $movies->map(fn (Movie $movie) => [
                'id' => $movie->id,
                'title' => $movie->title,
                'slug' => $movie->slug,
                'status' => $movie->status,
                'duration' => $movie->duration,
                'age_rating' => $movie->age_rating,
                'country' => $movie->country,
                'genres' => $movie->genres->pluck('name')->values()->all(),
                'description' => Str::limit((string) $movie->description, 350, ''),
            ])->values()->all(),
            'showtimes' => $showtimes->map(fn (Showtime $showtime) => [
                'movie_id' => $showtime->movie_id,
                'movie_title' => $showtime->movie?->title,
                'movie_slug' => $showtime->movie?->slug,
                'date' => $showtime->show_date?->format('Y-m-d'),
                'time' => Carbon::parse($showtime->show_time)->format('H:i'),
                'cinema' => $showtime->cinema?->name,
                'city' => $showtime->cinema?->city,
                'room' => $showtime->room?->name,
                'price' => (float) $showtime->price,
                'vip_price' => (float) ($showtime->vip_price ?? $showtime->price),
            ])->values()->all(),
            'cinemas' => $cinemas->map(fn (Cinema $cinema) => [
                'name' => $cinema->name,
                'address' => $cinema->address,
                'city' => $cinema->city,
                'phone' => $cinema->phone,
            ])->values()->all(),
        ];
    }

    protected function requestAiAnswer(string $message, array $context): string
    {
        return match ($this->provider()) {
            'gemini' => $this->requestGemini($message, $context),
            default => $this->requestOpenAi($message, $context),
        };
    }

    protected function requestOpenAi(string $message, array $context): string
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->withToken($this->apiKey())
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.ai.model', 'gpt-4o-mini'),
                'temperature' => 0.3,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'question' => $message,
                            'database_context' => $context,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

        $response->throw();

        return trim((string) $response->json('choices.0.message.content', ''));
    }

    protected function requestGemini(string $message, array $context): string
    {
        $model = config('services.ai.model', 'gemini-1.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::timeout(20)
            ->acceptJson()
            ->post($url.'?key='.urlencode($this->apiKey()), [
                'generationConfig' => [
                    'temperature' => 0.3,
                ],
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $this->systemPrompt()."\n\n".json_encode([
                                    'question' => $message,
                                    'database_context' => $context,
                                ], JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ],
            ]);

        $response->throw();

        return trim((string) $response->json('candidates.0.content.parts.0.text', ''));
    }

    protected function fallbackAnswer(string $message, array $context): string
    {
        $normalized = Str::lower($message);

        if ($this->containsAny($normalized, ['đặt vé', 'dat ve', 'booking', 'chọn ghế', 'chon ghe', 'thanh toán', 'thanh toan'])) {
            return "Cách đặt vé trên MovieMate:\n1. Vào trang Phim và chọn phim muốn xem.\n2. Mở phần Lịch chiếu, chọn suất chiếu còn hiệu lực.\n3. Chọn ghế, kiểm tra tổng tiền rồi xác nhận thanh toán.\n4. Sau khi đặt thành công, vé sẽ nằm trong mục Vé của tôi.";
        }

        if ($this->containsAny($normalized, ['rạp', 'rap', 'cinema', 'địa chỉ', 'dia chi', 'ở đâu', 'o dau'])) {
            return $this->cinemaAnswer($message, collect($context['cinemas']));
        }

        if ($this->containsAny($normalized, ['lịch', 'lich', 'suất', 'suat', 'giờ', 'gio', 'hôm nay', 'hom nay', 'tối nay', 'toi nay'])) {
            return $this->showtimeAnswer($message, collect($context['showtimes']));
        }

        if ($this->containsAny($normalized, ['giá', 'gia', 'bao nhiêu', 'bao nhieu', 'vé', 've'])) {
            return $this->priceAnswer(collect($context['showtimes']));
        }

        if ($this->containsAny($normalized, ['phim', 'hay', 'đang chiếu', 'dang chieu', 'thể loại', 'the loai', 'hành động', 'hanh dong', 'gia đình', 'gia dinh'])) {
            return $this->movieAnswer($message, collect($context['movies']), collect($context['showtimes']));
        }

        return 'Tôi có thể hỗ trợ bạn về phim đang chiếu, lịch chiếu, rạp và cách đặt vé trên MovieMate. Bạn có thể hỏi ví dụ: "Hôm nay có phim gì?", "Lịch chiếu tối nay", "Rạp ở đâu?" hoặc "Làm sao để đặt vé?".';
    }

    protected function movieAnswer(string $message, Collection $movies, Collection $showtimes): string
    {
        $matchedMovies = $this->filterByText($movies, $message, ['title', 'status', 'country'])
            ->whenEmpty(fn () => $movies->where('status', 'now_showing')->take(5));

        if ($matchedMovies->isEmpty()) {
            return 'Hiện MovieMate chưa có dữ liệu phim phù hợp trong hệ thống.';
        }

        $lines = ['Một số phim đang có trên MovieMate:'];

        foreach ($matchedMovies->take(5) as $movie) {
            $movieShowtimes = $showtimes
                ->where('movie_id', $movie['id'])
                ->take(3)
                ->map(fn ($showtime) => $showtime['date'].' '.$showtime['time'].' tại '.$showtime['cinema'])
                ->implode('; ');

            $genreText = empty($movie['genres']) ? 'chưa cập nhật thể loại' : implode(', ', $movie['genres']);
            $lines[] = '- '.$movie['title'].' ('.$genreText.', '.$movie['duration'].' phút, '.$movie['age_rating'].')'.($movieShowtimes ? "\n  Suất gần nhất: {$movieShowtimes}" : '');
        }

        return implode("\n", $lines);
    }

    protected function showtimeAnswer(string $message, Collection $showtimes): string
    {
        $matchedShowtimes = $this->filterByText($showtimes, $message, ['movie_title', 'cinema', 'city'])
            ->whenEmpty(fn () => $showtimes->take(8));

        if ($matchedShowtimes->isEmpty()) {
            return 'Hiện chưa có suất chiếu còn hiệu lực trong hệ thống.';
        }

        $lines = ['Các suất chiếu còn hiệu lực:'];

        foreach ($matchedShowtimes->take(8) as $showtime) {
            $lines[] = '- '.$showtime['movie_title'].' - '.$showtime['date'].' '.$showtime['time'].' tại '.$showtime['cinema'].' / '.$showtime['room'].' (từ '.number_format($showtime['price'], 0, ',', '.').'đ)';
        }

        return implode("\n", $lines);
    }

    protected function cinemaAnswer(string $message, Collection $cinemas): string
    {
        $matchedCinemas = $this->filterByText($cinemas, $message, ['name', 'city', 'address'])
            ->whenEmpty(fn () => $cinemas->take(6));

        if ($matchedCinemas->isEmpty()) {
            return 'Hiện MovieMate chưa có dữ liệu rạp đang hoạt động.';
        }

        $lines = ['Các rạp MovieMate đang hoạt động:'];

        foreach ($matchedCinemas->take(6) as $cinema) {
            $lines[] = '- '.$cinema['name'].' - '.$cinema['address'].', '.$cinema['city'].($cinema['phone'] ? ' - '.$cinema['phone'] : '');
        }

        return implode("\n", $lines);
    }

    protected function priceAnswer(Collection $showtimes): string
    {
        if ($showtimes->isEmpty()) {
            return 'Hiện chưa có suất chiếu còn hiệu lực để kiểm tra giá vé.';
        }

        $lines = ['Giá vé phụ thuộc từng suất chiếu. Một số suất gần nhất:'];

        foreach ($showtimes->take(6) as $showtime) {
            $lines[] = '- '.$showtime['movie_title'].' tại '.$showtime['cinema'].' lúc '.$showtime['date'].' '.$showtime['time'].': thường '.number_format($showtime['price'], 0, ',', '.').'đ, VIP '.number_format($showtime['vip_price'], 0, ',', '.').'đ';
        }

        return implode("\n", $lines);
    }

    protected function filterByText(Collection $items, string $message, array $fields): Collection
    {
        $tokens = collect(preg_split('/\s+/u', Str::lower($message)) ?: [])
            ->map(fn ($token) => trim($token, " \t\n\r\0\x0B,.!?;:()[]{}\"'"))
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->values();

        if ($tokens->isEmpty()) {
            return collect();
        }

        return $items->filter(function (array $item) use ($fields, $tokens) {
            $haystack = collect($fields)
                ->map(fn ($field) => Str::lower((string) ($item[$field] ?? '')))
                ->implode(' ');

            return $tokens->contains(fn ($token) => Str::contains($haystack, $token));
        })->values();
    }

    protected function containsAny(string $message, array $needles): bool
    {
        return collect($needles)->contains(fn ($needle) => Str::contains($message, $needle));
    }

    protected function systemPrompt(): string
    {
        return 'Bạn là chatbot hỗ trợ khách hàng của MovieMate. Chỉ trả lời các câu hỏi về phim, lịch chiếu, rạp, giá vé và cách đặt vé. Dùng database_context làm nguồn dữ liệu chính, không bịa phim/suất chiếu/rạp ngoài dữ liệu được cung cấp. Nếu không có dữ liệu, nói rõ là hiện chưa có thông tin trong hệ thống. Trả lời bằng tiếng Việt, ngắn gọn, thân thiện, có thể dùng gạch đầu dòng.';
    }

    protected function hasApiKey(): bool
    {
        return $this->apiKey() !== '';
    }

    protected function apiKey(): string
    {
        return trim((string) config('services.ai.key'));
    }

    protected function provider(): string
    {
        return strtolower((string) config('services.ai.provider', 'openai')) === 'gemini'
            ? 'gemini'
            : 'openai';
    }
}
