<?php

namespace App\Services;

use App\Models\PresentationFormat;
use App\Models\Room;
use Illuminate\Validation\ValidationException;

final class RoomPresentationCapabilityService
{
    public function __construct(private readonly FutureActiveShowtimeDependency $dependencies) {}

    /** @param list<int> $formatIds */
    public function syncNew(Room $room, array $formatIds): void
    {
        $formats = $this->lockedFormats($formatIds);
        $this->assertKnownFormats($formatIds, $formats);
        if ($formats->contains(fn (PresentationFormat $format): bool => ! $format->is_active)) {
            throw ValidationException::withMessages([
                'presentation_format_ids' => 'Chỉ có thể chọn khả năng trình chiếu đang sử dụng.',
            ]);
        }
        $this->assertOperationalRequirement($room, $formatIds, $formats);
        $room->presentationCapabilities()->sync($formatIds);
    }

    /** @param list<int> $formatIds */
    public function syncLocked(Room $room, array $formatIds): void
    {
        $currentIds = $room->presentationCapabilities()->pluck('presentation_formats.id')->map(fn ($id): int => (int) $id)->all();
        $allIds = collect([...$currentIds, ...$formatIds])->unique()->sort()->values()->all();
        $formats = $this->lockedFormats($allIds);
        $this->assertKnownFormats($formatIds, $formats);

        $additions = array_values(array_diff($formatIds, $currentIds));
        if ($formats->whereIn('id', $additions)->contains(fn (PresentationFormat $format): bool => ! $format->is_active)) {
            throw ValidationException::withMessages([
                'presentation_format_ids' => 'Không thể thêm khả năng trình chiếu đã lưu trữ.',
            ]);
        }
        $this->assertOperationalRequirement($room, $formatIds, $formats);

        $removals = array_values(array_diff($currentIds, $formatIds));
        $conflictingFormatId = $this->dependencies->conflictingRoomFormatId((int) $room->id, $removals, lock: true);
        if ($conflictingFormatId !== null) {
            $name = $formats->firstWhere('id', $conflictingFormatId)?->name ?? (string) $conflictingFormatId;
            throw ValidationException::withMessages([
                'presentation_format_ids' => "Không thể bỏ khả năng {$name} vì phòng còn suất chiếu tương lai sử dụng định dạng này.",
            ]);
        }

        $room->presentationCapabilities()->sync($formatIds);
    }

    public function assertCanActivateLocked(Room $room): void
    {
        $ids = $room->presentationCapabilities()->pluck('presentation_formats.id')->map(fn ($id): int => (int) $id)->all();
        $formats = $this->lockedFormats($ids);
        if (! $formats->contains(fn (PresentationFormat $format): bool => $format->is_active)) {
            throw ValidationException::withMessages([
                'status' => 'Phòng phải có ít nhất một khả năng trình chiếu đang sử dụng trước khi kích hoạt.',
            ]);
        }
    }

    private function assertOperationalRequirement(Room $room, array $formatIds, $formats): void
    {
        if ($room->status === 'active'
            && ! $formats->whereIn('id', $formatIds)->contains(fn (PresentationFormat $format): bool => $format->is_active)) {
            throw ValidationException::withMessages([
                'presentation_format_ids' => 'Phòng đang hoạt động phải có ít nhất một khả năng trình chiếu đang sử dụng.',
            ]);
        }
    }

    /** @param list<int> $formatIds */
    private function lockedFormats(array $formatIds)
    {
        return PresentationFormat::query()->whereIn('id', $formatIds)->orderBy('id')->lockForUpdate()->get();
    }

    /** @param list<int> $requestedIds */
    private function assertKnownFormats(array $requestedIds, $formats): void
    {
        $knownIds = $formats->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (array_diff($requestedIds, $knownIds) !== []) {
            throw ValidationException::withMessages([
                'presentation_format_ids' => 'Khả năng trình chiếu đã chọn không tồn tại.',
            ]);
        }
    }
}
