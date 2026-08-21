<?php

namespace App\Services;

use App\Ai\AiConversationContext;
use App\Ai\AiStructuredResponseAssembler;
use App\Ai\AiStructuredResultCollector;
use App\Ai\Contracts\AiTextStreamer;
use App\Ai\MovieMateToolCallGuard;
use UnexpectedValueException;

final class AiChatStreamService
{
    public function __construct(
        private readonly AiTextStreamer $streamer,
        private readonly AiChatbotService $fallback,
        private readonly AiStructuredResultCollector $structuredResults,
        private readonly AiStructuredResponseAssembler $structuredResponses,
        private readonly MovieMateToolCallGuard $toolGuard,
    ) {}

    /**
     * @return \Generator<int, string, mixed, array<string, mixed>>
     */
    public function stream(string $message, AiConversationContext $context, string $audience): \Generator
    {
        $message = trim($message);
        $this->structuredResults->reset();
        $this->toolGuard->reset();

        if (! $this->streamer->enabledAndConfigured()) {
            $result = $this->fallback->answer($message, $context, $audience);
            foreach ($this->chunks((string) $result['answer']) as $chunk) {
                yield $chunk;
            }

            return $result;
        }

        $answer = '';
        $max = max(500, (int) config('moviemate-ai.max_response_characters', 6000));
        foreach ($this->streamer->deltas($message, $context) as $delta) {
            if (! is_string($delta) || $delta === '') {
                continue;
            }
            $answer .= $delta;
            if (mb_strlen($answer) > $max) {
                throw new UnexpectedValueException('Malformed AI stream response.');
            }
            yield $delta;
        }

        $answer = trim($answer);
        if ($answer === '') {
            throw new UnexpectedValueException('Malformed AI stream response.');
        }

        return [
            'answer' => $answer,
            'source' => $this->streamer->source(),
            'message' => null,
            'assistant_completed' => true,
            'failure_category' => null,
            'structured_response' => $this->structuredResponses
                ->assemble($answer, $this->structuredResults)->toArray(),
        ];
    }

    /** @return list<string> */
    private function chunks(string $text): array
    {
        $chunks = [];
        for ($offset = 0, $length = mb_strlen($text); $offset < $length; $offset += 80) {
            $chunks[] = mb_substr($text, $offset, 80);
        }

        return $chunks;
    }
}
