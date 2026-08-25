<?php

namespace App\Ai\Tools;

use App\Services\PublicCinemaReadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListCinemas extends ReadOnlyTool implements Tool
{
    public function __construct(private readonly PublicCinemaReadService $cinemas) {}

    public function name(): string
    {
        return 'list_cinemas';
    }

    public function description(): string
    {
        return 'List bounded, active MovieMate cinema branches with customer-public information.';
    }

    public function handle(Request $request): string
    {
        $input = $this->validate($request, [
            'query' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.PublicCinemaReadService::MAX_RESULTS],
        ]);

        return $this->json([
            'cinemas' => $this->cinemas->list($input, (int) ($input['limit'] ?? PublicCinemaReadService::MAX_RESULTS))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->max(100)->description('Cinema name, address, city, or district.'),
            'city' => $schema->string()->max(120)->description('Exact public city.'),
            'district' => $schema->string()->max(120)->description('Exact public district.'),
            'limit' => $schema->integer()->min(1)->max(PublicCinemaReadService::MAX_RESULTS)->description('Maximum results.'),
        ];
    }
}
