<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomType;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ActivityLogger
{
    public function __construct(
        private readonly Request $request,
        private readonly ActivityLogSanitizer $sanitizer,
    ) {}

    public function log(
        string $action,
        Model $subject,
        array $before = [],
        array $after = [],
        array $context = [],
    ): ActivityLog {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $action) !== 1) {
            throw new InvalidArgumentException('Activity action must be a stable, lowercase identifier.');
        }

        $actor = $this->request->user();

        return ActivityLog::query()->create([
            'actor_user_id' => $actor?->getAuthIdentifier(),
            'actor_role_snapshot' => $actor?->role?->slug,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey() === null ? null : (string) $subject->getKey(),
            'subject_label' => $this->subjectLabel($subject),
            'request_id' => $this->requestId(),
            'route_name' => $this->routeName(),
            'method' => $this->request->method(),
            'safe_ip_hash' => $this->safeIpHash($this->request->ip()),
            'user_agent_summary' => $this->userAgentSummary($this->request->userAgent()),
            'before_data' => $this->sanitizer->sanitize($before),
            'after_data' => $this->sanitizer->sanitize($after),
            'context' => $this->sanitizer->sanitize($context),
        ]);
    }

    private function requestId(): string
    {
        $existing = $this->request->attributes->get('activity_request_id');
        if (is_string($existing)) {
            return $existing;
        }

        $header = trim((string) $this->request->header('X-Request-ID', ''));
        $requestId = preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $header) === 1
            ? $header
            : (string) Str::uuid();
        $this->request->attributes->set('activity_request_id', $requestId);

        return $requestId;
    }

    private function routeName(): ?string
    {
        $name = $this->request->route()?->getName();

        return is_string($name) ? Str::limit($name, 191, '') : null;
    }

    private function safeIpHash(?string $ip): ?string
    {
        $applicationKey = (string) config('app.key');
        if ($ip === null || $ip === '' || $applicationKey === '') {
            return null;
        }

        if (str_starts_with($applicationKey, 'base64:')) {
            $decoded = base64_decode(substr($applicationKey, 7), true);
            $applicationKey = $decoded === false ? $applicationKey : $decoded;
        }

        $derivedKey = hash_hmac('sha256', 'moviemate/activity-ip/v1', $applicationKey, true);

        return hash_hmac('sha256', $ip, $derivedKey);
    }

    private function userAgentSummary(?string $userAgent): ?string
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Trình duyệt khác',
        };
        $platform = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Nền tảng khác',
        };

        return "{$browser} / {$platform}";
    }

    private function subjectLabel(Model $subject): string
    {
        return match (true) {
            $subject instanceof Booking => 'Đơn đặt vé '.$subject->booking_code,
            $subject instanceof BookingTicketDelivery => 'Gửi vé điện tử #'.$subject->getKey().' / đơn #'.$subject->booking_id,
            $subject instanceof Payment => 'Giao dịch #'.$subject->getKey().' / '.$subject->provider,
            $subject instanceof Room => 'Phòng '.$subject->code,
            $subject instanceof RoomType => 'Loại phòng '.$subject->code,
            $subject instanceof RoomLayout => 'Sơ đồ #'.$subject->getKey().' / phòng #'.$subject->room_id,
            $subject instanceof Showtime => 'Suất chiếu #'.$subject->getKey(),
            $subject instanceof Seat => 'Ghế '.$subject->seat_code.' / phòng #'.$subject->room_id,
            $subject instanceof User => 'Người dùng #'.$subject->getKey(),
            $subject instanceof Role => 'Vai trò '.$subject->slug,
            default => class_basename($subject).' #'.$subject->getKey(),
        };
    }
}
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
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiMovieContentService
{
    public function generate(array $input): array
    {
        if ($this->hasApiKey()) {
            try {
                $content = $this->requestAiContent($input);

                return [
                    'source' => $this->provider(),
                    'content' => $this->normalizeContent($content, $input),
                    'message' => null,
                ];
            } catch (\Throwable $exception) {
                Log::warning('AI movie content generation failed, using fallback.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'source' => 'fallback',
            'content' => $this->fallbackContent($input),
            'message' => 'Đang dùng nội dung mẫu vì chưa cấu hình API key hoặc AI tạm thời không phản hồi.',
        ];
    }

    protected function requestAiContent(array $input): array
    {
        $content = match ($this->provider()) {
            'gemini' => $this->requestGemini($input),
            default => $this->requestOpenAi($input),
        };

        return $this->decodeAiJson($content);
    }

    protected function requestOpenAi(array $input): string
    {
        $response = Http::timeout(25)
            ->acceptJson()
            ->withToken($this->apiKey())
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.ai.model', 'gpt-4o-mini'),
                'temperature' => 0.7,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode($input, JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

        $response->throw();

        return (string) $response->json('choices.0.message.content', '');
    }

    protected function requestGemini(array $input): string
    {
        $model = config('services.ai.model', 'gemini-1.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::timeout(25)
            ->acceptJson()
            ->post($url.'?key='.urlencode($this->apiKey()), [
                'generationConfig' => [
                    'temperature' => 0.7,
                    'response_mime_type' => 'application/json',
                ],
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $this->systemPrompt()."\n\n".json_encode($input, JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ],
            ]);

        $response->throw();

        return (string) $response->json('candidates.0.content.parts.0.text', '');
    }

    protected function fallbackContent(array $input): array
    {
        $title = trim((string) $input['title']);
        $genres = trim((string) ($input['genres'] ?? 'điện ảnh'));
        $tone = $this->toneLabel((string) ($input['tone'] ?? 'attractive'));
        $base = trim((string) ($input['original_description'] ?? ''));
        $baseText = $base !== ''
            ? Str::limit($base, 180, '')
            : "{$title} là tác phẩm {$genres} mang đến một hành trình giàu cảm xúc, kịch tính và cuốn hút trên màn ảnh rộng.";

        return [
            'short_description' => "{$title} là lựa chọn {$tone} dành cho khán giả yêu thích {$genres}, với câu chuyện cuốn hút và trải nghiệm rạp đáng mong chờ.",
            'seo_description' => "{$title} thuộc thể loại {$genres}. {$baseText} Đặt vé xem {$title} tại MovieMate để cập nhật lịch chiếu mới nhất, chọn ghế nhanh và tận hưởng trải nghiệm điện ảnh trọn vẹn.",
            'facebook_caption' => "🎬 {$title} đã sẵn sàng lên màn ảnh MovieMate!\n\n{$baseText}\n\nBạn đã chọn được suất chiếu phù hợp chưa? Đặt vé ngay hôm nay để không bỏ lỡ trải nghiệm điện ảnh này.",
            'tiktok_caption' => "{$title} có gì đáng xem? Không khí {$tone}, thể loại {$genres}, và những khoảnh khắc cực hợp để ra rạp cùng hội bạn. #MovieMate #{$this->hashtag($title)} #PhimHay",
        ];
    }

    protected function normalizeContent(array $content, array $input): array
    {
        $fallback = $this->fallbackContent($input);

        return [
            'short_description' => trim((string) ($content['short_description'] ?? $fallback['short_description'])),
            'seo_description' => trim((string) ($content['seo_description'] ?? $fallback['seo_description'])),
            'facebook_caption' => trim((string) ($content['facebook_caption'] ?? $fallback['facebook_caption'])),
            'tiktok_caption' => trim((string) ($content['tiktok_caption'] ?? $fallback['tiktok_caption'])),
        ];
    }

    protected function decodeAiJson(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('AI response is not valid JSON.');
        }

        return $decoded;
    }

    protected function systemPrompt(): string
    {
        return 'Bạn là chuyên viên marketing phim cho MovieMate. Tạo nội dung tiếng Việt dựa trên tên phim, thể loại, mô tả gốc và tone. Không bịa thông tin cụ thể như diễn viên, đạo diễn, giải thưởng nếu input không cung cấp. Trả về JSON object đúng schema: {"short_description":"mô tả ngắn 1-2 câu","seo_description":"mô tả SEO 120-180 từ, có tên phim và thể loại","facebook_caption":"caption Facebook hấp dẫn, có CTA đặt vé","tiktok_caption":"caption TikTok ngắn, bắt trend vừa phải, có hashtag"}.';
    }

    protected function toneLabel(string $tone): string
    {
        return match ($tone) {
            'mysterious' => 'bí ẩn',
            'professional' => 'chuyên nghiệp',
            'funny' => 'vui nhộn',
            'emotional' => 'cảm xúc',
            default => 'hấp dẫn',
        };
    }

    protected function hashtag(string $title): string
    {
        $ascii = Str::ascii($title);
        $tag = preg_replace('/[^A-Za-z0-9]+/', '', $ascii) ?: 'MovieMate';

        return Str::limit($tag, 40, '');
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
<?php

namespace App\Services;

use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiMovieRecommendationService
{
    public function recommend(array $preferences, int $limit = 5): array
    {
        $candidates = $this->getAvailableMovieCandidates();

        if ($candidates->isEmpty()) {
            return [
                'source' => 'empty',
                'recommendations' => [],
                'available_count' => 0,
                'message' => 'Hiện chưa có phim đang chiếu với suất chiếu còn hiệu lực.',
            ];
        }

        $recommendations = collect();
        $source = 'fallback';
        $aiFailed = false;

        if ($this->hasApiKey()) {
            try {
                $recommendations = $this->requestAiRecommendations($preferences, $candidates, $limit);
                $source = $recommendations->isNotEmpty() ? $this->provider() : 'fallback';
            } catch (\Throwable $exception) {
                $aiFailed = true;
                Log::warning('AI movie recommendation failed, using fallback.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($recommendations->count() < min($limit, $candidates->count())) {
            $fallback = $this->fallbackRecommendations($preferences, $candidates, $limit);
            $recommendations = $recommendations
                ->merge($fallback)
                ->unique('movie_id')
                ->take($limit)
                ->values();

            if ($source !== 'openai' && $source !== 'gemini') {
                $source = 'fallback';
            }
        }

        return [
            'source' => $source,
            'recommendations' => $recommendations->values()->all(),
            'available_count' => $candidates->count(),
            'message' => match (true) {
                $source !== 'fallback' => null,
                ! $this->hasApiKey() => 'Chưa cấu hình AI_API_KEY. Kết quả bên dưới được xếp hạng trực tiếp từ thể loại, tâm trạng, thời gian và rạp bạn đã chọn.',
                $aiFailed => 'Dịch vụ AI tạm thời không phản hồi. MovieMate đã chuyển sang bộ gợi ý nội bộ.',
                default => 'AI không trả về kết quả hợp lệ. MovieMate đã chuyển sang bộ gợi ý nội bộ.',
            },
        ];
    }

    protected function getAvailableMovieCandidates(): Collection
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
                $query->where('status', 'now_showing');
            })
            ->whereHas('cinema', function ($query) {
                $query->where('status', 'active');
            })
            ->orderBy('show_date')
            ->orderBy('show_time')
            ->get();

        return $showtimes
            ->groupBy('movie_id')
            ->map(function (Collection $movieShowtimes) {
                $movie = $movieShowtimes->first()->movie;

                return [
                    'movie_id' => $movie->id,
                    'title' => $movie->title,
                    'slug' => $movie->slug,
                    'description' => Str::limit((string) $movie->description, 500, ''),
                    'poster' => $movie->poster,
                    'duration' => $movie->duration,
                    'age_rating' => $movie->age_rating,
                    'country' => $movie->country,
                    'genres' => $movie->genres->pluck('name')->values()->all(),
                    'showtimes' => $movieShowtimes->take(8)->map(function (Showtime $showtime) {
                        return [
                            'id' => $showtime->id,
                            'date' => Carbon::parse($showtime->show_date)->format('Y-m-d'),
                            'time' => Carbon::parse($showtime->show_time)->format('H:i'),
                            'cinema' => $showtime->cinema?->name,
                            'city' => $showtime->cinema?->city,
                            'room' => $showtime->room?->name,
                            'price' => (float) $showtime->price,
                        ];
                    })->values()->all(),
                ];
            })
            ->values();
    }

    protected function requestAiRecommendations(array $preferences, Collection $candidates, int $limit): Collection
    {
        $content = match ($this->provider()) {
            'gemini' => $this->requestGemini($preferences, $candidates, $limit),
            default => $this->requestOpenAi($preferences, $candidates, $limit),
        };

        $decoded = $this->decodeAiJson($content);
        $candidateMap = $candidates->keyBy('movie_id');

        return collect($decoded['recommendations'] ?? [])
            ->map(function ($item) use ($candidateMap) {
                $movieId = (int) ($item['movie_id'] ?? 0);

                if (! $candidateMap->has($movieId)) {
                    return null;
                }

                $candidate = $candidateMap->get($movieId);
                $score = (int) ($item['score'] ?? 85);

                return $this->formatRecommendation(
                    $candidate,
                    max(1, min(100, $score)),
                    (string) ($item['reason'] ?? 'Phim phù hợp với lựa chọn và còn suất chiếu hợp lệ.')
                );
            })
            ->filter()
            ->unique('movie_id')
            ->values();
    }

    protected function requestOpenAi(array $preferences, Collection $candidates, int $limit): string
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->withToken($this->apiKey())
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.ai.model', 'gpt-4o-mini'),
                'temperature' => 0.4,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt($limit),
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'preferences' => $preferences,
                            'available_movies' => $this->candidatePayload($candidates),
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

        $response->throw();

        return (string) $response->json('choices.0.message.content', '');
    }

    protected function requestGemini(array $preferences, Collection $candidates, int $limit): string
    {
        $model = config('services.ai.model', 'gemini-1.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::timeout(20)
            ->acceptJson()
            ->post($url.'?key='.urlencode($this->apiKey()), [
                'generationConfig' => [
                    'temperature' => 0.4,
                    'response_mime_type' => 'application/json',
                ],
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $this->systemPrompt($limit)."\n\n".json_encode([
                                    'preferences' => $preferences,
                                    'available_movies' => $this->candidatePayload($candidates),
                                ], JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ],
            ]);

        $response->throw();

        return (string) $response->json('candidates.0.content.parts.0.text', '');
    }

    protected function fallbackRecommendations(array $preferences, Collection $candidates, int $limit): Collection
    {
        return $candidates
            ->map(function (array $candidate) use ($preferences) {
                $score = 50;
                $reasons = [];
                $preferredGenres = collect($preferences['genres'] ?? [])
                    ->map(fn ($genre) => Str::lower((string) $genre))
                    ->filter();
                $movieGenres = collect($candidate['genres'])
                    ->map(fn ($genre) => Str::lower((string) $genre));

                $matchedGenres = $movieGenres->filter(function ($genre) use ($preferredGenres) {
                    return $preferredGenres->contains(fn ($preferred) => Str::contains($genre, $preferred) || Str::contains($preferred, $genre));
                });

                if ($matchedGenres->isNotEmpty()) {
                    $score += min(30, $matchedGenres->count() * 15);
                    $reasons[] = 'trùng thể loại bạn yêu thích';
                }

                $location = Str::lower((string) ($preferences['location'] ?? ''));
                if ($location !== '') {
                    $hasLocation = collect($candidate['showtimes'])->contains(function ($showtime) use ($location) {
                        return Str::contains(Str::lower((string) $showtime['cinema']), $location)
                            || Str::contains(Str::lower((string) $showtime['city']), $location);
                    });

                    if ($hasLocation) {
                        $score += 15;
                        $reasons[] = 'có suất chiếu đúng khu vực/rạp mong muốn';
                    }
                }

                if ($this->matchesPreferredTime($candidate, (string) ($preferences['preferred_time'] ?? ''))) {
                    $score += 10;
                    $reasons[] = 'có khung giờ phù hợp';
                }

                if ((string) ($preferences['companion'] ?? '') === 'family' && preg_match('/^(P|K)$/i', (string) $candidate['age_rating'])) {
                    $score += 8;
                    $reasons[] = 'phù hợp khi đi cùng gia đình';
                }

                $moodGenres = $this->moodGenres((string) ($preferences['mood'] ?? ''));
                $matchesMood = $movieGenres->contains(function ($genre) use ($moodGenres) {
                    return collect($moodGenres)->contains(fn ($expected) => Str::contains($genre, $expected));
                });

                if ($matchesMood) {
                    $score += 18;
                    $reasons[] = 'hợp với tâm trạng hiện tại của bạn';
                }

                $companionGenres = $this->companionGenres((string) ($preferences['companion'] ?? ''));
                $matchesCompanion = $movieGenres->contains(function ($genre) use ($companionGenres) {
                    return collect($companionGenres)->contains(fn ($expected) => Str::contains($genre, $expected));
                });

                if ($matchesCompanion) {
                    $score += 8;
                    $reasons[] = 'phù hợp với người đi cùng';
                }

                $reason = 'Gợi ý từ dữ liệu MovieMate vì phim '.implode(', ', array_slice($reasons ?: ['còn suất chiếu hợp lệ'], 0, 3)).'.';

                return $this->formatRecommendation($candidate, min(98, $score), $reason);
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    protected function moodGenres(string $mood): array
    {
        return match (Str::lower($mood)) {
            'happy' => ['comedy', 'animation', 'family', 'hài', 'hoạt hình'],
            'sad' => ['comedy', 'romance', 'drama', 'hài', 'tình cảm', 'chính kịch'],
            'stress', 'chill' => ['comedy', 'romance', 'animation', 'family', 'hài', 'tình cảm', 'hoạt hình'],
            'excited' => ['action', 'adventure', 'science fiction', 'horror', 'hành động', 'phiêu lưu', 'khoa học', 'kinh dị'],
            'romantic' => ['romance', 'drama', 'tình cảm', 'chính kịch'],
            default => [],
        };
    }

    protected function companionGenres(string $companion): array
    {
        return match (Str::lower($companion)) {
            'couple' => ['romance', 'comedy', 'drama', 'tình cảm', 'hài'],
            'friends' => ['action', 'comedy', 'horror', 'adventure', 'hành động', 'hài', 'kinh dị'],
            'family' => ['animation', 'family', 'comedy', 'hoạt hình', 'gia đình', 'hài'],
            default => [],
        };
    }

    protected function matchesPreferredTime(array $candidate, string $preferredTime): bool
    {
        $preferredTime = Str::lower($preferredTime);

        if ($preferredTime === '') {
            return false;
        }

        return collect($candidate['showtimes'])->contains(function ($showtime) use ($preferredTime) {
            $hour = (int) substr((string) $showtime['time'], 0, 2);
            $date = Carbon::parse($showtime['date'], 'Asia/Ho_Chi_Minh');

            return match ($preferredTime) {
                'tonight' => $date->isToday() && $hour >= 18,
                'tomorrow' => $date->isTomorrow(),
                'weekend' => $date->isWeekend(),
                'after_21' => $hour >= 21,
                'morning' => $hour < 12,
                'afternoon' => $hour >= 12 && $hour < 18,
                default => false,
            };
        });
    }

    protected function formatRecommendation(array $candidate, int $score, string $reason): array
    {
        return [
            'movie_id' => $candidate['movie_id'],
            'title' => $candidate['title'],
            'slug' => $candidate['slug'],
            'poster' => $candidate['poster'],
            'duration' => $candidate['duration'],
            'age_rating' => $candidate['age_rating'],
            'country' => $candidate['country'],
            'genres' => $candidate['genres'],
            'showtimes' => $candidate['showtimes'],
            'score' => $score,
            'reason' => $reason,
        ];
    }

    protected function candidatePayload(Collection $candidates): array
    {
        return $candidates->map(function (array $candidate) {
            return [
                'movie_id' => $candidate['movie_id'],
                'title' => $candidate['title'],
                'description' => $candidate['description'],
                'duration' => $candidate['duration'],
                'age_rating' => $candidate['age_rating'],
                'country' => $candidate['country'],
                'genres' => $candidate['genres'],
                'showtimes' => $candidate['showtimes'],
            ];
        })->values()->all();
    }

    protected function systemPrompt(int $limit): string
    {
        return "Bạn là AI gợi ý phim cho MovieMate. Chỉ được chọn movie_id có trong available_movies, tuyệt đối không tự bịa phim ngoài database. Trả về JSON object đúng schema: {\"recommendations\":[{\"movie_id\":number,\"score\":number,\"reason\":\"lý do ngắn bằng tiếng Việt\"}]}. Tối đa {$limit} phim. Ưu tiên phim khớp thể loại, tâm trạng, người đi cùng, thời gian và khu vực/rạp.";
    }

    protected function decodeAiJson(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('AI response is not valid JSON.');
        }

        return $decoded;
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
<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LoyaltyPointTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyPointService
{
    /** Mỗi điểm dùng thanh toán có giá trị 100đ (tỷ lệ hoàn điểm khoảng 10%). */
    public const VALUE_PER_POINT = 100;

    public function calculate(float|int $totalAmount): int
    {
        return max(0, (int) floor((float) $totalAmount / 1000));
    }

    public function awardForBooking(Booking $booking): void
    {
        if ($booking->loyaltyPointTransactions()->where('type', 'earn')->exists()) {
            return;
        }

        $points = $this->calculate((float) $booking->total_amount);

        if ($points <= 0) {
            return;
        }

        if ((int) $booking->loyalty_points_earned !== $points) {
            $booking->forceFill(['loyalty_points_earned' => $points])->save();
        }

        $user = $booking->user()->lockForUpdate()->first();

        if (! $user) {
            return;
        }

        $user->increment('loyalty_points', $points);
        $user->increment('lifetime_loyalty_points', $points);

        $booking->loyaltyPointTransactions()->create([
            'user_id' => $user->id,
            'points' => $points,
            'type' => 'earn',
            'description' => 'Tich diem tu don dat ve '.$booking->booking_code,
        ]);
    }

    public function reverseForCancelledBooking(Booking $booking): void
    {
        $points = (int) $booking->loyalty_points_earned;
        $wasAwarded = $booking->loyaltyPointTransactions()->where('type', 'earn')->exists();

        if ($points <= 0 || ! $wasAwarded || $booking->loyaltyPointTransactions()->where('type', 'reverse')->exists()) {
            return;
        }

        $user = $booking->user()->lockForUpdate()->first();

        if (! $user) {
            return;
        }

        if ((int) $user->loyalty_points < $points) {
            throw ValidationException::withMessages([
                'loyalty_points' => 'Khong the huy ve vi diem da duoc su dung. Vui long lien he nhan vien de ho tro hoan/huy ve.',
            ]);
        }

        $user->decrement('loyalty_points', $points);
        $user->decrement('lifetime_loyalty_points', min($points, (int) $user->lifetime_loyalty_points));

        $booking->loyaltyPointTransactions()->create([
            'user_id' => $booking->user_id,
            'points' => -1 * $points,
            'type' => 'reverse',
            'description' => 'Hoan diem do huy ve '.$booking->booking_code,
        ]);
    }

    public function redeemPoints(User $user, int $points, string $description): LoyaltyPointTransaction
    {
        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => 'So diem doi phai lon hon 0.',
            ]);
        }

        return DB::transaction(function () use ($user, $points, $description) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedUser->loyalty_points < $points) {
                throw ValidationException::withMessages([
                    'points' => 'So diem kha dung khong du de doi qua.',
                ]);
            }

            $lockedUser->decrement('loyalty_points', $points);

            return LoyaltyPointTransaction::create([
                'user_id' => $lockedUser->id,
                'booking_id' => null,
                'points' => -1 * $points,
                'type' => 'redeem',
                'description' => $description,
            ]);
        });
    }

    public function redeemForBooking(User $user, Booking $booking, int $points): LoyaltyPointTransaction
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['loyalty_points' => 'Số điểm sử dụng phải lớn hơn 0.']);
        }

        $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

        if ((int) $lockedUser->loyalty_points < $points) {
            throw ValidationException::withMessages(['loyalty_points' => 'Số điểm khả dụng không đủ.']);
        }

        $lockedUser->decrement('loyalty_points', $points);

        return $booking->loyaltyPointTransactions()->create([
            'user_id' => $lockedUser->id,
            'points' => -$points,
            'type' => 'redeem',
            'description' => 'Dùng điểm giảm giá đơn vé '.$booking->booking_code,
        ]);
    }

    public function restoreRedeemedPoints(Booking $booking): void
    {
        $points = (int) $booking->loyalty_points_redeemed;

        if ($points <= 0 || $booking->loyaltyPointTransactions()->where('type', 'adjustment')->where('points', $points)->exists()) {
            return;
        }

        $user = $booking->user()->lockForUpdate()->first();
        if (! $user) {
            return;
        }

        $user->increment('loyalty_points', $points);
        $booking->loyaltyPointTransactions()->create([
            'user_id' => $user->id,
            'points' => $points,
            'type' => 'adjustment',
            'description' => 'Hoàn điểm do đơn vé '.$booking->booking_code.' bị hủy',
        ]);
    }
}
<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PayosService
{
    public function createPaymentLink(Booking $booking): array
    {
        $this->ensureConfigured();

        $booking->loadMissing(['user', 'showtime.movie', 'bookingSeats.seat']);

        $amount = (int) round((float) $booking->total_amount);
        $orderCode = (int) $booking->id;
        $description = 'MMT'.$booking->id;
        $returnUrl = route('payment.payos.return', $booking);
        $cancelUrl = route('payment.payos.cancel', $booking);

        $payload = [
            'orderCode' => $orderCode,
            'amount' => $amount,
            'description' => $description,
            'buyerName' => $booking->user?->name,
            'buyerEmail' => $booking->user?->email,
            'buyerPhone' => $booking->user?->phone,
            'items' => [
                [
                    'name' => Str::limit('Ve + do an '.($booking->showtime?->movie?->title ?? 'MovieMate'), 80, ''),
                    'quantity' => 1,
                    'price' => $amount,
                ],
            ],
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
        ];

        $payload['signature'] = $this->signature([
            'amount' => $payload['amount'],
            'cancelUrl' => $payload['cancelUrl'],
            'description' => $payload['description'],
            'orderCode' => $payload['orderCode'],
            'returnUrl' => $payload['returnUrl'],
        ]);

        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->post($this->baseUrl().'/v2/payment-requests', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Khong tao duoc link thanh toan payOS: '.$response->body());
        }

        $json = $response->json();

        if (($json['code'] ?? null) !== '00' || empty($json['data']['checkoutUrl'])) {
            throw new RuntimeException('payOS tra ve du lieu khong hop le: '.json_encode($json, JSON_UNESCAPED_UNICODE));
        }

        return $json['data'];
    }

    public function getPaymentInfo(int|string $orderCode): array
    {
        $this->ensureConfigured();

        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->get($this->baseUrl().'/v2/payment-requests/'.$orderCode);

        if (! $response->successful()) {
            throw new RuntimeException('Khong lay duoc trang thai payOS: '.$response->body());
        }

        $json = $response->json();

        if (($json['code'] ?? null) !== '00') {
            throw new RuntimeException('payOS tra ve trang thai khong hop le: '.json_encode($json, JSON_UNESCAPED_UNICODE));
        }

        return $json['data'] ?? [];
    }

    public function verifyWebhook(array $payload): array
    {
        $this->ensureConfigured();

        $data = $payload['data'] ?? null;
        $signature = $payload['signature'] ?? null;

        if (! is_array($data) || ! is_string($signature)) {
            throw new RuntimeException('Webhook payOS thieu data hoac signature.');
        }

        if (! hash_equals($this->signature($data), $signature)) {
            throw new RuntimeException('Chu ky webhook payOS khong hop le.');
        }

        return $data;
    }

    public function isPaidStatus(?string $status): bool
    {
        return in_array(strtoupper((string) $status), ['PAID', 'SUCCESS', 'SUCCEEDED'], true);
    }

    protected function headers(): array
    {
        return [
            'x-client-id' => config('services.payos.client_id'),
            'x-api-key' => config('services.payos.api_key'),
        ];
    }

    protected function signature(array $data): string
    {
        ksort($data);

        $signatureData = collect($data)
            ->map(fn ($value, $key) => $key.'='.$this->normalizeSignatureValue($value))
            ->implode('&');

        return hash_hmac('sha256', $signatureData, (string) config('services.payos.checksum_key'));
    }

    protected function normalizeSignatureValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    protected function ensureConfigured(): void
    {
        if (! config('services.payos.client_id') || ! config('services.payos.api_key') || ! config('services.payos.checksum_key')) {
            throw new RuntimeException('Chua cau hinh PAYOS_CLIENT_ID, PAYOS_API_KEY hoac PAYOS_CHECKSUM_KEY trong .env.');
        }
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.payos.base_url'), '/');
    }
}
<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\SeatHold;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatHoldService
{
    public const HOLD_MINUTES = 7;

    public function expireStale(?int $showtimeId = null): int
    {
        SeatHold::where('expires_at', '<=', now())->delete();
        $ids = Booking::query()
            ->where('booking_status', 'pending')
            ->where('payment_status', 'pending')
            ->where(fn ($query) => $query->whereNull('hold_expires_at')->orWhere('hold_expires_at', '<=', now()))
            ->when($showtimeId, fn ($query) => $query->where('showtime_id', $showtimeId))
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired) {
                $booking = Booking::with(['payment', 'foodOrder'])->lockForUpdate()->find($id);
                if (! $booking || $booking->booking_status !== 'pending' || $booking->payment_status !== 'pending'
                    || ($booking->hold_expires_at && $booking->hold_expires_at->isFuture())) {
                    return;
                }

                $booking->update(['booking_status' => 'expired', 'payment_status' => 'failed']);
                $booking->payment?->update(['status' => 'failed']);
                $booking->foodOrder?->update(['status' => 'cancelled']);
                app(LoyaltyPointService::class)->restoreRedeemedPoints($booking);
                $booking->bookingSeats()->delete();
                $expired++;
            });
        }

        return $expired;
    }

    public function holdSeats(User $user, Showtime $showtime, array $seatIds): \Carbon\CarbonInterface
    {
        return DB::transaction(function () use ($user, $showtime, $seatIds) {
            SeatHold::where('expires_at', '<=', now())->delete();

            $conflict = SeatHold::where('showtime_id', $showtime->id)
                ->whereIn('seat_id', $seatIds)
                ->where('user_id', '!=', $user->id)
                ->where('expires_at', '>', now())
                ->lockForUpdate()->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['selected_seats' => 'Một hoặc nhiều ghế vừa được người khác giữ. Vui lòng chọn lại.']);
            }

            $existingHolds = SeatHold::where('user_id', $user->id)->where('showtime_id', $showtime->id)
                ->whereIn('seat_id', $seatIds)->where('expires_at', '>', now())->lockForUpdate()->get();

            if ($existingHolds->count() === count($seatIds)) {
                $expiresAt = $existingHolds->min('expires_at');
            } else {
                $expiresAt = now()->addMinutes(self::HOLD_MINUTES);
                $startsAt = \Carbon\Carbon::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time, 'Asia/Ho_Chi_Minh');
                $bookingDeadline = $startsAt->addMinutes(30);
                if ($expiresAt->greaterThan($bookingDeadline)) $expiresAt = $bookingDeadline;
            }

            SeatHold::where('user_id', $user->id)->where('showtime_id', $showtime->id)->whereNotIn('seat_id', $seatIds)->delete();
            foreach ($seatIds as $seatId) {
                SeatHold::updateOrCreate(
                    ['showtime_id' => $showtime->id, 'seat_id' => $seatId],
                    ['user_id' => $user->id, 'expires_at' => $expiresAt]
                );
            }

            return $expiresAt;
        });
    }

    public function assertHeldBy(User $user, Showtime $showtime, array $seatIds): \Carbon\CarbonInterface
    {
        $holds = SeatHold::where('user_id', $user->id)->where('showtime_id', $showtime->id)
            ->whereIn('seat_id', $seatIds)->where('expires_at', '>', now())->lockForUpdate()->get();

        if ($holds->count() !== count($seatIds)) {
            throw ValidationException::withMessages(['seat_ids' => 'Thời gian giữ ghế đã hết hoặc ghế không còn được giữ cho bạn. Vui lòng chọn lại.']);
        }

        return $holds->min('expires_at');
    }

    public function release(User $user, Showtime $showtime, array $seatIds): void
    {
        SeatHold::where('user_id', $user->id)->where('showtime_id', $showtime->id)->whereIn('seat_id', $seatIds)->delete();
    }

    public function activeHeldSeatIds(Showtime $showtime, ?int $exceptUserId = null): array
    {
        return SeatHold::where('showtime_id', $showtime->id)->where('expires_at', '>', now())
            ->when($exceptUserId, fn ($query) => $query->where('user_id', '!=', $exceptUserId))
            ->pluck('seat_id')->all();
    }
}
<?php

namespace App\Services;

use App\Models\Cinema;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ShowtimeCalendarService
{
    public function data(Request $request): array
    {
        $today = Carbon::today('Asia/Ho_Chi_Minh');
        $now = now('Asia/Ho_Chi_Minh');
        $endDate = $today->copy()->addDays(6);
        $selectedDate = $this->normalizeSelectedDate(
            $request->query('date'),
            $this->defaultSelectedDate($today, $endDate, $now)
        );
        $cityOptions = $this->cityOptions();
        $brandTabs = ['Tất cả', 'MovieMate', 'CGV', 'Lotte', 'Galaxy', 'BHD', 'Beta', 'Cinestar'];
        $selectedCity = $this->normalizeSelectedCity($request->query('city'), array_keys($cityOptions));
        $selectedBrand = $this->normalizeSelectedBrand($request->query('brand'), $brandTabs);
        $userLat = $this->normalizeCoordinate($request->query('lat'), -90, 90);
        $userLng = $this->normalizeCoordinate($request->query('lng'), -180, 180);
        $isNearby = $request->boolean('nearby') && ! is_null($userLat) && ! is_null($userLng);

        $cinemas = Cinema::query()
            ->where('status', 'active')
            ->when($selectedCity, function ($query) use ($cityOptions, $selectedCity) {
                $aliases = $cityOptions[$selectedCity] ?? [$selectedCity];

                $query->where(function ($cityQuery) use ($aliases) {
                    foreach ($aliases as $alias) {
                        $cityQuery->orWhere('city', 'like', '%'.$alias.'%')
                            ->orWhere('address', 'like', '%'.$alias.'%');
                    }
                });
            })
            ->when($selectedBrand, function ($query) use ($selectedBrand) {
                $query->where('name', 'like', '%'.$selectedBrand.'%');
            })
            ->withCount([
                'showtimes as active_showtimes_count' => function ($query) use ($selectedDate, $today, $now) {
                    $query->where('status', 'active')
                        ->whereDate('show_date', $selectedDate)
                        ->when($selectedDate === $today->toDateString(), function ($query) use ($now) {
                            $query->whereTime('show_time', '>=', $now->copy()->subMinutes(30)->format('H:i:s'));
                        });
                },
            ])
            ->orderBy('name')
            ->get();

        $cinemas = $isNearby
            ? $this->sortByDistance($cinemas, $userLat, $userLng)
            : $cinemas->map(function (Cinema $cinema) {
                $cinema->distance = null;

                return $cinema;
            })->values();

        $requestedCinemaId = $request->integer('cinema_id');
        $selectedCinema = $cinemas->firstWhere('id', $requestedCinemaId) ?? $cinemas->first();

        $scheduleDates = collect(range(0, 6))->map(function (int $offset) use ($today) {
            $date = $today->copy()->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'day' => $date->format('d'),
                'label' => $offset === 0 ? 'Hôm nay' : $this->vietnameseWeekday($date),
            ];
        });

        $scheduleShowtimes = collect();
        $scheduleMovies = collect();
        $showtimeDates = collect();

        if ($selectedCinema) {
            $scheduleShowtimes = Showtime::with(['movie.genres', 'cinema', 'room'])
                ->where('status', 'active')
                ->where('cinema_id', $selectedCinema->id)
                ->whereDate('show_date', $selectedDate)
                ->when($selectedDate === $today->toDateString(), function ($query) use ($now) {
                    $query->whereTime('show_time', '>=', $now->copy()->subMinutes(30)->format('H:i:s'));
                })
                ->whereHas('movie')
                ->orderBy('show_time')
                ->get();

            $showtimeDates = Showtime::query()
                ->where('status', 'active')
                ->where('cinema_id', $selectedCinema->id)
                ->whereBetween('show_date', [$today->toDateString(), $endDate->toDateString()])
                ->where(function ($query) use ($today, $now) {
                    $query->whereDate('show_date', '>', $today->toDateString())
                        ->orWhere(function ($query) use ($today, $now) {
                            $query->whereDate('show_date', $today->toDateString())
                                ->whereTime('show_time', '>=', $now->copy()->subMinutes(30)->format('H:i:s'));
                        });
                })
                ->orderBy('show_date')
                ->pluck('show_date')
                ->map(fn ($showDate) => Carbon::parse($showDate)->toDateString())
                ->unique()
                ->values();

            $scheduleMovies = $scheduleShowtimes
                ->groupBy('movie_id')
                ->map(function ($movieShowtimes) {
                    return [
                        'movie' => $movieShowtimes->first()->movie,
                        'showtimes' => $movieShowtimes->values(),
                    ];
                })
                ->values();
        }

        return compact(
            'cinemas',
            'scheduleDates',
            'selectedCinema',
            'selectedDate',
            'scheduleMovies',
            'showtimeDates',
            'cityOptions',
            'brandTabs',
            'selectedCity',
            'selectedBrand',
            'isNearby',
            'userLat',
            'userLng'
        );
    }

    private function sortByDistance($cinemas, ?float $userLat, ?float $userLng)
    {
        return $cinemas
            ->map(function (Cinema $cinema) use ($userLat, $userLng) {
                $cinema->distance = $this->cinemaHasCoordinates($cinema)
                    ? $this->calculateDistance($userLat, $userLng, (float) $cinema->latitude, (float) $cinema->longitude)
                    : null;

                return $cinema;
            })
            ->sortBy(fn (Cinema $cinema) => is_null($cinema->distance) ? PHP_FLOAT_MAX : $cinema->distance)
            ->values();
    }

    private function normalizeCoordinate(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $coordinate = (float) $value;

        return $coordinate >= $min && $coordinate <= $max ? $coordinate : null;
    }

    private function cinemaHasCoordinates(Cinema $cinema): bool
    {
        return ! is_null($cinema->latitude)
            && ! is_null($cinema->longitude)
            && is_numeric($cinema->latitude)
            && is_numeric($cinema->longitude);
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function cityOptions(): array
    {
        return [
            'Hà Nội' => ['Ha Noi', 'Hanoi', 'Hà Nội', 'Hà Nội'],
            'TP. Hồ Chí Minh' => ['TP. Hồ Chí Minh', 'Hồ Chí Minh', 'TP. Ho Chi Minh', 'Ho Chi Minh', 'Ho Chi Minh City', 'HCMC', 'Sai Gon', 'Sài Gòn'],
            'Đà Nẵng' => ['Đà Nẵng', 'Da Nang', 'Danang'],
        ];
    }

    private function normalizeSelectedCity(mixed $city, array $allowedCities): ?string
    {
        return is_string($city) && in_array($city, $allowedCities, true) ? $city : null;
    }

    private function normalizeSelectedBrand(mixed $brand, array $allowedBrands): ?string
    {
        if (! is_string($brand) || $brand === '' || in_array($brand, ['Tất cả', 'Tat ca'], true)) {
            return null;
        }

        return in_array($brand, $allowedBrands, true) ? $brand : null;
    }

    private function normalizeSelectedDate(mixed $date, Carbon $fallback): string
    {
        if (! is_string($date) || $date === '') {
            return $fallback->toDateString();
        }

        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $date, $fallback->getTimezone());
        } catch (\Throwable) {
            return $fallback->toDateString();
        }

        return $parsedDate && $parsedDate->format('Y-m-d') === $date
            ? $parsedDate->toDateString()
            : $fallback->toDateString();
    }

    private function defaultSelectedDate(Carbon $today, Carbon $endDate, Carbon $now): Carbon
    {
        $firstAvailableDate = Showtime::query()
            ->where('status', 'active')
            ->whereBetween('show_date', [$today->toDateString(), $endDate->toDateString()])
            ->where(function ($query) use ($today, $now) {
                $query->whereDate('show_date', '>', $today->toDateString())
                    ->orWhere(function ($query) use ($today, $now) {
                        $query->whereDate('show_date', $today->toDateString())
                            ->whereTime('show_time', '>=', $now->copy()->subMinutes(30)->format('H:i:s'));
                    });
            })
            ->orderBy('show_date')
            ->value('show_date');

        return $firstAvailableDate
            ? Carbon::parse($firstAvailableDate, $today->getTimezone())
            : $today;
    }

    private function vietnameseWeekday(Carbon $date): string
    {
        return match ((int) $date->dayOfWeekIso) {
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            default => 'Chủ nhật',
        };
    }
}
<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function resolve(?string $code, float|int $subtotal, ?int $userId = null, bool $lockForUpdate = false): array
    {
        $code = trim((string) $code);

        if ($code === '') {
            return [
                'voucher' => null,
                'code' => null,
                'discount' => 0.0,
                'total' => (float) $subtotal,
            ];
        }

        $voucherQuery = Voucher::where('code', strtoupper($code));

        if ($lockForUpdate) {
            $voucherQuery->lockForUpdate();
        }

        $voucher = $voucherQuery->first();

        if (! $voucher) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher không tồn tại.',
            ]);
        }

        if ($voucher->status !== 'active') {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher không còn hoạt động.',
            ]);
        }

        $now = now();
        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher chưa đến thời gian sử dụng.',
            ]);
        }

        if ($voucher->ends_at && $voucher->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher đã hết hạn.',
            ]);
        }

        if (! is_null($voucher->usage_limit) && $voucher->used_count >= $voucher->usage_limit) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Mã voucher đã hết lượt sử dụng.',
            ]);
        }

        if ($userId && ! is_null($voucher->per_user_limit)) {
            $userUsageCount = $voucher->bookings()
                ->where('user_id', $userId)
                ->where(function ($query) {
                    $query->whereIn('booking_status', ['paid', 'used'])
                        ->orWhere(function ($query) {
                            $query->where('booking_status', 'pending')
                                ->where('payment_status', 'pending')
                                ->where('hold_expires_at', '>', now());
                        });
                })
                ->count();

            if ($userUsageCount >= $voucher->per_user_limit) {
                throw ValidationException::withMessages([
                    'voucher_code' => 'Bạn đã sử dụng hết số lượt cho phép của voucher này.',
                ]);
            }
        }

        if ((float) $subtotal < (float) $voucher->min_order_amount) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Đơn hàng chưa đạt giá trị tối thiểu để dùng voucher.',
            ]);
        }

        $discount = $voucher->discount_type === 'percent'
            ? ((float) $subtotal * (float) $voucher->discount_value / 100)
            : (float) $voucher->discount_value;

        if (! is_null($voucher->max_discount_amount)) {
            $discount = min($discount, (float) $voucher->max_discount_amount);
        }

        $discount = min($discount, (float) $subtotal);

        return [
            'voucher' => $voucher,
            'code' => $voucher->code,
            'discount' => round($discount, 2),
            'total' => round(max(0, (float) $subtotal - $discount), 2),
        ];
    }
}
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane",
    |                    "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | This value determines the classes that can be unserialized from cache
    | storage. By default, no PHP classes will be unserialized from your
    | cache to prevent gadget chain attacks if your APP_KEY is leaked.
    |
    */

    'serializable_classes' => false,

];
<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', env('AI_PROVIDER') === 'gemini' ? 'gemini-1.5-flash' : 'gpt-4o-mini'),
    ],

    'payos' => [
        'client_id' => env('PAYOS_CLIENT_ID'),
        'api_key' => env('PAYOS_API_KEY'),
        'checksum_key' => env('PAYOS_CHECKSUM_KEY'),
        'base_url' => env('PAYOS_BASE_URL', 'https://api-merchant.payos.vn'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | This option determines the default session driver that is utilized for
    | incoming requests. Laravel supports a variety of storage options to
    | persist session data. Database storage is a great default choice.
    |
    | Supported: "file", "cookie", "database", "memcached",
    |            "redis", "dynamodb", "array"
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of minutes that you wish the session
    | to be allowed to remain idle before it expires. If you want them
    | to expire immediately when the browser is closed then you may
    | indicate that via the expire_on_close configuration option.
    |
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    |
    | This option allows you to easily specify that all of your session data
    | should be encrypted before it's stored. All encryption is performed
    | automatically by Laravel and you may use the session like normal.
    |
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    |
    | When utilizing the "file" session driver, the session files are placed
    | on disk. The default storage location is defined here; however, you
    | are free to provide another location where they should be stored.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection
    |--------------------------------------------------------------------------
    |
    | When using the "database" or "redis" session drivers, you may specify a
    | connection that should be used to manage these sessions. This should
    | correspond to a connection in your database configuration options.
    |
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    |
    | When using the "database" session driver, you may specify the table to
    | be used to store sessions. Of course, a sensible default is defined
    | for you; however, you're welcome to change this to another table.
    |
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    |
    | When using one of the framework's cache driven session backends, you may
    | define the cache store which should be used to store the session data
    | between requests. This must match one of your defined cache stores.
    |
    | Affects: "dynamodb", "memcached", "redis"
    |
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    |
    | Some session drivers must manually sweep their storage location to get
    | rid of old sessions from storage. Here are the chances that it will
    | happen on a given request. By default, the odds are 2 out of 100.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Here you may change the name of the session cookie that is created by
    | the framework. Typically, you should not need to change this value
    | since doing so does not grant a meaningful security improvement.
    |
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    |
    | The session cookie path determines the path for which the cookie will
    | be regarded as available. Typically, this will be the root path of
    | your application, but you're free to change this when necessary.
    |
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | This value determines the domain and subdomains the session cookie is
    | available to. By default, the cookie will be available to the root
    | domain without subdomains. Typically, this shouldn't be changed.
    |
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | By setting this option to true, session cookies will only be sent back
    | to the server if the browser has a HTTPS connection. This will keep
    | the cookie from being sent to you when it can't be done securely.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will prevent JavaScript from accessing the
    | value of the cookie and the cookie will only be accessible through
    | the HTTP protocol. It's unlikely you should disable this option.
    |
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | This option determines how your cookies behave when cross-site requests
    | take place, and can be used to mitigate CSRF attacks. By default, we
    | will set this value to "lax" to permit secure cross-site requests.
    |
    | See: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#samesitesamesite-value
    |
    | Supported: "lax", "strict", "none", null
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will tie the cookie to the top-level site for
    | a cross-site context. Partitioned cookies are accepted by the browser
    | when flagged "secure" and the Same-Site attribute is set to "none".
    |
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Serialization
    |--------------------------------------------------------------------------
    |
    | This value controls the serialization strategy for session data, which
    | is JSON by default. Setting this to "php" allows the storage of PHP
    | objects in the session but can make an application vulnerable to
    | "gadget chain" serialization attacks if the APP_KEY is leaked.
    |
    | Supported: "json", "php"
    |
    */

    'serialization' => 'json',

];
@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand-start">Giỏ hàng của bạn</p>
            <h1 class="mt-3 text-3xl font-bold app-text">Kiểm tra trước khi thanh toán</h1>
        </div>
        <a href="{{ route('foods.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-app-card px-5 py-3 text-sm font-semibold app-text hover:border-brand-start hover:text-brand-start transition">
            <i class="ph ph-arrow-left"></i>
            Quay lại thực đơn
        </a>
    </div>

    @if(empty($cart))
        <div class="rounded-[2rem] border border-white/10 bg-app-card p-10 text-center">
            <p class="text-xl font-bold app-text">Giỏ hàng đang trống</p>
            <p class="mt-3 text-sm app-muted">Thêm món ăn vào giỏ để tiếp tục thanh toán.</p>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[1.6fr_0.9fr]">
            <div class="space-y-4">
                @php $total = 0; @endphp
                @foreach($cart as $id => $qty)
                    @php $fi = $items[$id]; $line = $fi->price * $qty; $total += $line; @endphp
                    <div class="rounded-[2rem] border border-white/10 bg-app-card p-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold app-text">{{ $fi->name }}</h2>
                                <p class="mt-2 text-sm app-muted">{{ Str::limit($fi->description, 100) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm app-muted">Số lượng: <strong>{{ $qty }}</strong></p>
                                <p class="mt-2 text-lg font-semibold">{{ number_format($line,0,',','.') }}đ</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="rounded-[2rem] border border-white/10 bg-app-card p-6 shadow-sm">
                <div class="mb-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand-start">Tóm tắt đơn</p>
                    <p class="mt-3 text-sm app-muted">Kiểm tra tổng và tiếp tục thanh toán.</p>
                </div>
                <div class="space-y-3 border-y border-white/10 py-4">
                    <div class="flex items-center justify-between text-sm app-muted">
                        <span>Tổng tiền</span>
                        <span>{{ number_format($total,0,',','.') }}đ</span>
                    </div>
                    <div class="flex items-center justify-between text-sm app-muted">
                        <span>Phí dịch vụ</span>
                        <span>0đ</span>
                    </div>
                </div>
                <div class="mt-6 text-right">
                    <p class="text-sm app-muted">Tổng thanh toán</p>
                    <p class="mt-2 text-3xl font-bold app-text">{{ number_format($total,0,',','.') }}đ</p>
                </div>
                <a href="{{ route('foods.checkout') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-2xl bg-brand-start px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-start/20 hover:bg-brand-end transition">Thanh toán</a>
            </aside>
        </div>
    @endif
</div>
@endsection
@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand-start">Thanh toán đồ ăn</p>
            <h1 class="mt-3 text-3xl font-bold app-text">Hoàn tất đơn hàng</h1>
            <p class="mt-2 text-sm app-muted">Nhập thông tin nhận hàng để chúng tôi chuẩn bị sẵn khi bạn tới rạp.</p>
        </div>
        <a href="{{ route('foods.cart') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-app-card px-5 py-3 text-sm font-semibold app-text hover:border-brand-start hover:text-brand-start transition">
            <i class="ph ph-arrow-left"></i>
            Quay lại giỏ hàng
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <section class="rounded-[2rem] border border-white/10 bg-app-card p-6 shadow-sm">
            <h2 class="text-xl font-semibold app-text mb-4">Thông tin khách hàng</h2>
            <form method="POST" action="{{ route('foods.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium app-text mb-2">Họ và tên</label>
                    <input type="text" name="customer_name" required class="admin-input w-full" placeholder="Nguyễn Văn A" />
                </div>
                <div>
                    <label class="block text-sm font-medium app-text mb-2">Số điện thoại</label>
                    <input type="text" name="customer_phone" class="admin-input w-full" placeholder="0987 654 321" />
                </div>
                <div>
                    <label class="block text-sm font-medium app-text mb-2">Email</label>
                    <input type="email" name="customer_email" class="admin-input w-full" placeholder="email@example.com" />
                </div>
                <div>
                    <label class="block text-sm font-medium app-text mb-2">Chọn rạp nhận</label>
                    <select name="pickup_cinema_id" class="admin-input w-full">
                        <option value="">Chọn rạp</option>
                        @foreach(\App\Models\Cinema::orderBy('name')->get() as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-2xl bg-brand-start px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-start/20 hover:bg-brand-end transition">Đặt hàng và thanh toán</button>
            </form>
        </section>

        <aside class="rounded-[2rem] border border-white/10 bg-app-card p-6 shadow-sm">
            <h2 class="text-xl font-semibold app-text mb-4">Tóm tắt đơn hàng</h2>
            <div class="space-y-4">
                @php $total = 0; @endphp
                @foreach($cart as $id => $qty)
                    @php $fi = $items[$id]; $line = $fi->price * $qty; $total += $line; @endphp
                    <div class="rounded-3xl border border-white/10 bg-app-secondary p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold app-text">{{ $fi->name }}</h3>
                                <p class="text-sm app-muted">x{{ $qty }} • {{ number_format($fi->price,0,',','.') }}đ</p>
                            </div>
                            <p class="font-semibold">{{ number_format($line,0,',','.') }}đ</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 border-t border-white/10 pt-5 text-sm app-muted space-y-3">
                <div class="flex justify-between">
                    <span>Tổng hàng</span>
                    <span>{{ number_format($total,0,',','.') }}đ</span>
                </div>
                <div class="flex justify-between">
                    <span>Phí dịch vụ</span>
                    <span>0đ</span>
                </div>
            </div>
            <div class="mt-6 flex items-center justify-between text-sm font-semibold app-text">
                <span>Tổng thanh toán</span>
                <span>{{ number_format($total,0,',','.') }}đ</span>
            </div>
        </aside>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand-start">Thực đơn rạp</p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold app-text">Đặt đồ ăn khi đến rạp</h1>
            <p class="mt-3 max-w-2xl text-sm app-muted">Chọn món, thêm vào giỏ và nhận đồ ngay khi đến rạp. Thanh toán nhanh gọn bằng chức năng giống mua vé.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <a href="{{ route('foods.cart') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-start px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-start/20 hover:shadow-brand-start/30 transition">
                <i class="ph-fill ph-shopping-bag"></i>
                Giỏ hàng ({{ array_sum(session('food_cart', [])) ?: 0 }})
            </a>
            <a href="{{ route('user.showtimes.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/10 bg-app-card px-5 py-3 text-sm font-semibold app-text hover:border-brand-start hover:text-brand-start transition">
                <i class="ph ph-film-strip"></i>
                Xem lịch chiếu
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-3xl border border-success/20 bg-success/10 p-4 text-sm text-success mb-6">{{ session('success') }}</div>
    @endif

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse($foods as $food)
            <article class="rounded-[2rem] border border-white/10 bg-app-card p-5 shadow-lg shadow-black/5 transition hover:-translate-y-1">
                @if($food->image)
                    <div class="overflow-hidden rounded-[1.75rem] bg-slate-900 mb-4 h-56">
                        <img src="{{ asset('storage/' . $food->image) }}" alt="{{ $food->name }}" class="h-full w-full object-cover transition-transform duration-500 hover:scale-105" loading="lazy">
                    </div>
                @endif
                <div class="flex items-center justify-between gap-4 mb-3">
                    <h2 class="text-xl font-bold app-text">{{ $food->name }}</h2>
                    <span class="rounded-full bg-brand-start/10 px-3 py-1 text-sm font-semibold text-brand-start">{{ number_format($food->price,0,',','.') }}đ</span>
                </div>
                <p class="text-sm app-muted mb-5 min-h-[72px]">{{ $food->description ?: 'Chưa có mô tả.' }}</p>

                <form action="{{ route('foods.add') }}" method="POST" class="grid gap-3">
                    @csrf
                    <input type="hidden" name="food_id" value="{{ $food->id }}">
                    <div class="flex items-center gap-3">
                        <label class="text-sm font-medium app-text">Số lượng</label>
                        <input type="number" name="quantity" value="1" min="1" class="w-20 rounded-2xl border app-border bg-transparent px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-start" />
                    </div>
                    <button type="submit" class="rounded-2xl bg-brand-start px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-start/20 hover:bg-brand-end transition">Thêm vào giỏ</button>
                </form>
            </article>
        @empty
            <div class="col-span-full rounded-[2rem] border border-white/10 bg-app-card p-10 text-center">
                <h2 class="text-2xl font-bold app-text">Hiện chưa có món ăn</h2>
                <p class="mt-3 text-sm app-muted">Vui lòng quay lại sau hoặc liên hệ quản trị viên để cập nhật thực đơn.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-10 text-center">
        {{ $foods->links() }}
    </div>
</div>
@endsection
