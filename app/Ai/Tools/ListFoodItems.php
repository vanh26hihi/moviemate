<?php

namespace App\Ai\Tools;

use App\Services\PublicFoodReadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListFoodItems extends ReadOnlyTool implements Tool
{
    public function __construct(private readonly PublicFoodReadService $foods) {}

    public function name(): string
    {
        return 'list_food_items';
    }

    public function description(): string
    {
        return 'List the existing active public food catalog. Branch-specific requests return explicit unconfirmed availability instead of invented inventory.';
    }

    public function handle(Request $request): string
    {
        $input = $this->validate($request, [
            'query' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cinema_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.PublicFoodReadService::MAX_RESULTS],
        ]);

        return $this->json($this->foods->list(
            $input['query'] ?? null,
            $input['cinema_code'] ?? null,
            (int) ($input['limit'] ?? PublicFoodReadService::MAX_RESULTS),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->max(100)->description('Public food item name.'),
            'cinema_code' => $schema->string()->max(50)->description('Optional branch question; MovieMate may return unconfirmed availability.'),
            'limit' => $schema->integer()->min(1)->max(PublicFoodReadService::MAX_RESULTS)->description('Maximum results.'),
        ];
    }
}
