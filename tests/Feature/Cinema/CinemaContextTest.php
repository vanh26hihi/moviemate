<?php

namespace Tests\Feature\Cinema;

use App\Exceptions\CinemaConfigurationException;
use App\Models\Cinema;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CinemaContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_returns_the_only_active_primary_canonical_cinema(): void
    {
        $cinema = app(CinemaContext::class)->current();

        $this->assertSame(CinemaContext::CANONICAL_KEY, $cinema->canonical_key);
        $this->assertTrue($cinema->is_primary);
        $this->assertSame('active', $cinema->status);
    }

    public function test_resolver_never_falls_back_to_the_first_cinema(): void
    {
        Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->update([
            'is_primary' => false,
        ]);
        Cinema::factory()->legacy()->create(['name' => 'Cinema đầu tiên']);

        $this->expectException(CinemaConfigurationException::class);

        (new CinemaContext)->current();
    }

    public function test_resolver_fails_when_more_than_one_active_primary_exists(): void
    {
        Cinema::factory()->legacy()->create([
            'canonical_key' => 'unexpected-primary',
            'is_primary' => true,
            'status' => 'active',
            'archived_at' => null,
        ]);

        $this->expectException(CinemaConfigurationException::class);

        (new CinemaContext)->current();
    }
}
