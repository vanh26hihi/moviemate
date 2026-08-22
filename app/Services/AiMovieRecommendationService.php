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
            ->with(['movie.genres', 'cinema', 'room', 'ticketPrices'])
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
                            'price' => (int) $showtime->ticketPrices->min('final_unit_amount_vnd'),
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
