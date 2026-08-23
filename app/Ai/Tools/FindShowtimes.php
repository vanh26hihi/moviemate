<?php

namespace App\Ai\Tools;

use App\Services\CustomerShowtimeReadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class FindShowtimes extends ReadOnlyTool implements Tool
{
    public function __construct(private readonly CustomerShowtimeReadService $showtimes) {}

    public function name(): string
    {
        return 'find_showtimes';
    }

    public function description(): string
    {
        return 'Find authoritative customer-bookable MovieMate showtimes inside the 14-day window. MovieMate calculates bookability and booking URLs.';
    }

    public function handle(Request $request): string
    {
        $arguments = $request->all();
        if (is_int($arguments['limit'] ?? null)) {
            $arguments['limit'] = min($arguments['limit'], CustomerShowtimeReadService::MAX_RESULTS);
        }

        $input = $this->validate(new Request($arguments), [
            'movie_id' => ['sometimes', 'nullable', 'prohibits:movie_slug', 'integer', 'min:1'],
            'movie_slug' => ['sometimes', 'nullable', 'prohibits:movie_id', 'string', 'max:191'],
            'cinema_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'prohibits:from,to'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'prohibits:date'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'prohibits:date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.CustomerShowtimeReadService::MAX_RESULTS],
        ]);

        return $this->json([
            'showtimes' => $this->showtimes->find($input, (int) ($input['limit'] ?? CustomerShowtimeReadService::MAX_RESULTS))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'movie_id' => $schema->integer()->min(1)->description('Unique MovieMate movie ID.'),
            'movie_slug' => $schema->string()->max(191)->description('Unique MovieMate movie slug.'),
            'cinema_code' => $schema->string()->max(50)->description('Public MovieMate cinema code.'),
            'date' => $schema->string()->description('One YYYY-MM-DD date.'),
            'from' => $schema->string()->description('Start YYYY-MM-DD date.'),
            'to' => $schema->string()->description('End YYYY-MM-DD date.'),
            'limit' => $schema->integer()->min(1)->max(CustomerShowtimeReadService::MAX_RESULTS)->description('Maximum results.'),
        ];
    }
}
