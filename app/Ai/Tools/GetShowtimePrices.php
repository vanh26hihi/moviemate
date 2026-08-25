<?php

namespace App\Ai\Tools;

use App\Services\CustomerShowtimePriceReadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class GetShowtimePrices extends ReadOnlyTool implements Tool
{
    public function __construct(private readonly CustomerShowtimePriceReadService $prices) {}

    public function name(): string
    {
        return 'get_showtime_prices';
    }

    public function description(): string
    {
        return 'Get immutable logical seat-type price snapshots for one currently customer-sellable showtime. This never calculates a booking total.';
    }

    public function handle(Request $request): string
    {
        $input = $this->validate($request, [
            'showtime_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->json(['showtime_prices' => $this->prices->get((int) $input['showtime_id'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'showtime_id' => $schema->integer()->min(1)->description('Authoritative MovieMate showtime ID.')->required(),
        ];
    }
}
