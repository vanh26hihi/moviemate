<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoomTypeRequest;
use App\Models\RoomType;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class RoomTypeController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? '';
        $roomTypes = RoomType::query()->withCount(['rooms', 'pricingRules'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'archived', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.room-types.index', compact('roomTypes', 'search', 'status'));
    }

    public function create(): View
    {
        $roomType = new RoomType;
        $roomType->setAttribute('rooms_count', 0);
        $roomType->setAttribute('pricing_rules_count', 0);

        return view('admin.room-types.form', compact('roomType'));
    }

    public function store(SaveRoomTypeRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $roomType = DB::transaction(function () use ($request, $data): RoomType {
            $roomType = RoomType::query()->create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? 100,
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $this->activity->log('room_type.created', $roomType, after: $this->auditData($roomType));

            return $roomType;
        });

        if ($request->expectsJson()) {
            return response()->json(['room_type' => [
                'id' => $roomType->id,
                'code' => $roomType->code,
                'name' => $roomType->name,
            ]], 201);
        }

        return redirect()->route('admin.room-types.index')->with('success', 'Đã thêm loại phòng.');
    }

    public function edit(RoomType $roomType): View
    {
        $roomType->loadCount(['rooms', 'pricingRules']);

        return view('admin.room-types.form', compact('roomType'));
    }

    public function update(SaveRoomTypeRequest $request, RoomType $roomType): RedirectResponse
    {
        $data = $request->validated();
        if ($data['code'] !== $roomType->code
            && $roomType->rooms()->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Không thể đổi mã loại phòng đã được sử dụng. Bạn có thể cập nhật tên hiển thị hoặc ngừng sử dụng loại phòng này.',
            ]);
        }

        $before = $this->auditData($roomType);
        DB::transaction(function () use ($request, $roomType, $data, $before): void {
            $roomType->update([...$data, 'updated_by_user_id' => $request->user()->id]);
            $this->activity->log('room_type.updated', $roomType, $before, $this->auditData($roomType));
        });

        return redirect()->route('admin.room-types.index')->with('success', 'Đã cập nhật loại phòng.');
    }

    public function status(Request $request, RoomType $roomType): RedirectResponse
    {
        $active = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];
        $active = filter_var($active, FILTER_VALIDATE_BOOL);
        if ($roomType->is_active === $active) {
            return back();
        }

        $before = $this->auditData($roomType);
        $roomType->update(['is_active' => $active, 'updated_by_user_id' => $request->user()->id]);
        $this->activity->log('room_type.'.($active ? 'activated' : 'archived'), $roomType, $before, $this->auditData($roomType));

        return back()->with('success', $active ? 'Đã kích hoạt loại phòng.' : 'Đã ngừng sử dụng loại phòng. Dữ liệu lịch sử được giữ nguyên.');
    }

    private function auditData(RoomType $roomType): array
    {
        return [
            'id' => $roomType->id,
            'code' => $roomType->code,
            'name' => $roomType->name,
            'status' => $roomType->is_active ? 'active' : 'inactive',
        ];
    }
}
