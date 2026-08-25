<?php

namespace App\Ai\Tools;

use App\Services\RecommendationReadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class RecommendMovies extends ReadOnlyTool implements Tool
{
    public function __construct(private readonly RecommendationReadService $recommendations) {}

    public function name(): string
    {
        return 'recommend_movies';
    }

    public function description(): string
    {
        return 'Return grounded recommendation candidates made only from real MovieMate movies with authoritative bookable showtimes.';
    }

    public function handle(Request $request): string
    {
        $input = $this->validate($request, [
            'genres' => ['sometimes', 'array', 'max:5'],
            'genres.*' => ['string', 'max:100'],
            'mood' => ['sometimes', 'nullable', 'string', 'in:happy,sad,stress,chill,excited,romantic'],
            'companion' => ['sometimes', 'nullable', 'string', 'in:alone,couple,friends,family'],
            'preferred_time' => ['sometimes', 'nullable', 'string', 'in:tonight,tomorrow,weekend,after_21,morning,afternoon'],
            'location' => ['sometimes', 'nullable', 'string', 'max:191'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);

        return $this->json([
            'candidates' => $this->recommendations->candidates($input, (int) ($input['limit'] ?? 8))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'genres' => $schema->array()->items($schema->string()->max(100))->max(5)->description('Preferred public genre names.'),
            'mood' => $schema->string()->enum(['happy', 'sad', 'stress', 'chill', 'excited', 'romantic'])->description('Customer mood.'),
            'companion' => $schema->string()->enum(['alone', 'couple', 'friends', 'family'])->description('Who the customer is watching with.'),
            'preferred_time' => $schema->string()->enum(['tonight', 'tomorrow', 'weekend', 'after_21', 'morning', 'afternoon'])->description('Preferred viewing time.'),
            'location' => $schema->string()->max(191)->description('Public cinema, city, district, or address text.'),
            'limit' => $schema->integer()->min(1)->max(12)->description('Maximum candidates.'),
        ];
    }
}
