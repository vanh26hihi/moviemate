<?php

namespace App\Ai\Tools;

use App\Services\CustomerMovieReadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class GetMovieDetails extends ReadOnlyTool implements Tool
{
    public function __construct(private readonly CustomerMovieReadService $movies) {}

    public function name(): string
    {
        return 'get_movie_details';
    }

    public function description(): string
    {
        return 'Get one customer-visible MovieMate movie by its unique ID or slug. Titles are not identities.';
    }

    public function handle(Request $request): string
    {
        $input = $this->validate($request, [
            'movie_id' => ['required_without:slug', 'prohibits:slug', 'integer', 'min:1'],
            'slug' => ['required_without:movie_id', 'prohibits:movie_id', 'string', 'max:191'],
        ]);

        return $this->json([
            'movie' => $this->movies->details(
                isset($input['movie_id']) ? (int) $input['movie_id'] : null,
                $input['slug'] ?? null,
            ),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'movie_id' => $schema->integer()->min(1)->description('Unique MovieMate movie ID. Supply either this or slug.'),
            'slug' => $schema->string()->max(191)->description('Unique MovieMate public slug. Supply either this or movie_id.'),
        ];
    }
}
