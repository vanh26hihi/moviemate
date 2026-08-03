<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KEY = 'moviemate-fpt-polytechnic';

    private const NAME = 'MovieMate Cinema – FPT Polytechnic';

    private const SCHOOL = 'Trường Cao đẳng FPT Polytechnic';

    private const ADDRESS = 'Tòa nhà FPT Polytechnic, Cổng số 2, số 13 Trịnh Văn Bô, Xuân Phương, Hà Nội 100000, Việt Nam';

    private const CITY = 'Hà Nội';

    private const COUNTRY = 'Việt Nam';

    private const LATITUDE = '21.0381298';

    private const LONGITUDE = '105.44239119453124';

    private const APPROVED_ROOMS = [
        9 => ['old_code' => 'P01', 'old_name' => 'Phòng 1', 'old_status' => 'active', 'code' => 'P01', 'name' => 'Phòng 1', 'status' => 'active'],
        10 => ['old_code' => 'P02', 'old_name' => 'Phòng 2', 'old_status' => 'active', 'code' => 'P02', 'name' => 'Phòng 2', 'status' => 'active'],
        11 => ['old_code' => 'P01', 'old_name' => 'Phòng 1', 'old_status' => 'active', 'code' => 'P03', 'name' => 'Phòng 3', 'status' => 'active'],
        12 => ['old_code' => 'R0012', 'old_name' => 'Phòng 1', 'old_status' => 'inactive', 'code' => 'ARCH-12', 'name' => 'Phòng 1 (Ngừng hoạt động)', 'status' => 'inactive'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $before = $this->historyCounts();
            [$canonicalId] = $this->resolveOrCreateCanonical();

            $rooms = DB::table('rooms')->lockForUpdate()->orderBy('id')->get();
            if ($rooms->isNotEmpty()) {
                $this->assertApprovedRoomsMatch($rooms);
            }
            $this->assertNoRoomCollisions($rooms);

            $this->mapCinemas($canonicalId);
            $this->mapAndMoveRooms($rooms, $canonicalId);
            $this->mapAndMoveShowtimes($canonicalId);
            $this->mapAndMoveOrders($canonicalId);

            DB::table('cinemas')->where('id', '!=', $canonicalId)->update([
                'status' => 'inactive',
                'is_primary' => false,
                'archived_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('cinemas')->where('id', $canonicalId)->update([
                'status' => 'active',
                'is_primary' => true,
                'archived_at' => null,
                'updated_at' => now(),
            ]);

            $this->assertFinalInvariants($canonicalId, $before);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cinema_consolidation_mappings')) {
            return;
        }

        DB::transaction(function (): void {
            $mappings = DB::table('cinema_consolidation_mappings')->orderByDesc('id')->get();

            foreach ($mappings->where('entity_type', 'order') as $mapping) {
                DB::table('orders')->where('id', $mapping->entity_id)->update([
                    'pickup_cinema_id' => $mapping->original_cinema_id,
                ]);
            }

            foreach ($mappings->where('entity_type', 'showtime') as $mapping) {
                DB::table('showtimes')->where('id', $mapping->entity_id)->update([
                    'cinema_id' => $mapping->original_cinema_id,
                ]);
            }

            foreach ($mappings->where('entity_type', 'room') as $mapping) {
                DB::table('rooms')->where('id', $mapping->entity_id)->update([
                    'cinema_id' => $mapping->original_cinema_id,
                    'code' => $mapping->original_code,
                    'name' => $mapping->original_name,
                    'status' => $mapping->original_status,
                ]);
            }

            // A showtime created after consolidation has no rollback mapping. Keep it,
            // but realign its denormalized cinema_id with the room restored above.
            DB::table('showtimes')->whereNotIn(
                'id',
                $mappings->where('entity_type', 'showtime')->pluck('entity_id')
            )->orderBy('id')->eachById(function (object $showtime): void {
                $roomCinemaId = DB::table('rooms')->where('id', $showtime->room_id)->value('cinema_id');
                if ($roomCinemaId !== null && (int) $showtime->cinema_id !== (int) $roomCinemaId) {
                    DB::table('showtimes')->where('id', $showtime->id)->update([
                        'cinema_id' => $roomCinemaId,
                        'updated_at' => now(),
                    ]);
                }
            });

            foreach ($mappings->where('entity_type', 'cinema') as $mapping) {
                $payload = json_decode((string) $mapping->original_payload, true, flags: JSON_THROW_ON_ERROR);
                DB::table('cinemas')->where('id', $mapping->entity_id)->update($payload);
            }

            $canonical = $mappings->firstWhere('entity_type', 'canonical');
            if (! $canonical) {
                DB::table('cinema_consolidation_mappings')->delete();

                return;
            }

            $hasReferences = DB::table('rooms')->where('cinema_id', $canonical->entity_id)->exists()
                || DB::table('showtimes')->where('cinema_id', $canonical->entity_id)->exists()
                || DB::table('orders')->where('pickup_cinema_id', $canonical->entity_id)->exists();

            if ($canonical->original_code === 'created' && ! $hasReferences) {
                DB::table('cinemas')->where('id', $canonical->entity_id)->delete();
            } elseif ($canonical->original_code === 'created') {
                DB::table('cinemas')->where('id', $canonical->entity_id)->update([
                    'canonical_key' => null,
                    'is_primary' => false,
                    'status' => 'inactive',
                    'archived_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $payload = json_decode((string) $canonical->original_payload, true, flags: JSON_THROW_ON_ERROR);
                DB::table('cinemas')->where('id', $canonical->entity_id)->update($payload);
            }

            DB::table('cinema_consolidation_mappings')->delete();
        });
    }

    private function resolveOrCreateCanonical(): array
    {
        $matches = collect(DB::table('cinemas')->where('canonical_key', self::KEY)->pluck('id'))
            ->merge(DB::table('cinemas')->where('latitude', self::LATITUDE)->where('longitude', self::LONGITUDE)->pluck('id'))
            ->merge(DB::table('cinemas')->where('address', self::ADDRESS)->pluck('id'))
            ->merge(DB::table('cinemas')->where('name', self::NAME)->pluck('id'))
            ->unique()->values();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple cinemas match the canonical FPT profile.');
        }

        $created = $matches->isEmpty();
        $canonicalId = $created
            ? DB::table('cinemas')->insertGetId([
                'name' => self::NAME, 'school_name' => self::SCHOOL, 'address' => self::ADDRESS,
                'city' => self::CITY, 'country' => self::COUNTRY, 'phone' => null,
                'latitude' => self::LATITUDE, 'longitude' => self::LONGITUDE,
                'status' => 'active', 'canonical_key' => self::KEY, 'is_primary' => true,
                'archived_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ])
            : (int) $matches->sole();

        $original = DB::table('cinemas')->where('id', $canonicalId)->lockForUpdate()->first();
        $this->insertMapping('canonical', $canonicalId, $canonicalId, $canonicalId, $created ? 'created' : 'reused', $original->name, $original->status, $created ? null : $this->cinemaPayload($original));

        DB::table('cinemas')->where('id', $canonicalId)->update([
            'canonical_key' => self::KEY, 'name' => self::NAME, 'school_name' => self::SCHOOL,
            'address' => self::ADDRESS, 'city' => self::CITY, 'country' => self::COUNTRY,
            'latitude' => self::LATITUDE, 'longitude' => self::LONGITUDE,
            'status' => 'active', 'is_primary' => true, 'archived_at' => null, 'updated_at' => now(),
        ]);

        return [$canonicalId, $created];
    }

    private function assertApprovedRoomsMatch($rooms): void
    {
        foreach (self::APPROVED_ROOMS as $id => $approved) {
            $room = $rooms->firstWhere('id', $id);
            if (! $room || $room->code !== $approved['old_code'] || $room->name !== $approved['old_name'] || $room->status !== $approved['old_status']) {
                throw new RuntimeException("Room {$id} no longer matches the approved preflight mapping.");
            }
        }
    }

    private function assertNoRoomCollisions($rooms): void
    {
        $codes = [];
        $activeNames = [];
        foreach ($rooms as $room) {
            $approved = self::APPROVED_ROOMS[$room->id] ?? null;
            $code = mb_strtolower(trim((string) ($approved['code'] ?? $room->code)));
            $name = mb_strtolower(trim((string) ($approved['name'] ?? $room->name)));
            $status = $approved['status'] ?? $room->status;

            if ($code !== '' && isset($codes[$code])) {
                throw new RuntimeException("Room code collision between rooms {$codes[$code]} and {$room->id}.");
            }
            $codes[$code] = $room->id;

            if ($status === 'active' && isset($activeNames[$name])) {
                throw new RuntimeException("Operational room name collision between rooms {$activeNames[$name]} and {$room->id}.");
            }
            if ($status === 'active') {
                $activeNames[$name] = $room->id;
            }
        }
    }

    private function mapCinemas(int $canonicalId): void
    {
        foreach (DB::table('cinemas')->where('id', '!=', $canonicalId)->lockForUpdate()->get() as $cinema) {
            $this->insertMapping('cinema', $cinema->id, $cinema->id, $canonicalId, $cinema->canonical_key, $cinema->name, $cinema->status, $this->cinemaPayload($cinema));
        }
    }

    private function mapAndMoveRooms($rooms, int $canonicalId): void
    {
        foreach ($rooms as $room) {
            $this->insertMapping('room', $room->id, $room->cinema_id, $canonicalId, $room->code, $room->name, $room->status);
            $approved = self::APPROVED_ROOMS[$room->id] ?? null;
            DB::table('rooms')->where('id', $room->id)->update([
                'cinema_id' => $canonicalId,
                'code' => $approved['code'] ?? $room->code,
                'name' => $approved['name'] ?? $room->name,
                'status' => $approved['status'] ?? $room->status,
                'updated_at' => now(),
            ]);
        }
    }

    private function mapAndMoveShowtimes(int $canonicalId): void
    {
        foreach (DB::table('showtimes')->where('cinema_id', '!=', $canonicalId)->lockForUpdate()->get() as $showtime) {
            $this->insertMapping('showtime', $showtime->id, $showtime->cinema_id, $canonicalId, null, null, $showtime->status);
            DB::table('showtimes')->where('id', $showtime->id)->update(['cinema_id' => $canonicalId, 'updated_at' => now()]);
        }
    }

    private function mapAndMoveOrders(int $canonicalId): void
    {
        foreach (DB::table('orders')->whereNotNull('pickup_cinema_id')->where('pickup_cinema_id', '!=', $canonicalId)->lockForUpdate()->get() as $order) {
            $this->insertMapping('order', $order->id, $order->pickup_cinema_id, $canonicalId, null, null, $order->status);
            DB::table('orders')->where('id', $order->id)->update(['pickup_cinema_id' => $canonicalId, 'updated_at' => now()]);
        }
    }

    private function assertFinalInvariants(int $canonicalId, array $before): void
    {
        if (DB::table('cinemas')->where('is_primary', true)->where('status', 'active')->count() !== 1) {
            throw new RuntimeException('Single-cinema primary invariant failed.');
        }
        if (DB::table('rooms')->where('cinema_id', '!=', $canonicalId)->exists()) {
            throw new RuntimeException('A room remains outside the canonical cinema.');
        }
        if (DB::table('showtimes')->join('rooms', 'rooms.id', '=', 'showtimes.room_id')->whereColumn('showtimes.cinema_id', '!=', 'rooms.cinema_id')->exists()) {
            throw new RuntimeException('Showtime cinema does not match its room.');
        }
        if ($before !== $this->historyCounts()) {
            throw new RuntimeException('Historical entity counts changed during cinema consolidation.');
        }
    }

    private function historyCounts(): array
    {
        return [
            'rooms' => DB::table('rooms')->count(), 'seats' => DB::table('seats')->count(),
            'showtimes' => DB::table('showtimes')->count(), 'bookings' => DB::table('bookings')->count(),
            'payments' => DB::table('payments')->count(), 'booking_seats' => DB::table('booking_seats')->count(),
        ];
    }

    private function insertMapping(string $type, int $entityId, ?int $originalCinemaId, int $canonicalId, ?string $code, ?string $name, ?string $status, ?array $payload = null): void
    {
        DB::table('cinema_consolidation_mappings')->insert([
            'entity_type' => $type, 'entity_id' => $entityId,
            'original_cinema_id' => $originalCinemaId, 'canonical_cinema_id' => $canonicalId,
            'original_code' => $code, 'original_name' => $name, 'original_status' => $status,
            'original_payload' => $payload ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'migrated_at' => now(),
        ]);
    }

    private function cinemaPayload(object $cinema): array
    {
        return [
            'canonical_key' => $cinema->canonical_key, 'name' => $cinema->name,
            'school_name' => $cinema->school_name, 'address' => $cinema->address,
            'city' => $cinema->city, 'country' => $cinema->country,
            'phone' => $cinema->phone, 'latitude' => $cinema->latitude,
            'longitude' => $cinema->longitude, 'image' => $cinema->image,
            'description' => $cinema->description, 'status' => $cinema->status,
            'is_primary' => $cinema->is_primary, 'archived_at' => $cinema->archived_at,
            'updated_at' => $cinema->updated_at,
        ];
    }
};
