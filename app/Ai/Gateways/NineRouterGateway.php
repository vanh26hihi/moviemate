<?php

namespace App\Ai\Gateways;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\OpenRouter\OpenRouterGateway;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\TextResponse;

final class NineRouterGateway extends OpenRouterGateway
{
    /**
     * 9Router returns Chat Completions JSON from /responses only when the
     * non-stream transport mode is explicit.
     */
    public function generateText(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages = [],
        array $tools = [],
        ?array $schema = null,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): TextResponse {
        $body = $this->responsesBody(
            $provider,
            $model,
            $instructions,
            $messages,
            $tools,
            $schema,
            $options,
        );

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)
                ->acceptJson()
                ->post('responses', $body),
        );

        $data = $response->json();
        $data['model'] = $model;

        $this->validateTextResponse($data);

        return $this->parseTextResponse(
            $data,
            $provider,
            filled($schema),
            $tools,
            $schema,
            $options,
            $instructions,
            $messages,
            $timeout,
        );
    }

    /**
     * 9Router does not retain Responses API state for previous_response_id.
     * Replay the bounded current prompt plus SDK-produced tool turns instead.
     */
    protected function continueWithToolResults(
        string $model,
        Provider $provider,
        bool $structured,
        array $tools,
        ?array $schema,
        Collection $steps,
        Collection $messages,
        ?string $instructions,
        array $originalMessages,
        int $depth,
        ?int $maxSteps,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): TextResponse {
        $body = $this->responsesBody(
            $provider,
            $model,
            $instructions,
            [...$originalMessages, ...$messages->all()],
            $tools,
            $schema,
            $options,
        );

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)
                ->acceptJson()
                ->post('responses', $body),
        );

        $data = $response->json();
        $data['model'] = $model;

        $this->validateTextResponse($data);

        return $this->processResponse(
            $data,
            $provider,
            $structured,
            $tools,
            $schema,
            $steps,
            $messages,
            $instructions,
            $originalMessages,
            $depth,
            $maxSteps,
            $options,
            $timeout,
        );
    }

    /** @param list<Message|array<string, mixed>|object> $messages */
    private function responsesBody(
        Provider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
    ): array {
        $chatBody = $this->buildTextRequestBody(
            $provider,
            $model,
            $instructions,
            $messages,
            $tools,
            $schema,
            $options,
        );

        $body = Arr::except($chatBody, ['messages', 'tools', 'max_tokens', 'response_format']);
        $body['input'] = $this->responsesInput($messages, $instructions);
        $body['stream'] = false;

        if (isset($chatBody['max_tokens'])) {
            $body['max_output_tokens'] = $chatBody['max_tokens'];
        }

        if (isset($chatBody['tools'])) {
            $body['tools'] = array_map(fn (array $tool): array => [
                'type' => 'function',
                ...$tool['function'],
            ], $chatBody['tools']);
        }

        $jsonSchema = data_get($chatBody, 'response_format.json_schema');
        if (is_array($jsonSchema)) {
            $body['text'] = ['format' => [
                'type' => 'json_schema',
                'name' => $jsonSchema['name'] ?? 'schema_definition',
                'schema' => $jsonSchema['schema'] ?? [],
                'strict' => $jsonSchema['strict'] ?? true,
            ]];
        }

        return $body;
    }

    /**
     * @param  list<Message|array<string, mixed>|object>  $messages
     * @return list<array<string, mixed>>
     */
    private function responsesInput(array $messages, ?string $instructions): array
    {
        $input = [];

        if (filled($instructions)) {
            $input[] = ['role' => 'system', 'content' => $instructions];
        }

        foreach ($messages as $message) {
            $message = Message::tryFrom($message);

            if ($message->role === MessageRole::User) {
                $input[] = [
                    'role' => 'user',
                    'content' => [['type' => 'input_text', 'text' => $message->content]],
                ];

                continue;
            }

            if ($message->role === MessageRole::Assistant) {
                if ($message instanceof AssistantMessage) {
                    foreach ($message->toolCalls as $toolCall) {
                        $input[] = $this->responsesToolCall($toolCall);
                    }
                }

                if (filled($message->content)) {
                    $input[] = [
                        'role' => 'assistant',
                        'content' => [['type' => 'output_text', 'text' => $message->content]],
                    ];
                }

                continue;
            }

            if ($message instanceof ToolResultMessage) {
                foreach ($message->toolResults as $toolResult) {
                    $input[] = [
                        'type' => 'function_call_output',
                        'call_id' => $toolResult->resultId ?? $toolResult->id,
                        'output' => $this->serializeToolResultOutput($toolResult->result),
                    ];
                }
            }
        }

        return $input;
    }

    /** @return array<string, mixed> */
    private function responsesToolCall(ToolCall $toolCall): array
    {
        return [
            'id' => $toolCall->id,
            'call_id' => $toolCall->resultId ?? $toolCall->id,
            'type' => 'function_call',
            'name' => $toolCall->name,
            'arguments' => json_encode($toolCall->arguments ?: (object) []),
        ];
    }
}
