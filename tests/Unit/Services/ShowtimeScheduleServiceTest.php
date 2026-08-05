<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidMovieRuntimeException;
use App\Exceptions\ShowtimeScheduleConfigurationException;
use App\Models\Movie;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ShowtimeScheduleServiceTest extends TestCase
{
    private ShowtimeScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cinema.timezone', 'Asia/Ho_Chi_Minh');
        config()->set('cinema.showtime_cleaning_buffer_minutes', 15);
        $this->service = app(ShowtimeScheduleService::class);
    }

    public function test_it_calculates_the_complete_window_in_business_timezone(): void
    {
        $window = $this->service->window($this->movieWithRawRuntime(120), '2030-06-10', '18:00');

        $this->assertSame('Asia/Ho_Chi_Minh', $window->start->getTimezone()->getName());
        $this->assertSame('2030-06-10 18:00', $window->start->format('Y-m-d H:i'));
        $this->assertSame('2030-06-10 20:00', $window->movieEnd->format('Y-m-d H:i'));
        $this->assertSame('2030-06-10 20:15', $window->operationalEnd->format('Y-m-d H:i'));
        $this->assertSame(120, $window->runtimeMinutes);
        $this->assertSame(15, $window->cleaningBufferMinutes);
    }

    public function test_zero_and_custom_cleaning_buffers_are_supported(): void
    {
        config()->set('cinema.showtime_cleaning_buffer_minutes', 0);
        $zero = $this->service->window($this->movieWithRawRuntime(90), '2030-06-10', '10:00');
        $this->assertSame('11:30', $zero->operationalEnd->format('H:i'));

        config()->set('cinema.showtime_cleaning_buffer_minutes', 37);
        $custom = $this->service->window($this->movieWithRawRuntime(90), '2030-06-10', '10:00');
        $this->assertSame('12:07', $custom->operationalEnd->format('H:i'));
        $this->assertSame(37, $custom->cleaningBufferMinutes);
    }

    public function test_invalid_cleaning_buffer_configuration_is_rejected_without_fallback(): void
    {
        foreach ([-1, 181, 'abc', 15.5] as $invalid) {
            config()->set('cinema.showtime_cleaning_buffer_minutes', $invalid);
            try {
                $this->service->cleaningBufferMinutes();
                $this->fail('Cấu hình buffer không hợp lệ phải bị từ chối.');
            } catch (ShowtimeScheduleConfigurationException $exception) {
                $this->assertSame('show_time', $exception->field);
            }
        }
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        config()->set('cinema.timezone', 'Saigon/Invalid');

        $this->expectException(ShowtimeScheduleConfigurationException::class);
        $this->service->timezone();
    }

    public function test_invalid_movie_runtimes_are_rejected(): void
    {
        foreach ([null, '', 0, -1, 601, 'abc'] as $invalid) {
            try {
                $this->service->validateRuntime($this->movieWithRawRuntime($invalid));
                $this->fail('Runtime không hợp lệ phải bị từ chối.');
            } catch (InvalidMovieRuntimeException $exception) {
                $this->assertSame('movie_id', $exception->field);
            }
        }

        $this->assertSame(600, $this->service->validateRuntime($this->movieWithRawRuntime(600)));
    }

    public function test_midnight_is_calculated_with_explicit_next_day(): void
    {
        $window = $this->service->window($this->movieWithRawRuntime(120), '2030-06-10', '23:30');

        $this->assertSame('2030-06-11 01:30', $window->movieEnd->format('Y-m-d H:i'));
        $this->assertSame('2030-06-11 01:45', $window->operationalEnd->format('Y-m-d H:i'));
    }

    public function test_carbon_input_and_window_are_immutable(): void
    {
        $start = CarbonImmutable::parse('2030-06-10 18:00', 'Asia/Ho_Chi_Minh');
        $movieEnd = $this->service->calculateMovieEnd($start, 120);
        $operationalEnd = $this->service->calculateOperationalEnd($movieEnd, 15);

        $this->assertSame('18:00', $start->format('H:i'));
        $this->assertSame('20:00', $movieEnd->format('H:i'));
        $this->assertSame('20:15', $operationalEnd->format('H:i'));
        $this->assertNotSame($start, $movieEnd);
        $this->assertNotSame($movieEnd, $operationalEnd);
    }

    private function movieWithRawRuntime(mixed $runtime): Movie
    {
        $movie = new Movie;
        $movie->setRawAttributes(['duration' => $runtime], true);

        return $movie;
    }
}
