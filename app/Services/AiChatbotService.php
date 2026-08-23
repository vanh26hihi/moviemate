<?php

namespace App\Services;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Ai\AiAssistantResponse;
use App\Ai\AiConversationContext;
use App\Ai\AiStructuredResponseAssembler;
use App\Ai\AiStructuredResultCollector;
use App\Ai\MovieMateAiRuntime;
use App\Ai\MovieMateToolCallGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use OverflowException;
use Throwable;

final class AiChatbotService
{
    public function __construct(
        private readonly MovieMateCinemaAssistant $assistant,
        private readonly MovieMateAiRuntime $runtime,
        private readonly CustomerMovieReadService $movies,
        private readonly CustomerShowtimeReadService $showtimes,
        private readonly PublicCinemaReadService $cinemas,
        private readonly PublicFoodReadService $foods,
        private readonly MovieMateToolCallGuard $toolGuard,
        private readonly AiStructuredResultCollector $structuredResults,
        private readonly AiStructuredResponseAssembler $structuredResponses,
    ) {}

    public function answer(string $message, ?AiConversationContext $context = null, string $audience = 'guest'): array
    {
        $message = trim($message);
        $context ??= AiConversationContext::empty();
        $this->structuredResults->reset();

        if ($this->runtime->enabledAndConfigured()) {
            $startedAt = hrtime(true);
            $this->toolGuard->reset();
            try {
                $answer = $this->runtime->prompt($this->assistant, $message, $context);
                if (mb_strlen($answer) > max(500, (int) config('moviemate-ai.max_response_characters', 6000))) {
                    throw new \UnexpectedValueException('Malformed AI response.');
                }

                $structuredResponse = $this->structuredResponses
                    ->assemble($answer, $this->structuredResults)->toArray();
                if ($answer === '' && $structuredResponse['cards'] === []) {
                    throw new \UnexpectedValueException('Malformed AI response.');
                }
                $result = [
                    'answer' => $structuredResponse['text'],
                    'source' => $this->runtime->provider(),
                    'message' => null,
                    'assistant_completed' => true,
                    'failure_category' => null,
                    'structured_response' => $structuredResponse,
                ];
                $this->logAttempt('info', 'AI chatbot completed.', $audience, $context, $startedAt, null);

                return $result;
            } catch (Throwable $exception) {
                $category = $this->failureCategory($exception);
                $this->logAttempt('warning', 'AI chatbot request failed safely.', $audience, $context, $startedAt, $category);

                $answer = 'MovieMate AI tạm thời không thể trả lời. Vui lòng thử lại sau.';

                return [
                    'answer' => $answer,
                    'source' => 'unavailable',
                    'message' => 'Dịch vụ trợ lý đang tạm thời không khả dụng.',
                    'assistant_completed' => false,
                    'failure_category' => $category,
                    'structured_response' => (new AiAssistantResponse($answer))->toArray(),
                ];
            }
        }

        $answer = $this->fallbackAnswer($message);
        $structuredResponse = $this->structuredResponses
            ->assemble($answer, $this->structuredResults)->toArray();

        return [
            'answer' => $structuredResponse['text'],
            'source' => 'fallback',
            'message' => 'MovieMate đang dùng chế độ hỗ trợ dự phòng từ dữ liệu hệ thống.',
            'assistant_completed' => true,
            'failure_category' => null,
            'structured_response' => $structuredResponse,
        ];
    }

    private function failureCategory(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof RateLimitedException => 'rate_limited',
            $exception instanceof InsufficientCreditsException => 'quota',
            $exception instanceof ProviderOverloadedException => 'provider_unavailable',
            $exception instanceof ConnectionException,
            Str::contains(Str::lower($exception::class.' '.$exception->getMessage()), 'timeout') => 'timeout',
            $exception instanceof ValidationException => 'tool_failure',
            $exception instanceof OverflowException => 'step_limit',
            $exception instanceof \UnexpectedValueException => 'malformed',
            default => 'provider_unavailable',
        };
    }

    private function logAttempt(
        string $level,
        string $event,
        string $audience,
        AiConversationContext $context,
        int $startedAt,
        ?string $failureCategory,
    ): void {
        Log::$level($event, [
            'provider' => $this->runtime->provider(),
            'model' => $this->runtime->model(),
            'audience' => $audience === 'authenticated' ? 'authenticated' : 'guest',
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'context_messages' => count($context->messages),
            'context_characters' => $context->characterCount,
            'tool_calls' => $this->toolGuard->count(),
            'failure_category' => $failureCategory,
        ]);
    }

    private function fallbackAnswer(string $message): string
    {
        $normalized = Str::lower($message);

        if ($this->containsAny($normalized, ['khuyến mãi', 'khuyen mai', 'promotion', 'mã giảm', 'ma giam'])) {
            return 'MovieMate chưa công bố danh mục khuyến mãi để trợ lý có thể liệt kê an toàn. Điều kiện áp dụng và tối đa một khuyến mãi sẽ được xác định trong luồng đặt vé bình thường.';
        }
        if ($this->containsAny($normalized, ['bắp', 'bap', 'nước', 'nuoc', 'đồ ăn', 'do an', 'food', 'combo'])) {
            $catalog = $this->foods->list(limit: 6);
            $this->structuredResults->record('list_food_items', $catalog);
            if ($catalog['items'] === []) {
                return 'Hiện MovieMate chưa có món ăn công khai đang hoạt động.';
            }

            return 'Mình tìm thấy một số lựa chọn bắp nước trong danh mục MovieMate.';
        }
        if ($this->containsAny($normalized, ['đặt vé', 'dat ve', 'booking', 'chọn ghế', 'chon ghe', 'thanh toán', 'thanh toan'])) {
            return "Cách đặt vé trên MovieMate:\n1. Chọn phim và một suất chiếu có nút đặt vé do MovieMate xác nhận.\n2. Chọn ghế và kiểm tra thông tin.\n3. Hoàn tất trong luồng thanh toán MovieMate.\n4. Xem kết quả trong Đơn đặt vé của tôi.";
        }
        if ($this->containsAny($normalized, ['rạp', 'rap', 'cinema', 'địa chỉ', 'dia chi', 'ở đâu', 'o dau'])) {
            $cinemas = $this->cinemas->list(limit: 6);
            $this->structuredResults->record('list_cinemas', ['cinemas' => $cinemas->all()]);
            if ($cinemas->isEmpty()) {
                return 'Hiện MovieMate chưa có dữ liệu rạp đang hoạt động.';
            }

            return 'Mình tìm thấy '.$cinemas->count().' rạp MovieMate đang hoạt động.';
        }
        if ($this->containsAny($normalized, ['lịch', 'lich', 'suất', 'suat', 'giờ', 'gio', 'hôm nay', 'hom nay', 'tối nay', 'toi nay'])) {
            $showtimes = $this->showtimes->find(limit: 8);
            $this->structuredResults->record('find_showtimes', ['showtimes' => $showtimes->all()]);
            if ($showtimes->isEmpty()) {
                return 'Hiện chưa có suất chiếu được MovieMate xác nhận là còn nhận đặt vé.';
            }

            return 'Mình tìm thấy '.$showtimes->count().' suất chiếu còn nhận đặt vé.';
        }
        if ($this->containsAny($normalized, ['giá', 'gia', 'bao nhiêu', 'bao nhieu', 'vé', 've'])) {
            $showtimes = $this->showtimes->find(limit: 6);
            $this->structuredResults->record('find_showtimes', ['showtimes' => $showtimes->all()]);
            if ($showtimes->isEmpty()) {
                return 'Hiện chưa có suất chiếu còn nhận đặt vé để kiểm tra giá snapshot.';
            }

            return 'Mình tìm thấy giá khởi điểm của các suất chiếu phù hợp.';
        }
        if ($this->containsAny($normalized, ['phim', 'hay', 'đang chiếu', 'dang chieu', 'thể loại', 'the loai', 'hành động', 'hanh dong', 'gia đình', 'gia dinh'])) {
            $movies = $this->movies->search(limit: 5);
            $this->structuredResults->record('search_movies', ['movies' => $movies->all()]);
            if ($movies->isEmpty()) {
                return 'Hiện MovieMate chưa có phim công khai phù hợp trong hệ thống.';
            }

            return 'Mình tìm thấy '.$movies->count().' phim công khai trên MovieMate.';
        }

        return 'Tôi có thể hỗ trợ về phim, suất chiếu còn nhận đặt vé, rạp, giá snapshot, món ăn công khai và cách đặt vé trên MovieMate.';
    }

    private function containsAny(string $message, array $needles): bool
    {
        return collect($needles)->contains(fn ($needle): bool => Str::contains($message, $needle));
    }
}
