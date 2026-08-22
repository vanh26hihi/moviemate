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
