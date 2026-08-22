<?php

namespace Tests\Feature\Showtimes;

use App\Models\ActivityLog;
use App\Models\Cinema;
use App\Models\PresentationFormat;
use App\Models\PriceBookVersion;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Showtime;
use App\Services\BulkShowtimeScheduleService;
use App\Services\MoviePresentationFormatService;
use App\Services\PresentationFormatManagementService;
use App\Services\PriceBookVersionService;
use App\Services\RoomPresentationCapabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDOException;
use RuntimeException;

class BulkShowtimeSchedulingTest extends ShowtimeTestCase
{
    protected bool $prepareSingleShowtimeFormats = true;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_preview_requires_authorization_nonempty_well_formed_unique_intent_rows(): void
    {
        $row = $this->row('one', $this->movie(), $this->rooms['P01']);

        $this->postJson(route('admin.showtimes.bulk.preview'), ['rows' => [$row]])->assertUnauthorized();
        $this->actingAs($this->userWithRole('staff'))->postJson(route('admin.showtimes.bulk.preview'), ['rows' => [$row]])->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->postJson(route('admin.showtimes.bulk.store'), ['rows' => [$row]])->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->postJson(route('admin.showtimes.bulk.preview'), ['rows' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->actingAs($this->userWithRole('manager'))->postJson(route('admin.showtimes.bulk.preview'), [
            'rows' => [$row, [...$row, 'movie_id' => null]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['rows.1.row_key', 'rows.1.movie_id']);
        $missingFormat = $row;
        unset($missingFormat['presentation_format_id']);
        $this->actingAs($this->userWithRole('manager'))->postJson(route('admin.showtimes.bulk.preview'), [
            'rows' => [$missingFormat],
        ])->assertUnprocessable()->assertJsonValidationErrors('rows.0.presentation_format_id');
    }

    public function test_preview_is_authoritative_side_effect_free_and_returns_operational_row_context(): void
    {
        $movie = $this->movie(120, ['title' => 'Phim lô']);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');
        $before = $this->operationalCounts();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), [
                'rows' => [$this->row('cross-midnight', $movie, $room, '2030-06-10', '23:30')],
            ])->assertOk()->assertJson([
                'valid' => true,
                'cinema_id' => $this->cinema->id,
                'timezone' => 'Asia/Ho_Chi_Minh',
                'summary' => ['total' => 1, 'valid_count' => 1, 'invalid_count' => 0],
                'rows' => [[
                    'row_key' => 'cross-midnight',
                    'valid' => true,
                    'movie' => ['title' => 'Phim lô'],
                    'room' => ['code' => 'P01'],
                    'window' => [
                        'start_display' => '10/06/2030 23:30',
                        'end_display' => '11/06/2030 01:30',
                        'cleaning_display' => '11/06/2030 01:30 – 11/06/2030 01:45',
                        'room_ready_display' => '11/06/2030 01:45',
                    ],
                ]],
            ]);
        }

        $this->assertSame($before, $this->operationalCounts());
    }

    public function test_internal_duplicates_partial_overlap_and_cleaning_overlap_mark_both_rows_invalid(): void
    {
        $movie = $this->movie(120);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');

        foreach ([
            ['18:00', '18:00'],
            ['18:00', '19:00'],
            ['18:00', '20:05'],
        ] as [$first, $second]) {
            $response = $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), [
                'rows' => [
                    $this->row('row-a', $movie, $room, time: $first),
                    $this->row('row-b', $movie, $room, time: $second),
                ],
            ])->assertOk()->assertJson([
                'valid' => false,
                'summary' => ['total' => 2, 'valid_count' => 0, 'invalid_count' => 2],
            ]);

            foreach ([0, 1] as $index) {
                $response->assertJsonPath("rows.{$index}.code", 'BATCH_ROOM_CONFLICT')
                    ->assertJsonPath("rows.{$index}.internal_conflicts.0.source", 'batch');
            }
        }
    }

    public function test_exact_ready_boundary_multi_room_same_time_and_multiple_dates_are_valid(): void
    {
        $movie = $this->movie(120);
        $rows = [
            $this->row('room-one-a', $movie, $this->rooms['P01'], time: '18:00'),
            $this->row('room-one-ready', $movie, $this->rooms['P01'], time: '20:15'),
            $this->row('room-two-same-time', $movie, $this->rooms['P02'], time: '18:00'),
            $this->row('next-date', $movie, $this->rooms['P03'], '2030-06-11', '18:00'),
        ];

        $this->actingAs($this->userWithRole('manager'))->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertOk()->assertJson([
                'valid' => true,
                'summary' => ['total' => 4, 'valid_count' => 4, 'invalid_count' => 0],
            ]);
    }

    public function test_bulk_supports_multiple_formats_and_format_does_not_partition_room_conflicts_or_pricing(): void
    {
        $movie = $this->movie(90);
        $threeD = PresentationFormat::query()->create([
            'code' => '3D', 'name' => '3D', 'is_active' => true, 'sort_order' => 20,
        ]);
        $movie->supportedPresentationFormats()->attach($threeD);
        $this->rooms['P01']->presentationCapabilities()->attach($threeD);
        $this->rooms['P02']->presentationCapabilities()->attach($threeD);
        $admin = $this->userWithRole('admin');
        $twoDRow = $this->row('two-d', $movie, $this->rooms['P01'], time: '18:00');
        $threeDConflict = [
            ...$this->row('three-d-conflict', $movie, $this->rooms['P01'], time: '18:30'),
            'presentation_format_id' => $threeD->id,
        ];

        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), [
            'rows' => [$twoDRow, $threeDConflict],
        ])->assertOk()
            ->assertJsonPath('rows.0.code', 'BATCH_ROOM_CONFLICT')
            ->assertJsonPath('rows.1.code', 'BATCH_ROOM_CONFLICT');

        $threeDRow = [
            ...$this->row('three-d', $movie, $this->rooms['P02'], '2030-06-11'),
            'presentation_format_id' => $threeD->id,
        ];
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), [
            'rows' => [$twoDRow, $threeDRow],
        ])->assertCreated()->assertJson(['created_count' => 2]);

        $showtimes = Showtime::query()->with('ticketPrices.seatType')->orderBy('id')->get();
        $this->assertSame([$this->presentationFormat->id, $threeD->id], $showtimes->pluck('presentation_format_id')->all());
        $this->assertSame(
            $showtimes[0]->ticketPrices->pluck('final_unit_amount_vnd', 'seatType.code')->all(),
            $showtimes[1]->ticketPrices->pluck('final_unit_amount_vnd', 'seatType.code')->all(),
        );
    }

    public function test_cross_midnight_candidates_compare_authoritative_datetimes_across_business_dates(): void
    {
        $movie = $this->movie(120);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => [
            $this->row('late', $movie, $room, '2030-06-10', '23:30'),
            $this->row('too-early', $movie, $room, '2030-06-11', '01:44'),
        ]])->assertOk()->assertJson(['valid' => false, 'summary' => ['invalid_count' => 2]]);

        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => [
            $this->row('late', $movie, $room, '2030-06-10', '23:30'),
            $this->row('ready', $movie, $room, '2030-06-11', '01:45'),
        ]])->assertOk()->assertJson(['valid' => true, 'summary' => ['valid_count' => 2]]);
    }

    public function test_persisted_conflict_remains_primary_and_internal_context_is_still_detected(): void
    {
        $movie = $this->movie(120);
        $room = $this->rooms['P01'];
        $this->existing($movie, $room);

        $this->actingAs($this->userWithRole('admin'))->postJson(route('admin.showtimes.bulk.preview'), ['rows' => [
            $this->row('persisted', $movie, $room, time: '18:30'),
            $this->row('internal', $movie, $room, time: '19:00'),
        ]])->assertOk()
            ->assertJsonPath('rows.0.code', 'ROOM_CONFLICT')
            ->assertJsonPath('rows.0.conflict.source', 'persisted')
            ->assertJsonPath('rows.0.internal_conflicts.0.row_key', 'internal')
            ->assertJsonMissing(['customer_email' => true])
            ->assertJsonMissing(['booking_code' => true]);
    }

    public function test_past_exact_now_closed_day_operating_window_inactive_room_layout_and_runtime_reuse_single_codes(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 20:33:00', 'Asia/Ho_Chi_Minh'));
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');

        foreach (['20:32', '20:33'] as $time) {
            $this->assertPreviewCode($admin, $this->row('clock-'.$time, $movie, $room, time: $time), 'PAST_START');
        }

        $hours = $this->cinema->operatingHours()->create([
            'day_of_week' => 1, 'opens_at' => '21:00', 'latest_show_start_at' => '22:00', 'is_closed' => false,
        ]);
        $this->assertPreviewCode($admin, $this->row('outside', $movie, $room, time: '20:34'), 'OUTSIDE_START_WINDOW');
        $hours->update(['is_closed' => true]);
        $this->assertPreviewCode($admin, $this->row('closed', $movie, $room, time: '21:00'), 'CINEMA_CLOSED');
        $hours->delete();

        $room->update(['status' => 'maintenance']);
        $this->assertPreviewCode($admin, $this->row('inactive', $movie, $room, time: '21:00'), 'ROOM_UNAVAILABLE');
        $room->update(['status' => 'active']);
        $room->layouts()->update(['status' => 'retired']);
        $this->assertPreviewCode($admin, $this->row('layout', $movie, $room, time: '21:00'), 'LAYOUT_UNAVAILABLE');
        RoomLayout::query()->create([
            'room_id' => $room->id, 'version' => 2, 'rows' => 1, 'columns' => 1,
            'screen_position' => 'top', 'status' => 'published', 'published_at' => now(),
        ]);
        $movie->update(['duration' => 0]);
        $this->assertPreviewCode($admin, $this->row('runtime', $movie, $room, time: '21:00'), 'INVALID_RUNTIME');
    }

    public function test_mixed_cinema_is_rejected_for_global_admin_and_hidden_from_branch_manager(): void
    {
        $otherCinema = Cinema::factory()->create(['status' => 'active', 'archived_at' => null]);
        $otherRoom = Room::factory()->create(['cinema_id' => $otherCinema->id, 'code' => 'OTHER']);
        $movie = $this->movie();
        RoomLayout::query()->create([
            'room_id' => $otherRoom->id,
            'version' => 1,
            'name' => 'Sơ đồ phòng khác',
            'rows' => 1,
            'columns' => 1,
            'screen_position' => 'top',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $rows = [
            $this->row('local', $movie, $this->rooms['P01']),
            $this->row('other', $movie, $otherRoom),
        ];

        $this->actingAs($this->userWithRole('admin'))->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->actingAs($this->userWithRole('manager'))->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertNotFound();
    }

    public function test_forged_authoritative_fields_are_rejected_by_preview_and_publish(): void
    {
        $row = [
            ...$this->row('forged', $this->movie(), $this->rooms['P01']),
            'cinema_id' => 999,
            'room_layout_id' => 999,
            'status' => 'cancelled',
            'price' => 1,
            'vip_price' => 1,
            'pricing_version' => 'forged',
            'room_ready' => 'never',
            'timezone' => 'UTC',
        ];

        foreach (['admin.showtimes.bulk.preview', 'admin.showtimes.bulk.store'] as $route) {
            $this->actingAs($this->userWithRole('admin'))->postJson(route($route), ['rows' => [$row]])
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'rows.0.cinema_id', 'rows.0.room_layout_id', 'rows.0.status', 'rows.0.price',
                    'rows.0.vip_price', 'rows.0.pricing_version', 'rows.0.room_ready', 'rows.0.timezone',
                ]);
        }
        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_operating_start_boundaries_are_inclusive_and_one_minute_outside_is_invalid(): void
    {
        $this->cinema->operatingHours()->create([
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'latest_show_start_at' => '23:00',
            'is_closed' => false,
        ]);
        $movie = $this->movie(30);
        $admin = $this->userWithRole('admin');

        foreach (['09:00', '23:00'] as $time) {
            $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => [
                $this->row('valid-'.$time, $movie, $this->rooms['P01'], time: $time),
            ]])->assertOk()->assertJson(['valid' => true]);
        }
        foreach (['08:59', '23:01'] as $time) {
            $this->assertPreviewCode($admin, $this->row('invalid-'.$time, $movie, $this->rooms['P01'], time: $time), 'OUTSIDE_START_WINDOW');
        }
    }

    public function test_one_malformed_row_blocks_publish_before_any_valid_row_is_created(): void
    {
        $valid = $this->row('valid', $this->movie(), $this->rooms['P01']);
        $malformed = $this->row('malformed', $this->movie(), $this->rooms['P02']);
        unset($malformed['show_time']);

        $this->actingAs($this->userWithRole('manager'))->postJson(route('admin.showtimes.bulk.store'), [
            'rows' => [$valid, $malformed],
        ])->assertUnprocessable()->assertJsonValidationErrors('rows.1.show_time');
        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_price_book_gap_marks_one_row_invalid_and_blocks_the_whole_batch(): void
    {
        $versions = app(PriceBookVersionService::class);
        $versions->retire(PriceBookVersion::query()->where('status', PriceBookVersion::STATUS_PUBLISHED)->sole());
        $limited = $versions->createDraft($this->chainPriceBook(), [
            'base_price_vnd' => 80_000,
            'effective_from' => '2030-06-10',
            'effective_until' => '2030-06-11',
        ]);
        $versions->publish($limited);
        $movie = $this->movie(90);
        $rows = [
            $this->row('priced', $movie, $this->rooms['P01'], '2030-06-10', '18:00'),
            $this->row('gap', $movie, $this->rooms['P02'], '2030-06-11', '18:00'),
        ];
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertOk()
            ->assertJsonPath('summary.valid_count', 1)
            ->assertJsonPath('summary.invalid_count', 1)
            ->assertJsonPath('rows.1.code', 'SHOWTIME_PRICE_UNRESOLVABLE');
        $this->actingAs($manager)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertUnprocessable()
            ->assertJsonPath('rows.1.code', 'SHOWTIME_PRICE_UNRESOLVABLE');
        $this->assertDatabaseCount('showtimes', 0);
        $this->assertDatabaseCount('showtime_ticket_prices', 0);
    }

    public function test_publish_creates_valid_multi_room_multi_date_batch_with_single_create_pricing_and_audit_parity(): void
    {
        $movie = $this->movie(90);
        $rows = [
            $this->row('one', $movie, $this->rooms['P01']),
            $this->row('two', $movie, $this->rooms['P02'], '2030-06-11', '19:00'),
        ];

        $this->actingAs($this->userWithRole('manager'))->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertCreated()->assertJson(['valid' => true, 'created_count' => 2]);

        $this->assertDatabaseCount('showtimes', 2);
        $this->assertDatabaseCount('activity_logs', 2);
        foreach (Showtime::query()->with('ticketPrices.seatType')->get() as $showtime) {
            $this->assertSame('active', $showtime->status);
            $this->assertSame(80_000, (int) $showtime->ticketPrices->firstWhere('seatType.code', 'normal')->final_unit_amount_vnd);
            $this->assertSame(110_000, (int) $showtime->ticketPrices->firstWhere('seatType.code', 'vip')->final_unit_amount_vnd);
            $this->assertSame(1, $showtime->ticketPrices->pluck('price_book_version_id')->unique()->count());
            $this->assertSame($this->cinema->id, (int) $showtime->cinema_id);
            $this->assertNotNull($showtime->room_layout_id);
            $this->assertSame($this->presentationFormat->id, $showtime->presentation_format_id);
        }
        $this->assertTrue(ActivityLog::query()->get()->every(fn (ActivityLog $log): bool => ($log->context['source'] ?? null) === 'bulk'));
    }

    public function test_direct_publish_revalidates_and_rejects_any_invalid_row_without_partial_insert(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 20:00:00', 'Asia/Ho_Chi_Minh'));
        $movie = $this->movie(90);
        $rows = [
            $this->row('valid', $movie, $this->rooms['P01'], time: '21:00'),
            $this->row('past', $movie, $this->rooms['P02'], time: '20:00'),
        ];

        $this->actingAs($this->userWithRole('admin'))->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertUnprocessable()->assertJson([
                'valid' => false,
                'summary' => ['total' => 2, 'valid_count' => 1, 'invalid_count' => 1],
            ])->assertJsonPath('rows.1.code', 'PAST_START');
        $this->assertDatabaseCount('showtimes', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_bulk_persistence_pricing_matches_equivalent_single_create_semantics(): void
    {
        $movie = $this->movie(90);
        $admin = $this->userWithRole('admin');
        $singlePayload = $this->payload($movie, $this->rooms['P01'], [
            'show_time' => '18:00',
            'presentation_format_id' => $this->presentationFormat->id,
        ]);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $singlePayload)->assertRedirect(route('admin.showtimes.index'));
        $single = Showtime::query()->with('ticketPrices.seatType')->firstOrFail();

        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), ['rows' => [
            $this->row('bulk', $movie, $this->rooms['P02'], time: '18:00'),
        ]])->assertCreated();
        $bulk = Showtime::query()->with('ticketPrices.seatType')->latest('id')->firstOrFail();

        $this->assertSame(
            $single->ticketPrices->pluck('final_unit_amount_vnd', 'seatType.code')->all(),
            $bulk->ticketPrices->pluck('final_unit_amount_vnd', 'seatType.code')->all(),
        );
        $this->assertSame('active', $bulk->status);
    }

    public function test_preview_and_publish_share_authority_for_every_representative_failure_code(): void
    {
        $admin = $this->userWithRole('admin');
        $movie = $this->movie(90);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 20:00:00', 'Asia/Ho_Chi_Minh'));
        $this->assertPreviewPublishParity(
            $admin,
            [$this->row('past', $movie, $this->rooms['P01'], time: '20:00')],
            'PAST_START',
        );
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2029-06-10 20:00:00', 'Asia/Ho_Chi_Minh'));

        $existing = $this->existing($movie, $this->rooms['P01']);
        $this->assertPreviewPublishParity(
            $admin,
            [$this->row('persisted', $movie, $this->rooms['P01'], time: '18:30')],
            'ROOM_CONFLICT',
        );
        $existing->delete();

        $this->assertPreviewPublishParity($admin, [
            $this->row('internal-a', $movie, $this->rooms['P01'], time: '18:00'),
            $this->row('internal-b', $movie, $this->rooms['P01'], time: '18:30'),
        ], 'BATCH_ROOM_CONFLICT');

        $hours = $this->cinema->operatingHours()->create([
            'day_of_week' => 1,
            'opens_at' => '09:00',
            'latest_show_start_at' => '23:00',
            'is_closed' => true,
        ]);
        $this->assertPreviewPublishParity(
            $admin,
            [$this->row('closed', $movie, $this->rooms['P01'])],
            'CINEMA_CLOSED',
        );
        $hours->update(['is_closed' => false, 'opens_at' => '19:00']);
        $this->assertPreviewPublishParity(
            $admin,
            [$this->row('outside', $movie, $this->rooms['P01'])],
            'OUTSIDE_START_WINDOW',
        );
        $hours->delete();

        $this->rooms['P01']->layouts()->update(['status' => 'retired']);
        $this->assertPreviewPublishParity(
            $admin,
            [$this->row('layout', $movie, $this->rooms['P01'])],
            'LAYOUT_UNAVAILABLE',
        );
        RoomLayout::query()->create([
            'room_id' => $this->rooms['P01']->id, 'version' => 2, 'rows' => 1, 'columns' => 1,
            'screen_position' => 'top', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->rooms['P01']->update(['status' => 'maintenance']);
        $this->assertPreviewPublishParity(
            $admin,
            [$this->row('inactive', $movie, $this->rooms['P01'])],
            'ROOM_UNAVAILABLE',
        );
        $this->rooms['P01']->update(['status' => 'active']);

        $movie->update(['duration' => 0]);
        $this->assertPreviewPublishParity(
            $admin,
            [$this->row('runtime', $movie, $this->rooms['P01'])],
            'INVALID_RUNTIME',
        );
    }

    public function test_publish_revalidates_current_format_activity_movie_support_and_room_capability_atomically(): void
    {
        $admin = $this->userWithRole('admin');
        $movie = $this->movie(90);
        $rows = [
            $this->row('one', $movie, $this->rooms['P01']),
            $this->row('two', $movie, $this->rooms['P02'], '2030-06-11'),
        ];

        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertOk()->assertJson(['valid' => true])
            ->assertJsonPath('rows.0.presentation_format.id', $this->presentationFormat->id)
            ->assertJsonPath('rows.0.presentation_format.code', '2D');

        $movie->supportedPresentationFormats()->detach($this->presentationFormat);
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertUnprocessable()->assertJsonPath('rows.0.code', 'MOVIE_FORMAT_UNSUPPORTED');
        $this->assertDatabaseCount('showtimes', 0);

        $movie->supportedPresentationFormats()->attach($this->presentationFormat);
        $this->rooms['P02']->presentationCapabilities()->detach($this->presentationFormat);
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertUnprocessable()->assertJsonPath('rows.1.code', 'ROOM_FORMAT_UNSUPPORTED');
        $this->assertDatabaseCount('showtimes', 0);

        $this->rooms['P02']->presentationCapabilities()->attach($this->presentationFormat);
        $this->presentationFormat->update(['is_active' => false]);
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertUnprocessable()->assertJsonPath('rows.0.code', 'PRESENTATION_FORMAT_INACTIVE');
        $this->assertDatabaseCount('showtimes', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_committed_bulk_showtime_blocks_official_format_dependency_removals(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $alternate = PresentationFormat::query()->create([
            'code' => '3D', 'name' => '3D', 'is_active' => true, 'sort_order' => 20,
        ]);
        $movie->supportedPresentationFormats()->attach($alternate);
        $room->presentationCapabilities()->attach($alternate);
        $admin = $this->userWithRole('admin');

        app(BulkShowtimeScheduleService::class)->publish(
            [$this->row('one', $movie, $room)],
            $this->serviceUser(),
        );

        try {
            app(MoviePresentationFormatService::class)->update($movie, [], [], [$alternate->id]);
            $this->fail('Official Movie support removal must not invalidate a committed bulk Showtime.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        try {
            DB::transaction(function () use ($room, $alternate): void {
                $locked = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
                app(RoomPresentationCapabilityService::class)->syncLocked($locked, [$alternate->id]);
            });
            $this->fail('Official Room capability removal must not invalidate a committed bulk Showtime.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        try {
            app(PresentationFormatManagementService::class)->archive($this->presentationFormat, $admin);
            $this->fail('Official Format archive must not invalidate a committed bulk Showtime.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('showtimes', [
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'presentation_format_id' => $this->presentationFormat->id,
        ]);
    }

    public function test_stale_preview_and_time_passage_fail_whole_publish(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 17:00:00', 'Asia/Ho_Chi_Minh'));
        $movie = $this->movie(90);
        $admin = $this->userWithRole('admin');
        $rows = [
            $this->row('one', $movie, $this->rooms['P01'], time: '18:00'),
            $this->row('two', $movie, $this->rooms['P02'], time: '18:00'),
        ];
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertOk()->assertJson(['valid' => true]);
        $competing = $this->existing($movie, $this->rooms['P02'], ['show_time' => '18:30:00']);
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertUnprocessable()->assertJsonPath('rows.1.code', 'ROOM_CONFLICT');
        $this->assertDatabaseCount('showtimes', 1);
        $this->assertDatabaseHas('showtimes', ['id' => $competing->id]);

        $competing->delete();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 18:00:00', 'Asia/Ho_Chi_Minh'));
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertUnprocessable()->assertJsonPath('rows.0.code', 'PAST_START');
        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_publish_transaction_rolls_back_earlier_rows_when_later_persistence_callback_fails(): void
    {
        $rows = [
            $this->row('one', $this->movie(), $this->rooms['P01']),
            $this->row('two', $this->movie(), $this->rooms['P02']),
        ];
        $calls = 0;

        try {
            app(BulkShowtimeScheduleService::class)->publish(
                $rows,
                $this->serviceUser(),
                function () use (&$calls): void {
                    if (++$calls === 2) {
                        throw new RuntimeException('forced row-two failure');
                    }
                },
            );
            $this->fail('The complete batch transaction must roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced row-two failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_deadlock_retry_replays_whole_batch_without_duplicates(): void
    {
        $rows = [
            $this->row('one', $this->movie(), $this->rooms['P01']),
            $this->row('two', $this->movie(), $this->rooms['P02']),
        ];
        $attempts = 0;
        DB::commit();
        try {
            app(BulkShowtimeScheduleService::class)->publish(
                $rows,
                $this->serviceUser(),
                function () use (&$attempts): void {
                    if (++$attempts === 1) {
                        throw new QueryException(
                            'sqlite',
                            'forced bulk scheduling deadlock',
                            [],
                            new PDOException('Deadlock found when trying to get lock'),
                        );
                    }
                },
            );
            $this->assertSame(3, $attempts);
            $this->assertDatabaseCount('showtimes', 2);
        } finally {
            DB::table('showtimes')->delete();
            $integrityMigration = require database_path('migrations/2026_08_14_200000_harden_room_layout_history_integrity.php');
            foreach (['room_layout_cells_prevent_immutable_insert', 'room_layout_cells_prevent_immutable_update', 'room_layout_cells_prevent_immutable_delete'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
            DB::table('room_layout_cells')->delete();
            DB::table('room_layouts')->delete();
            DB::table('seats')->delete();
            DB::table('rooms')->delete();
            DB::table('movies')->delete();
            DB::table('presentation_formats')->delete();
            DB::table('users')->delete();
            $integrityMigration->up();
            DB::beginTransaction();
        }
    }

    public function test_final_owner_locks_are_unique_ascending_and_validation_queries_follow_room_movie_format_order(): void
    {
        $movie = $this->movie(30);
        $rows = [
            $this->row('five', $movie, $this->rooms['P03'], time: '21:00'),
            $this->row('two-a', $movie, $this->rooms['P01'], time: '18:00'),
            $this->row('nine', $movie, $this->rooms['P02'], time: '19:30'),
            $this->row('two-b', $movie, $this->rooms['P01'], time: '19:00'),
        ];
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
        });

        app(BulkShowtimeScheduleService::class)->publish($rows, $this->serviceUser());

        $lockIndex = collect($queries)->search(fn (array $query): bool => str_contains($query['sql'], 'from "rooms"')
            && str_contains($query['sql'], 'where "id" in') && str_contains($query['sql'], 'order by "id" asc'));
        $movieLockIndex = collect($queries)->search(fn (array $query): bool => str_contains($query['sql'], 'from "movies"')
            && str_contains($query['sql'], 'where "id" in') && str_contains($query['sql'], 'order by "id" asc'));
        $formatLockIndex = collect($queries)->search(fn (array $query): bool => str_contains($query['sql'], 'from "presentation_formats"')
            && str_contains($query['sql'], 'where "id" in') && str_contains($query['sql'], 'order by "id" asc'));
        $conflictIndex = collect($queries)->search(fn (array $query): bool => str_contains($query['sql'], 'from "showtimes"')
            && str_contains($query['sql'], '"room_id"'));
        $this->assertIsInt($lockIndex);
        $this->assertIsInt($movieLockIndex);
        $this->assertIsInt($formatLockIndex);
        $this->assertIsInt($conflictIndex);
        $this->assertLessThan($movieLockIndex, $lockIndex);
        $this->assertLessThan($formatLockIndex, $movieLockIndex);
        $this->assertLessThan($conflictIndex, $lockIndex);
        $this->assertSame($this->rooms->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all(), $queries[$lockIndex]['bindings']);
        $this->assertSame([$movie->id], $queries[$movieLockIndex]['bindings']);
        $this->assertSame([$this->presentationFormat->id], $queries[$formatLockIndex]['bindings']);
        $this->assertDatabaseCount('showtimes', 4);
    }

    public function test_preview_query_count_is_bounded_for_ten_rows_without_ui_n_plus_one(): void
    {
        $movie = $this->movie(30);
        $rows = [];
        foreach (range(0, 9) as $index) {
            $rows[] = $this->row(
                'query-'.$index,
                $movie,
                $this->rooms[['P01', 'P02', 'P03'][$index % 3]],
                '2030-06-'.str_pad((string) (10 + intdiv($index, 3)), 2, '0', STR_PAD_LEFT),
                sprintf('%02d:00', 10 + ($index % 3) * 3),
            );
        }
        $admin = $this->userWithRole('admin');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertOk()->assertJson(['valid' => true]);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(48, $queryCount, "Ten-row bulk preview query count exceeded Phase 7C snapshot budget: {$queryCount}");
    }

    public function test_publish_query_count_is_bounded_for_ten_rows_without_format_compatibility_n_plus_one(): void
    {
        $movie = $this->movie(30);
        $rows = collect(range(0, 9))->map(fn (int $index): array => $this->row(
            'publish-query-'.$index,
            $movie,
            $this->rooms[['P01', 'P02', 'P03'][$index % 3]],
            '2030-07-'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT),
            '18:00',
        ))->all();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->userWithRole('admin'))->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertCreated()->assertJson(['created_count' => 10]);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(100, $queryCount, "Ten-row bulk publish query count exceeded Phase 7C atomic snapshot budget: {$queryCount}");
    }

    private function assertPreviewCode($user, array $row, string $code): void
    {
        $this->actingAs($user)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => [$row]])
            ->assertOk()->assertJson(['valid' => false])->assertJsonPath('rows.0.code', $code);
    }

    /** @param list<array<string, mixed>> $rows */
    private function assertPreviewPublishParity($user, array $rows, string $code): void
    {
        $before = Showtime::query()->count();
        $this->actingAs($user)->postJson(route('admin.showtimes.bulk.preview'), ['rows' => $rows])
            ->assertOk()->assertJson(['valid' => false])->assertJsonPath('rows.0.code', $code);
        $this->actingAs($user)->postJson(route('admin.showtimes.bulk.store'), ['rows' => $rows])
            ->assertUnprocessable()->assertJson(['valid' => false])->assertJsonPath('rows.0.code', $code);
        $this->assertSame($before, Showtime::query()->count());
    }

    private function serviceUser()
    {
        $user = $this->userWithRole('admin');
        $this->actingAs($user);
        request()->setLaravelSession($this->app['session.store']);

        return $user;
    }

    /** @return array{row_key: string, movie_id: int, presentation_format_id: int, room_id: int, show_date: string, show_time: string} */
    private function row(string $key, $movie, $room, string $date = '2030-06-10', string $time = '18:00'): array
    {
        return [
            'row_key' => $key,
            'movie_id' => $movie->id,
            'presentation_format_id' => $this->presentationFormat->id,
            'room_id' => $room->id,
            'show_date' => $date,
            'show_time' => $time,
        ];
    }

    /** @return array<string, int> */
    private function operationalCounts(): array
    {
        return [
            'showtimes' => Showtime::query()->count(),
            'activity_logs' => ActivityLog::query()->count(),
            'showtime_ticket_prices' => DB::table('showtime_ticket_prices')->count(),
            'rooms' => Room::query()->count(),
        ];
    }
}
