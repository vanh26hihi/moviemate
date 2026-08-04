<?php

namespace App\Console\Commands;

use App\Models\Cinema;
use App\Models\Room;
use App\Services\CinemaContext;
use App\Services\RoomLayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class RebuildSeatLayouts extends Command
{
    protected $signature = 'moviemate:rebuild-seat-layouts
        {--dry-run : Chỉ hiển thị kế hoạch, không ghi database}
        {--force : Xác nhận xóa dữ liệu nghiệp vụ cũ và dựng layout sạch}';

    protected $description = 'Dựng lại dữ liệu layout ghế sạch cho các phòng P01, P02 và P03 của rạp canonical';

    public function __construct(private readonly RoomLayoutService $layouts)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            [$cinema, $rooms] = $this->resolveScope();
            $plan = $this->buildPlan($cinema, $rooms);
            $this->renderPlan($cinema, $rooms, $plan);

            if ($this->option('dry-run')) {
                $this->info('DRY-RUN: không có dữ liệu nào được ghi.');

                return self::SUCCESS;
            }

            if (! $this->option('force')) {
                $this->warn('Chưa có --force: không có dữ liệu nào được ghi. Review kế hoạch rồi chạy lại với --force.');

                return self::SUCCESS;
            }

            DB::transaction(function () use ($cinema, $rooms): void {
                [$lockedCinema, $lockedRooms] = $this->resolveScope(lock: true);
                if ($lockedCinema->id !== $cinema->id || $lockedRooms->pluck('id')->all() !== $rooms->pluck('id')->all()) {
                    throw new RuntimeException('Canonical scope đã thay đổi trong khi chờ transaction lock.');
                }

                $roomIds = $lockedRooms->pluck('id');
                $showtimeIds = DB::table('showtimes')->where('cinema_id', $lockedCinema->id)
                    ->whereIn('room_id', $roomIds)->lockForUpdate()->pluck('id');
                $bookingIds = DB::table('bookings')->whereIn('showtime_id', $showtimeIds)
                    ->lockForUpdate()->pluck('id');

                $this->deleteOptionalDependencies($bookingIds, $showtimeIds, $roomIds);
                DB::table('payments')->whereIn('booking_id', $bookingIds)->delete();
                DB::table('booking_seats')->whereIn('booking_id', $bookingIds)->delete();
                DB::table('bookings')->whereIn('id', $bookingIds)->delete();
                DB::table('showtimes')->whereIn('id', $showtimeIds)->delete();
                DB::table('room_layout_cells')->whereIn(
                    'room_layout_id',
                    DB::table('room_layouts')->whereIn('room_id', $roomIds)->select('id')
                )->delete();
                DB::table('room_layouts')->whereIn('room_id', $roomIds)->delete();
                DB::table('seats')->whereIn('room_id', $roomIds)->delete();

                $this->ensureSeatTypes();
                $layouts = $this->layouts->rebuildDefaultLayouts($lockedRooms);

                if ($layouts->count() !== 3 || $layouts->contains(fn ($layout) => $layout->status !== 'published')) {
                    throw new RuntimeException('Không tạo đủ ba published layouts.');
                }
            }, 3);

            $this->info('Đã dựng lại thành công 3 layouts và 332 ghế trong một transaction.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Rebuild bị hủy và rollback: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array{Cinema, Collection<int, Room>} */
    private function resolveScope(bool $lock = false): array
    {
        $canonicalQuery = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY);
        if ($lock) {
            $canonicalQuery->lockForUpdate();
        }
        $canonicalMatches = $canonicalQuery->get();
        if ($canonicalMatches->count() !== 1) {
            throw new RuntimeException('Phải có đúng một cinema mang canonical_key MovieMate FPT.');
        }
        $cinema = $canonicalMatches->sole();

        $primaryCount = Cinema::query()->where('status', 'active')->where('is_primary', true)->count();
        if ($primaryCount !== 1 || ! $cinema->is_primary || $cinema->status !== 'active' || $cinema->archived_at !== null) {
            throw new RuntimeException('Canonical cinema không thỏa invariant active primary duy nhất.');
        }

        $roomQuery = Room::query()->where('cinema_id', $cinema->id)->whereIn('code', ['P01', 'P02', 'P03']);
        if ($lock) {
            $roomQuery->lockForUpdate();
        }
        $rooms = $roomQuery->orderBy('code')->get();
        if ($rooms->count() !== 3 || $rooms->pluck('code')->unique()->count() !== 3) {
            throw new RuntimeException('Phải tìm thấy duy nhất các phòng P01, P02 và P03 trong canonical cinema.');
        }
        if ($rooms->contains(fn (Room $room) => $room->status !== 'active')) {
            throw new RuntimeException('P01, P02 và P03 đều phải đang active.');
        }
        if (DB::table('showtimes')->whereIn('room_id', $rooms->pluck('id'))
            ->where('cinema_id', '!=', $cinema->id)->exists()) {
            throw new RuntimeException('Có showtime của P01/P02/P03 không thuộc canonical cinema.');
        }

        return [$cinema, $rooms];
    }

    private function buildPlan(Cinema $cinema, Collection $rooms): array
    {
        $roomIds = $rooms->pluck('id');
        $showtimeIds = DB::table('showtimes')->where('cinema_id', $cinema->id)
            ->whereIn('room_id', $roomIds)->pluck('id');
        $bookingIds = DB::table('bookings')->whereIn('showtime_id', $showtimeIds)->pluck('id');

        return [
            'seats' => DB::table('seats')->whereIn('room_id', $roomIds)->count(),
            'showtimes' => $showtimeIds->count(),
            'bookings' => $bookingIds->count(),
            'booking_seats' => DB::table('booking_seats')->whereIn('booking_id', $bookingIds)->count(),
            'payments' => DB::table('payments')->whereIn('booking_id', $bookingIds)->count(),
            'layouts' => 3,
            'new_seats' => 332,
        ];
    }

    private function renderPlan(Cinema $cinema, Collection $rooms, array $plan): void
    {
        $this->newLine();
        $this->line("Canonical cinema: #{$cinema->id} {$cinema->name} [{$cinema->canonical_key}]");
        $this->line('Rooms: '.$rooms->map(fn (Room $room) => "{$room->code} (#{$room->id})")->implode(', '));
        $this->table(['Hạng mục', 'Sẽ xóa', 'Sẽ tạo'], [
            ['Seats', $plan['seats'], $plan['new_seats']],
            ['Showtimes', $plan['showtimes'], 0],
            ['Bookings', $plan['bookings'], 0],
            ['Booking seats', $plan['booking_seats'], 0],
            ['Payments', $plan['payments'], 0],
            ['Published layouts', 'existing in P01/P02/P03', $plan['layouts']],
        ]);
    }

    private function ensureSeatTypes(): void
    {
        $definitions = [
            'normal' => ['aliases' => ['normal', 'regular', 'standard'], 'name' => 'Normal', 'modifier' => 0, 'pair' => false],
            'vip' => ['aliases' => ['vip'], 'name' => 'VIP', 'modifier' => 20000, 'pair' => false],
            'couple' => ['aliases' => ['couple', 'sweetbox', 'double'], 'name' => 'Couple', 'modifier' => 40000, 'pair' => true],
        ];

        foreach ($definitions as $code => $definition) {
            $existing = DB::table('seat_types')->get()->first(function (object $row) use ($definition): bool {
                return collect([$row->code, $row->slug, $row->name])
                    ->map(fn ($value) => mb_strtolower(trim((string) $value)))
                    ->contains(fn ($value) => in_array($value, $definition['aliases'], true));
            });
            if ($existing) {
                DB::table('seat_types')->where('id', $existing->id)->update([
                    'status' => true,
                    'is_pair' => $definition['pair'],
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('seat_types')->insert([
                'name' => $definition['name'],
                'code' => $code,
                'slug' => $code,
                'description' => null,
                'image_path' => null,
                'color' => null,
                'text_color' => null,
                'price_modifier' => $definition['modifier'],
                'is_pair' => $definition['pair'],
                'status' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function deleteOptionalDependencies(Collection $bookingIds, Collection $showtimeIds, Collection $roomIds): void
    {
        $definitions = [
            'seat_holds' => ['booking_id' => $bookingIds, 'showtime_id' => $showtimeIds, 'room_id' => $roomIds],
            'tickets' => ['booking_id' => $bookingIds],
            'ticket_checkins' => ['booking_id' => $bookingIds],
            'check_ins' => ['booking_id' => $bookingIds],
        ];

        foreach ($definitions as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column => $ids) {
                if (Schema::hasColumn($table, $column)) {
                    DB::table($table)->whereIn($column, $ids)->delete();
                    break;
                }
            }
        }
    }
}
