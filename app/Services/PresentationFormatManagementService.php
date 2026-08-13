<?php

namespace App\Services;

use App\Models\PresentationFormat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PresentationFormatManagementService
{
    public function __construct(
        private readonly FutureActiveShowtimeDependency $dependencies,
        private readonly ActivityLogger $activity,
    ) {}

    /** @param array{code:string,name:string,description?:?string,sort_order?:?int} $data */
    public function create(array $data, User $actor): PresentationFormat
    {
        return DB::transaction(function () use ($data, $actor): PresentationFormat {
            $format = PresentationFormat::query()->create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => true,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
            $this->activity->log('presentation_format.created', $format, [], $this->auditData($format));

            return $format;
        });
    }

    /** @param array{code:string,name:string,description?:?string,sort_order?:?int} $data */
    public function update(PresentationFormat $format, array $data, User $actor): PresentationFormat
    {
        return DB::transaction(function () use ($format, $data, $actor): PresentationFormat {
            $locked = PresentationFormat::query()->whereKey($format->id)->lockForUpdate()->firstOrFail();
            if ($data['code'] !== $locked->code && $this->isReferenced($locked)) {
                throw ValidationException::withMessages([
                    'code' => 'Không thể đổi mã định dạng đã được phim, phòng hoặc suất chiếu sử dụng. Bạn vẫn có thể cập nhật tên và mô tả.',
                ]);
            }

            $before = $this->auditData($locked);
            $locked->update([
                ...$data,
                'sort_order' => $data['sort_order'] ?? 0,
                'updated_by_user_id' => $actor->id,
            ]);
            $this->activity->log('presentation_format.updated', $locked, $before, $this->auditData($locked));

            return $locked->fresh();
        });
    }

    public function archive(PresentationFormat $format, User $actor): PresentationFormat
    {
        return DB::transaction(function () use ($format, $actor): PresentationFormat {
            $locked = PresentationFormat::query()->whereKey($format->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_active) {
                return $locked;
            }

            if ($this->dependencies->conflictingFormatIdForArchive([(int) $locked->id], lock: true) !== null) {
                throw ValidationException::withMessages([
                    'format' => "Không thể lưu trữ định dạng {$locked->name} vì còn suất chiếu tương lai đang sử dụng.",
                ]);
            }

            $before = $this->auditData($locked);
            $locked->update(['is_active' => false, 'updated_by_user_id' => $actor->id]);
            $this->activity->log('presentation_format.archived', $locked, $before, $this->auditData($locked));

            return $locked->fresh();
        });
    }

    private function isReferenced(PresentationFormat $format): bool
    {
        return $format->showtimes()->exists()
            || $format->movies()->exists()
            || $format->rooms()->exists();
    }

    /** @return array<string, int|string> */
    private function auditData(PresentationFormat $format): array
    {
        return [
            'id' => (int) $format->id,
            'code' => $format->code,
            'name' => $format->name,
            'status' => $format->is_active ? 'active' : 'archived',
            'sort_order' => (int) $format->sort_order,
        ];
    }
}
