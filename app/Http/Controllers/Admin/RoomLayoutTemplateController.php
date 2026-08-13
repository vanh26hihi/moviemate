<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoomLayoutTemplateRequest;
use App\Models\RoomLayoutTemplate;
use App\Models\RoomType;
use App\Services\ActivityLogger;
use App\Services\RoomLayoutTemplateGeometry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoomLayoutTemplateController extends Controller
{
    public function __construct(
        private readonly RoomLayoutTemplateGeometry $geometry,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): View
    {
        $templates = RoomLayoutTemplate::query()->withCount([
            'cells as seat_count' => fn ($query) => $query->where('cell_type', 'seat'),
            'roomLayouts',
        ])
            ->when(! $request->user()->hasPermission('layout_templates.manage'), fn ($query) => $query->active())
            ->orderByRaw("case status when 'active' then 0 when 'draft' then 1 else 2 end")
            ->orderBy('name')->paginate(15);

        $roomTypeNames = RoomType::query()->pluck('name', 'code');

        return view('admin.layout-templates.index', compact('templates', 'roomTypeNames'));
    }

    public function create(): View
    {
        return view('admin.layout-templates.create', [
            'template' => new RoomLayoutTemplate,
            'roomTypes' => RoomType::options(),
        ]);
    }

    public function store(SaveRoomLayoutTemplateRequest $request): RedirectResponse
    {
        $template = $this->persist(new RoomLayoutTemplate, $request->validated(), (int) $request->user()->id);
        $this->activityLogger->log('layout_template.created', $template, [], $template->only(['code', 'name', 'status']));

        return redirect()->route('admin.layout-templates.show', $template)->with('success', 'Đã tạo mẫu sơ đồ phòng.');
    }

    public function show(RoomLayoutTemplate $layoutTemplate): View
    {
        $layoutTemplate->load(['cells' => fn ($query) => $query->orderBy('y_position')->orderBy('x_position')]);
        $usages = $layoutTemplate->roomLayouts()->with('room.cinema')->latest()->paginate(10);

        $roomTypeName = $layoutTemplate->room_type
            ? RoomType::query()->where('code', $layoutTemplate->room_type)->value('name')
            : null;

        $seatCells = $layoutTemplate->cells->where('cell_type', 'seat');
        $normalSeats = $seatCells->where('seat_type', 'normal')->count();
        $vipSeats = $seatCells->where('seat_type', 'vip')->count();
        $couplePositions = $seatCells->where('seat_type', 'couple')->count();
        $couplePairs = $seatCells->where('seat_type', 'couple')->pluck('pair_key')->filter()->unique()->count();
        $aisleCells = $layoutTemplate->cells->where('cell_type', 'aisle')->count();
        $statistics = [
            'physical_seats' => $seatCells->count(),
            'pricing_units' => $normalSeats + $vipSeats + $couplePairs,
            'normal' => $normalSeats,
            'vip' => $vipSeats,
            'couple_pairs' => $couplePairs,
            'couple_positions' => $couplePositions,
            'aisles' => $aisleCells,
            'usages' => $usages->total(),
        ];

        return view('admin.layout-templates.show', compact('layoutTemplate', 'usages', 'roomTypeName', 'statistics'));
    }

    public function edit(RoomLayoutTemplate $layoutTemplate): View
    {
        $this->assertMutable($layoutTemplate);
        $layoutTemplate->load('cells');

        return view('admin.layout-templates.edit', [
            'template' => $layoutTemplate,
            'roomTypes' => RoomType::options($layoutTemplate->room_type),
        ]);
    }

    public function update(SaveRoomLayoutTemplateRequest $request, RoomLayoutTemplate $layoutTemplate): RedirectResponse
    {
        $this->assertMutable($layoutTemplate);
        $before = $layoutTemplate->only(['code', 'name', 'description', 'room_type']);
        $this->persist($layoutTemplate, $request->validated(), (int) $request->user()->id);
        $this->activityLogger->log('layout_template.updated', $layoutTemplate, $before, $layoutTemplate->only(array_keys($before)));

        return redirect()->route('admin.layout-templates.show', $layoutTemplate)->with('success', 'Đã cập nhật mẫu sơ đồ phòng.');
    }

    public function activate(RoomLayoutTemplate $layoutTemplate, Request $request): RedirectResponse
    {
        if ($layoutTemplate->status !== RoomLayoutTemplate::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Chỉ mẫu nháp mới có thể đưa vào sử dụng.']);
        }
        $layoutTemplate->update(['status' => RoomLayoutTemplate::STATUS_ACTIVE, 'updated_by_user_id' => $request->user()->id]);
        $this->activityLogger->log('layout_template.activated', $layoutTemplate, ['status' => 'draft'], ['status' => 'active']);

        return back()->with('success', 'Mẫu đã sẵn sàng để áp dụng cho phòng.');
    }

    public function archive(RoomLayoutTemplate $layoutTemplate, Request $request): RedirectResponse
    {
        if ($layoutTemplate->status === RoomLayoutTemplate::STATUS_ARCHIVED) {
            return back();
        }
        $before = $layoutTemplate->status;
        $layoutTemplate->update(['status' => RoomLayoutTemplate::STATUS_ARCHIVED, 'updated_by_user_id' => $request->user()->id]);
        $this->activityLogger->log('layout_template.archived', $layoutTemplate, ['status' => $before], ['status' => 'archived']);

        return back()->with('success', 'Đã lưu trữ mẫu. Các sơ đồ đã tạo từ mẫu vẫn được giữ nguyên.');
    }

    private function persist(RoomLayoutTemplate $template, array $validated, int $userId): RoomLayoutTemplate
    {
        $layout = $this->geometry->normalize($validated['layout']);

        return DB::transaction(function () use ($template, $validated, $layout, $userId): RoomLayoutTemplate {
            $template->fill([
                'code' => strtoupper($validated['code']), 'name' => $validated['name'],
                'description' => $validated['description'] ?? null, 'room_type' => $validated['room_type'] ?? null,
                'rows' => $layout['rows'], 'columns' => $layout['columns'], 'screen_position' => $layout['screen_position'],
                'updated_by_user_id' => $userId,
            ]);
            if (! $template->exists) {
                $template->status = RoomLayoutTemplate::STATUS_DRAFT;
                $template->created_by_user_id = $userId;
            }
            $template->save();
            $template->cells()->delete();
            $now = now();
            $template->cells()->insert(array_map(fn (array $cell): array => array_merge($cell, [
                'room_layout_template_id' => $template->id, 'metadata' => $cell['metadata'] ? json_encode($cell['metadata']) : null,
                'created_at' => $now, 'updated_at' => $now,
            ]), $layout['cells']));

            return $template->fresh('cells');
        });
    }

    private function assertMutable(RoomLayoutTemplate $template): void
    {
        if ($template->status === RoomLayoutTemplate::STATUS_ARCHIVED) {
            throw ValidationException::withMessages(['template' => 'Mẫu đã lưu trữ chỉ có thể xem.']);
        }
    }
}
