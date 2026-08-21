<?php

namespace App\Services;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Ai\AiStructuredResponseAssembler;
use App\Ai\AiStructuredResultCollector;
use App\Ai\MovieMateAiRuntime;
use App\Ai\MovieMateToolCallGuard;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class AiMovieRecommendationService
{
    public function __construct(
        private readonly RecommendationReadService $candidates,
        private readonly MovieMateCinemaAssistant $assistant,
        private readonly MovieMateAiRuntime $runtime,
        private readonly MovieMateToolCallGuard $toolGuard,
        private readonly AiStructuredResultCollector $structuredResults,
        private readonly AiStructuredResponseAssembler $structuredResponses,
    ) {}

    public function recommend(array $preferences, int $limit = 5): array
    {
        $limit = max(1, min(5, $limit));
        $this->structuredResults->reset();
        $candidates = $this->candidates->candidates($preferences);

        if ($candidates->isEmpty()) {
            $message = 'Hiện chưa có phim đang chiếu với suất chiếu còn hiệu lực.';

            return [
                'source' => 'empty',
                'recommendations' => [],
                'available_count' => 0,
                'message' => $message,
                'structured_response' => $this->structuredResponses
                    ->assembleRecommendations($message, [])->toArray(),
            ];
        }

        $recommendations = collect();
        $source = 'fallback';
        $aiFailed = false;

        if ($this->runtime->enabledAndConfigured()) {
            $this->toolGuard->reset();
            try {
                $recommendations = $this->requestAiRecommendations($preferences, $candidates, $limit);
                $source = $recommendations->isNotEmpty() ? $this->runtime->provider() : 'fallback';
            } catch (\Throwable $exception) {
                $aiFailed = true;
                Log::warning('AI movie recommendation failed, using fallback.', ['exception' => $exception::class]);
            }
        }

        if ($recommendations->count() < min($limit, $candidates->count())) {
            $recommendations = $recommendations
                ->merge($this->fallbackRecommendations($preferences, $candidates, $limit))
                ->unique('movie_id')->take($limit)->values();
        }

        $message = match (true) {
            $source !== 'fallback' => null,
            ! $this->runtime->enabledAndConfigured() => 'AI chưa được bật hoặc chưa có credential. Kết quả được xếp hạng nội bộ từ dữ liệu MovieMate.',
            $aiFailed => 'Dịch vụ AI tạm thời không phản hồi. MovieMate đã chuyển sang bộ gợi ý nội bộ.',
            default => 'AI không trả về kết quả hợp lệ. MovieMate đã chuyển sang bộ gợi ý nội bộ.',
        };
        $text = $message ?? 'MovieMate đã xếp hạng các phim phù hợp từ dữ liệu suất chiếu hiện tại.';

        return [
            'source' => $source,
            'recommendations' => $recommendations->all(),
            'available_count' => $candidates->count(),
            'message' => $message,
            'structured_response' => $this->structuredResponses
                ->assembleRecommendations($text, $recommendations->all())->toArray(),
        ];
    }

    private function requestAiRecommendations(array $preferences, Collection $candidates, int $limit): Collection
    {
        $content = $this->runtime->prompt($this->assistant, implode("\n", [
            'Nhiệm vụ nội bộ: xếp hạng các ứng viên MovieMate đã được backend xác thực.',
            'Chỉ trả JSON object theo schema {"recommendations":[{"movie_id":number,"score":number,"reason":"lý do ngắn bằng tiếng Việt"}]}.',
            "Tối đa {$limit} phim. Không gọi công cụ để tạo thêm ứng viên và không chọn ID ngoài available_movies.",
            json_encode([
                'preferences' => $preferences,
                'available_movies' => $this->candidatePayload($candidates),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]));

        $decoded = $this->decodeAiJson($content);
        $candidateMap = $candidates->keyBy('movie_id');

        return collect($decoded['recommendations'] ?? [])->map(function ($item) use ($candidateMap) {
            $movieId = (int) ($item['movie_id'] ?? 0);
            if (! $candidateMap->has($movieId)) {
                return null;
            }

            return $this->formatRecommendation(
                $candidateMap->get($movieId),
                max(1, min(100, (int) ($item['score'] ?? 85))),
                Str::limit(trim((string) ($item['reason'] ?? 'Phim phù hợp và có suất chiếu được MovieMate xác nhận.')), 300, ''),
            );
        })->filter()->unique('movie_id')->take($limit)->values();
    }

    private function fallbackRecommendations(array $preferences, Collection $candidates, int $limit): Collection
    {
        return $candidates->map(function (array $candidate) use ($preferences): array {
            $score = 50;
            $reasons = [];
            $preferredGenres = collect($preferences['genres'] ?? [])->map(fn ($genre) => Str::lower((string) $genre))->filter();
            $movieGenres = collect($candidate['genres'])->map(fn ($genre) => Str::lower((string) $genre));
            $matchedGenres = $movieGenres->filter(fn ($genre) => $preferredGenres->contains(
                fn ($preferred) => Str::contains($genre, $preferred) || Str::contains($preferred, $genre),
            ));
            if ($matchedGenres->isNotEmpty()) {
                $score += min(30, $matchedGenres->count() * 15);
                $reasons[] = 'trùng thể loại bạn yêu thích';
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
            if ($movieGenres->contains(fn ($genre) => collect($moodGenres)->contains(fn ($expected) => Str::contains($genre, $expected)))) {
                $score += 18;
                $reasons[] = 'hợp với tâm trạng hiện tại';
            }
            $companionGenres = $this->companionGenres((string) ($preferences['companion'] ?? ''));
            if ($movieGenres->contains(fn ($genre) => collect($companionGenres)->contains(fn ($expected) => Str::contains($genre, $expected)))) {
                $score += 8;
                $reasons[] = 'phù hợp với người đi cùng';
            }

            return $this->formatRecommendation(
                $candidate,
                min(98, $score),
                'Gợi ý từ dữ liệu MovieMate vì phim '.implode(', ', array_slice($reasons ?: ['có suất chiếu được xác nhận'], 0, 3)).'.',
            );
        })->sortByDesc('score')->take($limit)->values();
    }

    private function matchesPreferredTime(array $candidate, string $preferredTime): bool
    {
        return collect($candidate['showtimes'])->contains(function (array $showtime) use ($preferredTime): bool {
            $hour = (int) substr($showtime['time'], 0, 2);
            $date = Carbon::parse($showtime['date'], config('cinema.timezone', 'Asia/Ho_Chi_Minh'));

            return match (Str::lower($preferredTime)) {
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

    private function moodGenres(string $mood): array
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

    private function companionGenres(string $companion): array
    {
        return match (Str::lower($companion)) {
            'couple' => ['romance', 'comedy', 'drama', 'tình cảm', 'hài'],
            'friends' => ['action', 'comedy', 'horror', 'adventure', 'hành động', 'hài', 'kinh dị'],
            'family' => ['animation', 'family', 'comedy', 'hoạt hình', 'gia đình', 'hài'],
            default => [],
        };
    }

    private function formatRecommendation(array $candidate, int $score, string $reason): array
    {
        return [
            'movie_id' => $candidate['movie_id'], 'title' => $candidate['title'], 'slug' => $candidate['slug'],
            'status' => $candidate['status'], 'poster' => $candidate['poster'], 'duration' => $candidate['duration'],
            'age_rating' => $candidate['age_rating'], 'country' => $candidate['country'], 'genres' => $candidate['genres'],
            'showtimes' => $candidate['showtimes'], 'bookable' => $candidate['bookable'] === true,
            'booking_url' => $candidate['bookable'] === true ? $candidate['booking_url'] : null,
            'details_url' => $candidate['details_url'], 'showtimes_url' => $candidate['showtimes_url'],
            'score' => $score, 'reason' => $reason,
        ];
    }

    private function candidatePayload(Collection $candidates): array
    {
        return $candidates->map(fn (array $candidate): array => [
            'movie_id' => $candidate['movie_id'], 'title' => $candidate['title'],
            'description' => $candidate['description'], 'duration' => $candidate['duration'],
            'age_rating' => $candidate['age_rating'], 'country' => $candidate['country'],
            'genres' => $candidate['genres'], 'showtimes' => $candidate['showtimes'],
        ])->values()->all();
    }

    private function decodeAiJson(string $content): array
    {
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($content)) ?? trim($content);
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('AI response is not valid JSON.');
        }

        return $decoded;
    }
}
