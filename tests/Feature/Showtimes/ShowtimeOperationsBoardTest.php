<?php

namespace Tests\Feature\Showtimes;

use App\Services\CinemaAccessService;
use Carbon\CarbonImmutable;

final class ShowtimeOperationsBoardTest extends ShowtimeTestCase
{
    protected bool $prepareSingleShowtimeFormats = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_board_requires_authentication_and_showtime_view_permission(): void
    {
        $url = route('admin.showtimes.board', ['from' => '2030-06-10', 'to' => '2030-06-16']);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('staff'))->get($url)->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))
            ->withSession([CinemaAccessService::SESSION_KEY => 'all'])
            ->get($url)->assertOk();
    }

    public function test_board_renders_room_day_matrix_and_authoritative_windows(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 08:00:00', 'Asia/Ho_Chi_Minh'));

        try {
            $movie = $this->movie(120, ['title' => 'Phim vận hành ma trận']);
            $showtime = $this->existing($movie, $this->rooms['P01'], [
                'show_date' => '2030-06-10',
                'show_time' => '18:00:00',
            ]);

            $response = $this->actingAs($this->userWithRole('admin'))
                ->withSession([CinemaAccessService::SESSION_KEY => 'all'])
                ->get(route('admin.showtimes.board', [
                    'from' => '2030-06-10',
                    'to' => '2030-06-16',
                ]));

            $response->assertOk()
                ->assertSee('data-showtime-board', false)
                ->assertSee('Lịch vận hành phòng chiếu')
                ->assertSee('Ma trận phòng và ngày')
                ->assertSee('Phim vận hành ma trận')
                ->assertSee('18:00')
                ->assertSee('20:00')
                ->assertSee('20:15')
                ->assertSee(route('admin.showtimes.show', $showtime), false)
                ->assertSee(route('admin.showtimes.board.export', [
                    'from' => '2030-06-10',
                    'to' => '2030-06-16',
                ]));

            $this->assertSame(1, $response->viewData('summary')['total']);
            $this->assertSame(7, $response->viewData('days')->count());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_board_rejects_periods_longer_than_thirty_one_days(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.showtimes.board'))
            ->get(route('admin.showtimes.board', [
                'from' => '2030-01-01',
                'to' => '2030-02-15',
            ]))
            ->assertRedirect(route('admin.showtimes.board'))
            ->assertSessionHasErrors('to');
    }

    public function test_csv_export_uses_same_filters_and_safe_utf8_content(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 08:00:00', 'Asia/Ho_Chi_Minh'));

        try {
            $movie = $this->movie(95, ['title' => '@Formula Movie']);
            $this->existing($movie, $this->rooms['P01'], [
                'show_date' => '2030-06-10',
                'show_time' => '18:00:00',
            ]);
            $response = $this->actingAs($this->userWithRole('admin'))
                ->withSession([CinemaAccessService::SESSION_KEY => 'all'])
                ->get(route('admin.showtimes.board.export', [
                    'from' => '2030-06-10',
                    'to' => '2030-06-10',
                ]));

            $response->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8')
                ->assertDownload('lich-suat-chieu-20300610-20300610.csv');

            $content = $response->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
            $this->assertStringContainsString("'@Formula Movie", $content);
            $this->assertStringContainsString('18:00', $content);
            $this->assertStringNotContainsString('Phim không thuộc bộ lọc', $content);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
