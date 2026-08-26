<?php

namespace App\Ai\Tools;

use App\Models\Movie;
use App\Services\CustomerMovieReadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SearchMovies extends ReadOnlyTool implements Tool
{
    public function __construct(private readonly CustomerMovieReadService $movies) {}

    public function name(): string
    {
        return 'search_movies';
    }

    public function description(): string
    {
        return 'Search bounded, customer-visible MovieMate movies. Stored status is authoritative; release date never changes lifecycle.';
    }

    public function handle(Request $request): string
    {
        $input = $this->validate($request, [
            'query' => ['sometimes', 'nullable', 'string', 'max:100'],
            'genre' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', Movie::PUBLIC_STATUSES)],
            'age_rating' => ['sometimes', 'nullable', 'string', 'max:20'],
            'runtime_band' => ['sometimes', 'nullable', 'string', 'in:short,standard,long'],
            'cinema_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.CustomerMovieReadService::MAX_RESULTS],
        ]);

        return $this->json([
            'movies' => $this->movies->search($input, (int) ($input['limit'] ?? CustomerMovieReadService::MAX_RESULTS))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->max(100)->description('Title or description text.'),
            'genre' => $schema->string()->max(100)->description('Public genre name.'),
            'status' => $schema->string()->enum(Movie::PUBLIC_STATUSES)->description('Stored public Movie status.'),
            'age_rating' => $schema->string()->max(20)->description('Stored public age rating.'),
            'runtime_band' => $schema->string()->enum(['short', 'standard', 'long'])->description('Short ≤90, standard 91–120, long >120 minutes.'),
            'cinema_code' => $schema->string()->max(50)->description('Public cinema code.'),
            'date' => $schema->string()->description('Show date in YYYY-MM-DD when availability is required.'),
            'limit' => $schema->integer()->min(1)->max(CustomerMovieReadService::MAX_RESULTS)->description('Maximum results.'),
        ];
    }
}
