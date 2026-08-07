@extends('layouts.admin')
@section('title', 'Thiết kế sơ đồ ghế - MovieMate')
@section('page-title', 'Thiết kế sơ đồ ghế')
@section('suppress-global-validation-summary', '1')

@section('content')
@php
    $isDraft = $layout?->status === 'draft';
    $oldLayout = old('layout');
    if (is_string($oldLayout)) {
        $oldLayout = json_decode($oldLayout, true);
    }
    $oldLayout = is_array($oldLayout) ? $oldLayout : [];
    $layoutRows = (int) ($oldLayout['rows'] ?? $layout?->rows ?? 10);
    $layoutColumns = (int) ($oldLayout['columns'] ?? $layout?->columns ?? 12);
    $layoutScreenPosition = (string) ($oldLayout['screen_position'] ?? $layout?->screen_position ?? 'top');
    $layoutName = (string) ($oldLayout['name'] ?? $layout?->display_name ?? '');
    $toolDefinitions = [
        'normal' => ['label' => 'Ghế thường', 'icon' => 'ph-armchair'],
        'vip' => ['label' => 'Ghế VIP', 'icon' => 'ph-crown'],
        'couple' => ['label' => 'Ghế đôi', 'icon' => 'ph-heart'],
        'aisle' => ['label' => 'Lối đi', 'icon' => 'ph-arrows-down-up'],
        'empty' => ['label' => 'Ô trống', 'icon' => 'ph-eraser'],
        'maintenance' => ['label' => 'Bảo trì', 'icon' => 'ph-wrench'],
        'inactive' => ['label' => 'Không sử dụng', 'icon' => 'ph-prohibit'],
    ];

    if (isset($oldLayout['cells']) && is_array($oldLayout['cells'])) {
        $initialCells = collect($oldLayout['cells'])->map(fn ($cell) => [
            'x' => (int) ($cell['x'] ?? $cell['x_position'] ?? 0),
            'y' => (int) ($cell['y'] ?? $cell['y_position'] ?? 0),
            'cell_type' => ($cell['kind'] ?? $cell['cell_type'] ?? null) === 'aisle' ? 'aisle' : 'seat',
            'seat_id' => isset($cell['seat_id']) ? (int) $cell['seat_id'] : null,
            'type' => $cell['type'] ?? $cell['kind'] ?? null,
            'status' => $cell['status'] ?? 'active',
            'seat_code' => $cell['seat_code'] ?? null,
            'pair_code' => $cell['pair_code'] ?? null,
            'pair_position' => $cell['pair_position'] ?? null,
            'has_bookings' => (bool) ($cell['has_bookings'] ?? false),
        ])->values();
    } else {
        $initialCells = $layout?->cells->map(function ($cell) {
            if ($cell->cell_type === 'aisle') {
                return ['x' => $cell->x_position, 'y' => $cell->y_position, 'cell_type' => 'aisle'];
            }

            return [
                'x' => $cell->x_position,
                'y' => $cell->y_position,
                'cell_type' => 'seat',
                'seat_id' => $cell->seat_id,
                'type' => $cell->seat->type,
                'status' => $cell->seat->status,
                'seat_code' => $cell->seat->seat_code,
                'pair_code' => $cell->seat->pair_code,
                'pair_position' => $cell->seat->pair_position,
                'has_bookings' => (int) ($cell->seat->booking_seats_count ?? 0) > 0,
            ];
        })->values() ?? collect();
    }
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm font-extrabold uppercase tracking-[0.2em] text-brand-start">{{ $room->code }} — {{ $room->name }}</p>
            <h1 class="mt-2 text-3xl font-extrabold app-text">Sơ đồ ghế theo phiên bản</h1>
            <p class="mt-2 app-muted">Sơ đồ đã phát hành không thể chỉnh sửa. Mọi thay đổi luôn được thực hiện trên một bản nháp riêng.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($layout)
                <a class="btn-secondary" href="{{ route('admin.rooms.layout.preview', ['room' => $room, 'version' => $layout->version]) }}">
                    <i class="ph ph-eye" aria-hidden="true"></i>Xem trước {{ $layout->display_name }}
                </a>
            @endif
            <a class="btn-secondary" href="{{ route('admin.rooms.index') }}"><i class="ph ph-list-bullets" aria-hidden="true"></i>Danh sách phòng</a>
        </div>
    </div>

    @if($errors->any())
        <x-validation-summary id="layoutServerErrors" :errors="$errors" heading="Không thể hoàn tất thao tác với sơ đồ ghế." />
    @endif

    @if(!$layout || !$isDraft)
        <div class="cinema-card p-6">
            <h2 class="text-xl font-bold app-text">{{ $layout ? $layout->display_name : 'Phòng chưa có sơ đồ ghế' }}</h2>
            <p class="mt-2 app-muted">{{ $layout ? 'Sao chép sơ đồ này thành bản nháp mới trước khi chỉnh sửa.' : 'Chọn kích thước lưới ban đầu. Các ô trống sẽ không được lưu.' }}</p>
            <form method="POST" action="{{ route('admin.rooms.layout.draft', $room) }}" class="mt-5 flex flex-wrap items-end gap-4">
                @csrf
                <label class="cinema-label">Tên sơ đồ mới<input class="cinema-input mt-1" name="name" required minlength="5" value="{{ old('name', $layout ? 'Điều chỉnh từ '.$layout->display_name : 'Sơ đồ phòng '.$room->code) }}"></label>
                @unless($layout)
                    <label class="cinema-label">Số hàng ghế <input class="cinema-input mt-1 w-28" type="number" name="rows" min="1" max="30" value="{{ old('rows', 10) }}"></label>
                    <label class="cinema-label">Chiều rộng vùng thiết kế <input class="cinema-input mt-1 w-28" type="number" name="columns" min="1" max="40" value="{{ old('columns', 12) }}"><span class="mt-1 block text-xs font-normal app-muted">Đây là chiều rộng vùng bố trí. Mỗi hàng có thể sử dụng số ô khác nhau.</span></label>
                    <label class="cinema-label">Vị trí màn hình <select class="cinema-input mt-1" name="screen_position"><option value="top">Phía trên</option><option value="bottom">Phía dưới</option></select></label>
                @endunless
                <button class="btn-primary"><i class="ph ph-copy" aria-hidden="true"></i>{{ $layout ? 'Sao chép thành bản nháp phiên bản '.($layout->version + 1) : 'Tạo bản nháp trống' }}</button>
            </form>
        </div>
    @endif

    @if($layout)
        <div class="cinema-card p-5 sm:p-6">
            <div class="grid gap-4 md:grid-cols-4">
                <label class="cinema-label">Tên sơ đồ<input id="layoutName" class="cinema-input mt-1" value="{{ $layoutName }}" @disabled(!$isDraft)></label>
                <label class="cinema-label">Số hàng ghế<input id="layoutRows" type="number" min="1" max="30" class="cinema-input mt-1" value="{{ $layoutRows }}" @disabled(!$isDraft)></label>
                <label class="cinema-label">Chiều rộng vùng thiết kế<input id="layoutColumns" type="number" min="1" max="40" class="cinema-input mt-1" value="{{ $layoutColumns }}" @disabled(!$isDraft)><span class="mt-1 block text-xs font-normal app-muted">Đây là chiều rộng vùng bố trí. Mỗi hàng có thể sử dụng số ô khác nhau.</span></label>
                <label class="cinema-label">Màn hình<select id="screenPosition" class="cinema-input mt-1" @disabled(!$isDraft)><option value="top" @selected($layoutScreenPosition === 'top')>Phía trên</option><option value="bottom" @selected($layoutScreenPosition === 'bottom')>Phía dưới</option></select></label>
            </div>

            @if($isDraft)
                <div class="mt-5 flex flex-wrap items-center gap-2" role="toolbar" aria-label="Điều chỉnh chiều rộng vùng thiết kế">
                    <span class="mr-1 text-xs font-extrabold uppercase tracking-wider app-muted">Vùng thiết kế</span>
                    <button type="button" data-canvas-action="expand-left" data-amount="1" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Mở rộng bên trái thêm 1 cột"><i class="ph ph-arrow-line-left" aria-hidden="true"></i>Mở rộng bên trái +1</button>
                    <button type="button" data-canvas-action="expand-left" data-amount="2" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Mở rộng bên trái thêm 2 cột">+2 trái</button>
                    <button type="button" data-canvas-action="expand-right" data-amount="1" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Mở rộng bên phải thêm 1 cột"><i class="ph ph-arrow-line-right" aria-hidden="true"></i>Mở rộng phải +1</button>
                    <button type="button" data-canvas-action="expand-right" data-amount="2" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Mở rộng bên phải thêm 2 cột">+2 phải</button>
                    <button type="button" data-canvas-action="shrink-left" data-amount="1" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Thu hẹp bên trái 1 cột">Thu hẹp trái</button>
                    <button type="button" data-canvas-action="shrink-right" data-amount="1" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Thu hẹp bên phải 1 cột">Thu hẹp phải</button>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2" role="toolbar" aria-label="Chế độ chỉnh sửa sơ đồ">
                    <span class="mr-1 text-xs font-extrabold uppercase tracking-wider app-muted">Chế độ</span>
                    @foreach(['paint' => ['Chỉnh loại ô', 'ph-paint-brush'], 'expand' => ['Mở rộng hàng', 'ph-arrows-out-line-horizontal'], 'move' => ['Di chuyển hàng', 'ph-arrows-left-right'], 'couple' => ['Tạo ghế đôi', 'ph-heart']] as $mode => [$label, $icon])
                        <button type="button" data-editor-mode="{{ $mode }}" class="btn-secondary !px-3 !py-2 text-xs {{ $mode === 'paint' ? '!border-brand-start !text-brand-start' : '' }}" aria-pressed="{{ $mode === 'paint' ? 'true' : 'false' }}" aria-label="Chế độ {{ $label }}"><i class="ph {{ $icon }}" aria-hidden="true"></i>{{ $label }}</button>
                    @endforeach
                    <button type="button" id="undoLayoutAction" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Hoàn tác thao tác gần nhất" disabled><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i>Hoàn tác thao tác gần nhất</button>
                </div>
                <div id="layoutTools" class="mt-5 flex flex-wrap gap-2" role="toolbar" aria-label="Công cụ thiết kế sơ đồ ghế">
                    @foreach($toolDefinitions as $tool => $definition)
                        <button type="button" data-tool="{{ $tool }}" class="btn-secondary !px-3 !py-2 text-xs {{ $tool === 'normal' ? '!border-brand-start !text-brand-start' : '' }}" title="{{ $definition['label'] }}" aria-pressed="{{ $tool === 'normal' ? 'true' : 'false' }}">
                            <i class="ph {{ $definition['icon'] }}" aria-hidden="true"></i>{{ $definition['label'] }}
                        </button>
                    @endforeach
                </div>

                <div id="rowControls" class="mt-4 hidden rounded-2xl border app-border p-4" aria-live="polite">
                    <p class="font-extrabold app-text">Hàng <span id="selectedRowLabel">—</span></p>
                    <div class="mt-3 flex flex-wrap gap-2" role="toolbar" aria-label="Điều chỉnh hàng đang chọn">
                        <button type="button" data-row-action="add-left" data-amount="1" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Thêm 1 ô bên trái hàng đã chọn">Thêm 1 ô bên trái</button>
                        <button type="button" data-row-action="add-left" data-amount="2" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Thêm 2 ô bên trái hàng đã chọn">Thêm 2 ô bên trái</button>
                        <button type="button" data-row-action="add-right" data-amount="1" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Thêm 1 ô bên phải hàng đã chọn">Thêm 1 ô bên phải</button>
                        <button type="button" data-row-action="add-right" data-amount="2" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Thêm 2 ô bên phải hàng đã chọn">Thêm 2 ô bên phải</button>
                        <button type="button" data-row-action="move-left" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Dịch hàng đã chọn sang trái">Dịch hàng sang trái</button>
                        <button type="button" data-row-action="move-right" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Dịch hàng đã chọn sang phải">Dịch hàng sang phải</button>
                        <button type="button" data-row-action="center" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Căn giữa hàng đã chọn">Căn giữa hàng</button>
                        <button type="button" data-row-action="trim" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Xóa các ô trống ngoài cùng">Xóa ô trống ngoài cùng</button>
                        <button type="button" data-row-action="add-row-after" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Thêm hàng phía sau hàng đã chọn">Thêm hàng phía sau</button>
                        <button type="button" data-row-action="add-row-before" class="btn-secondary !px-3 !py-2 text-xs" aria-label="Thêm hàng phía trước hàng đã chọn">Thêm hàng phía trước</button>
                    </div>
                </div>
            @endif

            <div id="layoutEditorAlert" class="mt-5 hidden items-start gap-3 rounded-2xl border border-error/40 bg-error/10 px-4 py-3 text-sm text-error" role="alert" aria-live="assertive" tabindex="-1">
                <i class="ph-fill ph-warning-octagon mt-0.5 text-xl" aria-hidden="true"></i>
                <div><p class="font-extrabold">Chưa thể áp dụng thay đổi.</p><p id="layoutEditorAlertMessage" class="mt-1"></p></div>
            </div>
            <p id="layoutCellError" class="sr-only">Ô này có lỗi. Hãy đọc thông báo lỗi phía trên và sửa ô được đánh dấu.</p>

            <div id="selectedPositionPanel" class="mt-4 grid gap-2 rounded-2xl border app-border p-4 text-sm sm:grid-cols-3 lg:grid-cols-6">
                <span><strong>Hàng:</strong> <span data-position="row">—</span></span>
                <span><strong>Tọa độ:</strong> <span data-position="coordinate">—</span></span>
                <span><strong>Loại ô:</strong> <span data-position="kind">—</span></span>
                <span><strong>Mã ghế:</strong> <span data-position="code">—</span></span>
                <span><strong>Cặp ghế:</strong> <span data-position="pair">—</span></span>
                <span><strong>Lịch sử:</strong> <span data-position="history">—</span></span>
                @if($isDraft)<button type="button" id="splitCoupleButton" class="btn-secondary hidden !px-3 !py-2 text-xs sm:col-span-3 lg:col-span-6" aria-label="Tách ghế đôi đang chọn">Tách ghế đôi</button>@endif
                <p id="newLeftSeatHint" class="hidden text-xs text-brand-start sm:col-span-3 lg:col-span-6">Ghế mới dùng mã tiếp theo để giữ nguyên mã của các ghế hiện có.</p>
            </div>

            <div class="mt-6 overflow-x-auto overscroll-x-contain pb-4" tabindex="0" aria-label="Sơ đồ ghế, có thể cuộn ngang trên màn hình nhỏ">
                <div id="screenTop" class="mx-auto mb-2 min-w-[30rem] text-center {{ $layoutScreenPosition === 'bottom' ? 'hidden' : '' }}">
                    <i class="ph ph-monitor text-xl text-brand-start" aria-hidden="true"></i><p class="text-[0.65rem] font-bold uppercase tracking-[0.3em] app-muted">Màn hình</p>
                </div>
                <div id="layoutGrid" class="mx-auto grid w-max gap-1.5" aria-label="Lưới thiết kế sơ đồ ghế" aria-describedby="layoutGridHelp layoutCellError"></div>
                <div id="screenBottom" class="mx-auto mt-2 min-w-[30rem] text-center {{ $layoutScreenPosition === 'top' ? 'hidden' : '' }}">
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.3em] app-muted">Màn hình</p><i class="ph ph-monitor text-xl text-brand-start" aria-hidden="true"></i>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t app-border pt-5">
                <p id="layoutGridHelp" class="text-sm app-muted"><span id="seatCount">0</span> vị trí ghế · {{ $layout->display_name }} · Công cụ ghế đôi ghép ô hiện tại với ô liền bên phải.</p>
                @if($isDraft)
                    <div class="flex flex-wrap gap-2">
                        <form id="saveLayoutForm" method="POST" action="{{ route('admin.rooms.layout.update', $room) }}">@csrf @method('PATCH')<input type="hidden" name="layout" id="layoutPayload"><button class="btn-secondary"><i class="ph ph-floppy-disk" aria-hidden="true"></i>Lưu bản nháp</button></form>
                        <form id="publishLayoutForm" method="POST" action="{{ route('admin.rooms.layout.publish', $room) }}">@csrf<button class="btn-primary"><i class="ph ph-upload-simple" aria-hidden="true"></i>Phát hành {{ $layout->display_name }}</button></form>
                    </div>
                @endif
            </div>
            @if($layoutSummary)
                <div class="mt-5 grid grid-cols-2 gap-3 border-t app-border pt-5 text-sm sm:grid-cols-4 lg:grid-cols-6" aria-label="Tóm tắt sơ đồ do máy chủ tính toán">
                    @foreach(['rows' => 'Số hàng', 'columns' => 'Chiều rộng', 'used' => 'Vị trí đã dùng', 'empty' => 'Vị trí trống', 'normal' => 'Ghế thường', 'vip' => 'Ghế VIP', 'couple_pairs' => 'Cặp ghế đôi', 'aisles' => 'Lối đi', 'maintenance' => 'Bảo trì', 'inactive' => 'Không sử dụng', 'capacity' => 'Sức chứa'] as $key => $label)
                        <div><p class="app-muted">{{ $label }}</p><p class="font-extrabold app-text">{{ $layoutSummary[$key] }}</p></div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>

@if($layout)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const editable = @json($isDraft);
    const grid = document.getElementById('layoutGrid');
    const rowsInput = document.getElementById('layoutRows');
    const columnsInput = document.getElementById('layoutColumns');
    const screenInput = document.getElementById('screenPosition');
    const initialCells = {{ Illuminate\Support\Js::from($initialCells) }};
    const roomSeatCodes = {{ Illuminate\Support\Js::from($roomSeatCodes) }};
    const expectedUpdatedAt = @json($layout->updated_at?->format('Y-m-d H:i:s.u'));
    const serverErrorKeys = {{ Illuminate\Support\Js::from(array_keys($errors->getMessages())) }};
    const typeLabels = @json(\App\Support\StatusLabel::options('seat_type'));
    const statusLabels = @json(\App\Support\StatusLabel::options('seat'));
    const cells = new Map(initialCells.filter(cell => cell.x > 0 && cell.y > 0).map(cell => [`${cell.x}:${cell.y}`, cell]));
    const serverCellErrors = new Map();
    const clientCellErrors = new Map();
    let activeTool = 'normal';
    let editorMode = 'paint';
    let selectedRow = null;
    let selectedCoordinate = null;
    let lastRows = Number(rowsInput.value);
    let lastColumns = Number(columnsInput.value);
    const undoStack = [];
    let dirty = false;

    serverErrorKeys.forEach(key => {
        const match = key.match(/(?:layout\.)?cells\.(\d+)/);
        const cell = match ? initialCells[Number(match[1])] : null;
        if (cell) serverCellErrors.set(`${cell.x}:${cell.y}`, true);
    });

    const rowLabel = index => index <= 26 ? String.fromCharCode(64 + index) : `A${String.fromCharCode(64 + index - 26)}`;
    const visualClasses = {
        normal: 'app-input app-muted',
        vip: 'bg-ai-start/10 text-ai-start border-ai-start/50',
        couple: 'bg-warning/10 text-warning border-warning/50',
        aisle: 'border-dashed app-muted opacity-60',
        maintenance: 'bg-gray-300 text-gray-600 border-gray-400',
        inactive: 'bg-dark-border/30 app-muted',
    };

    function pairMembers(cell) {
        if (!cell || cell.cell_type !== 'seat' || cell.type !== 'couple' || !cell.pair_code) return [];
        return Array.from(cells.values()).filter(candidate => (
            candidate.cell_type === 'seat'
            && candidate.type === 'couple'
            && candidate.pair_code === cell.pair_code
        ));
    }

    function validPair(cell) {
        const members = pairMembers(cell).sort((a, b) => a.x - b.x);
        if (members.length !== 2) return null;
        const [left, right] = members;
        const positions = members.map(member => member.pair_position).sort().join(',');
        if (left.y !== right.y || right.x - left.x !== 1 || positions !== 'left,right') return null;
        return members;
    }

    function normalizeSeatMetadata() {
        Array.from(cells.values()).filter(cell => cell.cell_type === 'seat').forEach(cell => {
            if (!cell.row || !cell.number || !cell.seat_code) {
                const metadata = nextSeatMetadata(cell.y);
                cell.row = metadata.row;
                cell.number = metadata.number;
                cell.seat_code = metadata.seat_code;
            }
            if (cell.type !== 'couple') {
                cell.pair_code = null;
                cell.pair_position = null;
            }
        });
    }

    function nextSeatMetadata(y, reservedCodes = []) {
        const row = rowLabel(y);
        const used = new Set([
            ...roomSeatCodes,
            ...Array.from(cells.values()).filter(cell => cell.cell_type === 'seat').map(cell => cell.seat_code),
            ...reservedCodes,
        ]);
        let number = 1;
        while (used.has(`${row}${number}`) && number <= 99) number++;
        return {row, number, seat_code: `${row}${number}`};
    }

    function createSeat(x, y, type = 'normal', status = 'active') {
        return {x, y, cell_type: 'seat', type, status, seat_id: null, has_bookings: false, ...nextSeatMetadata(y)};
    }

    function snapshot() {
        return {
            rows: Number(rowsInput.value),
            columns: Number(columnsInput.value),
            cells: Array.from(cells.values()).map(cell => ({...cell})),
            selectedRow,
            selectedCoordinate,
        };
    }

    function remember() {
        undoStack.push(snapshot());
        if (undoStack.length > 50) undoStack.shift();
        document.getElementById('undoLayoutAction').disabled = false;
    }

    function restoreSnapshot(state) {
        rowsInput.value = state.rows;
        columnsInput.value = state.columns;
        lastRows = state.rows;
        lastColumns = state.columns;
        cells.clear();
        state.cells.forEach(cell => cells.set(`${cell.x}:${cell.y}`, {...cell}));
        selectedRow = state.selectedRow;
        selectedCoordinate = state.selectedCoordinate;
        dirty = true;
        render();
    }

    function clearCoordinateErrors(coordinates) {
        coordinates.forEach(coordinate => {
            serverCellErrors.delete(coordinate);
            clientCellErrors.delete(coordinate);
        });
    }

    function showEditorError(message, coordinates = []) {
        coordinates.forEach(coordinate => clientCellErrors.set(coordinate, message));
        const alert = document.getElementById('layoutEditorAlert');
        document.getElementById('layoutEditorAlertMessage').textContent = message;
        alert.classList.remove('hidden');
        alert.classList.add('flex');
        render();
        alert.focus({preventScroll: true});
    }

    function hideEditorError() {
        const alert = document.getElementById('layoutEditorAlert');
        alert.classList.add('hidden');
        alert.classList.remove('flex');
    }

    function render() {
        const rows = Math.max(1, Math.min(30, Number(rowsInput.value) || 1));
        const columns = Math.max(1, Math.min(40, Number(columnsInput.value) || 1));
        rowsInput.value = rows;
        columnsInput.value = columns;
        normalizeSeatMetadata();
        grid.style.gridTemplateColumns = `2.75rem repeat(${columns}, 2.35rem)`;
        grid.replaceChildren();
        const skipped = new Set();

        for (let y = 1; y <= rows; y++) {
            const rowButton = document.createElement('button');
            rowButton.type = 'button';
            rowButton.textContent = rowLabel(y);
            rowButton.className = `h-9 rounded-lg border text-xs font-extrabold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-start ${selectedRow === y ? 'border-brand-start bg-brand-start/10 text-brand-start' : 'app-border app-muted'}`;
            rowButton.setAttribute('aria-label', `Chọn hàng ${rowLabel(y)}`);
            rowButton.setAttribute('aria-pressed', String(selectedRow === y));
            if (editable) rowButton.addEventListener('click', () => selectRow(y));
            else rowButton.disabled = true;
            grid.appendChild(rowButton);
            for (let x = 1; x <= columns; x++) {
                const key = `${x}:${y}`;
                if (skipped.has(key)) continue;
                const cell = cells.get(key);
                const pair = cell?.type === 'couple' ? validPair(cell) : null;
                const isMergedPair = pair && pair[0].x === x;
                if (isMergedPair) skipped.add(`${pair[1].x}:${pair[1].y}`);
                const coordinates = isMergedPair ? pair.map(member => `${member.x}:${member.y}`) : [key];
                const error = coordinates.map(coordinate => clientCellErrors.get(coordinate) || serverCellErrors.get(coordinate)).find(Boolean);
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.coordinate = key;
                button.className = `relative h-9 rounded-lg border px-1 text-[9px] font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-start ${isMergedPair ? 'col-span-2 min-w-[5.1rem]' : 'w-9'}`;
                if (selectedRow === y) button.className += ' ring-1 ring-brand-start/40';
                if (selectedCoordinate === key || (isMergedPair && pair.some(member => `${member.x}:${member.y}` === selectedCoordinate))) button.className += ' !ring-2 !ring-ai-start';

                if (!cell) {
                    button.className += ' border-dashed app-border opacity-40';
                    button.innerHTML = '<i class="ph ph-plus" aria-hidden="true"></i>';
                    button.setAttribute('aria-label', `Ô trống hàng ${rowLabel(y)}, cột ${x}`);
                } else if (cell.cell_type === 'aisle') {
                    button.className += ` ${visualClasses.aisle}`;
                    button.innerHTML = '<i class="ph ph-arrows-down-up" aria-hidden="true"></i>';
                    button.setAttribute('aria-label', `Lối đi hàng ${rowLabel(y)}, cột ${x}`);
                } else if (isMergedPair) {
                    const displayCode = pair.map(member => member.seat_code).join('–');
                    const consistentStatus = new Set(pair.map(member => member.status)).size === 1;
                    button.className += ` ${consistentStatus ? visualClasses[pair[0].status !== 'active' ? pair[0].status : 'couple'] : 'border-error/70 bg-error/10 text-error'}`;
                    button.textContent = displayCode;
                    button.title = `Ghế đôi ${displayCode} · ${consistentStatus ? (statusLabels[pair[0].status] || 'Chưa xác định') : 'Trạng thái không đồng nhất'}`;
                    button.setAttribute('aria-label', button.title);
                } else {
                    const invalidCouple = cell.type === 'couple';
                    button.className += ` ${invalidCouple ? 'border-error/70 bg-error/10 text-error' : (visualClasses[cell.status !== 'active' ? cell.status : cell.type] || visualClasses.normal)}`;
                    button.textContent = cell.seat_code;
                    button.title = invalidCouple
                        ? `${cell.seat_code} · Ghế đôi chưa đủ cặp, không thể phát hành`
                        : `${cell.seat_code} · ${typeLabels[cell.type] || 'Chưa xác định'} · ${statusLabels[cell.status] || 'Chưa xác định'}`;
                    button.setAttribute('aria-label', button.title);
                }

                if (error || (cell?.type === 'couple' && !pair)) {
                    button.classList.add('!border-error', 'ring-2', 'ring-error/40');
                    button.setAttribute('aria-invalid', 'true');
                    button.setAttribute('aria-describedby', 'layoutCellError');
                    const badge = document.createElement('span');
                    badge.className = 'absolute -right-1.5 -top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-error text-[10px] text-white';
                    badge.textContent = '!';
                    badge.setAttribute('aria-hidden', 'true');
                    button.appendChild(badge);
                }
                if (editable) button.addEventListener('click', () => handleCoordinateClick(x, y));
                else button.disabled = true;
                grid.appendChild(button);
            }
        }

        document.getElementById('seatCount').textContent = Array.from(cells.values()).filter(cell => cell.cell_type === 'seat').length;
        document.getElementById('screenTop').classList.toggle('hidden', screenInput.value !== 'top');
        document.getElementById('screenBottom').classList.toggle('hidden', screenInput.value !== 'bottom');
        renderSelectionPanel();
    }

    function selectRow(y) {
        selectedRow = y;
        document.getElementById('selectedRowLabel').textContent = rowLabel(y);
        document.getElementById('rowControls').classList.remove('hidden');
        render();
    }

    function handleCoordinateClick(x, y) {
        selectedCoordinate = `${x}:${y}`;
        if (editorMode === 'expand' || editorMode === 'move') {
            selectRow(y);
            return;
        }
        if (editorMode === 'couple') activeTool = 'couple';
        applyTool(x, y);
    }

    function renderSelectionPanel() {
        const [x, y] = selectedCoordinate ? selectedCoordinate.split(':').map(Number) : [null, null];
        const cell = selectedCoordinate ? cells.get(selectedCoordinate) : null;
        const set = (key, value) => {
            const target = document.querySelector(`[data-position="${key}"]`);
            if (target) target.textContent = value;
        };
        set('row', y ? rowLabel(y) : '—');
        set('coordinate', x && y ? `x=${x}, y=${y}` : '—');
        set('kind', cell ? (cell.cell_type === 'aisle' ? 'Lối đi' : (typeLabels[cell.type] || statusLabels[cell.status] || 'Ghế')) : (x ? 'Ô trống' : '—'));
        set('code', cell?.seat_code || '—');
        set('pair', cell?.pair_code || '—');
        set('history', cell?.has_bookings ? 'Đã có đặt vé' : (cell ? 'Chưa có đặt vé' : '—'));
        const split = document.getElementById('splitCoupleButton');
        split?.classList.toggle('hidden', !validPair(cell));
        const rowCells = y ? Array.from(cells.values()).filter(candidate => candidate.y === y && candidate.cell_type === 'seat') : [];
        const isNewLeftSeat = cell?.cell_type === 'seat' && !cell.seat_id && rowCells.some(candidate => candidate.seat_id && candidate.x > cell.x);
        document.getElementById('newLeftSeatHint')?.classList.toggle('hidden', !isNewLeftSeat);
    }

    function applyTool(x, y) {
        if (!editable) return;
        hideEditorError();
        const key = `${x}:${y}`;
        const existing = cells.get(key);
        const existingPair = validPair(existing);
        const touched = existingPair ? existingPair.map(member => `${member.x}:${member.y}`) : [key];
        clearCoordinateErrors(touched);

        if (activeTool === 'couple') {
            if (existing?.type === 'couple') return;
            const rightKey = `${x + 1}:${y}`;
            const right = cells.get(rightKey);
            if (x >= Number(columnsInput.value)) {
                showEditorError('Ghế đôi cần một ô liền bên phải trong cùng hàng.', [key]);
                return;
            }
            if (existing?.type === 'couple' || right?.type === 'couple') {
                showEditorError('Không thể di chuyển riêng một nửa ghế đôi. Hãy dùng thao tác “Tách ghế đôi” trước.', [key, rightKey]);
                return;
            }
            if (existing?.cell_type === 'aisle' || right?.cell_type === 'aisle') {
                showEditorError('Ghế đôi không thể chồng lên hoặc băng qua lối đi. Hãy thay đổi lối đi bằng một thao tác cấu trúc riêng trước.', [key, rightKey]);
                return;
            }
            if (existing?.has_bookings || right?.has_bookings) {
                showEditorError('Không thể đổi cấu trúc ghế đã có lịch sử đặt vé.', [key, rightKey]);
                return;
            }
            const pairCode = `${rowLabel(y)}-PAIR-${y}-${x}-${Date.now().toString(36)}`;
            const leftSeat = existing?.cell_type === 'seat' ? {...existing, type: 'couple', status: 'active'} : createSeat(x, y, 'couple');
            const rightSeat = right?.cell_type === 'seat'
                ? {...right, type: 'couple', status: 'active'}
                : {...createSeat(x + 1, y, 'couple'), ...nextSeatMetadata(y, [leftSeat.seat_code])};
            if (leftSeat.row !== rightSeat.row || Number(rightSeat.number) - Number(leftSeat.number) !== 1) {
                showEditorError('Hai nửa ghế đôi phải dùng hai mã ghế liên tiếp trong cùng hàng.', [key, rightKey]);
                return;
            }
            if ((existing || right) && !window.confirm('Hai vị trí đang có dữ liệu. Bạn có chắc muốn thay thế rõ ràng bằng một ghế đôi?')) return;
            remember();
            cells.set(key, {...leftSeat, x, y, pair_code: pairCode, pair_position: 'left'});
            cells.set(rightKey, {...rightSeat, x: x + 1, y, pair_code: pairCode, pair_position: 'right'});
            clearCoordinateErrors([key, rightKey]);
        } else if (activeTool === 'maintenance' || activeTool === 'inactive') {
            if (existing?.cell_type === 'aisle' && !window.confirm('Vị trí này là lối đi. Bạn có chắc muốn thay thế lối đi bằng ghế?')) return;
            remember();
            if (existingPair) existingPair.forEach(member => member.status = activeTool);
            else cells.set(key, {...(existing?.cell_type === 'seat' ? existing : createSeat(x, y)), status: activeTool});
        } else if (activeTool === 'empty' || activeTool === 'aisle') {
            if (!existing && activeTool === 'empty') return;
            if (existingPair) {
                showEditorError('Không thể thay đổi riêng một nửa ghế đôi. Hãy dùng thao tác “Tách ghế đôi” trước.', touched);
                return;
            }
            if (existing?.has_bookings) {
                showEditorError('Không thể xóa ghế đã có lịch sử đặt vé.', [key]);
                return;
            }
            if (existing && !window.confirm(`Vị trí này đang là ${existing.cell_type === 'aisle' ? 'lối đi' : existing.seat_code}. Bạn có chắc muốn thay thế?`)) return;
            remember();
            cells.delete(key);
            if (activeTool === 'aisle') cells.set(key, {x, y, cell_type: 'aisle'});
        } else if (existingPair) {
            showEditorError('Không thể thay đổi riêng một nửa ghế đôi. Hãy dùng thao tác “Tách ghế đôi” trước.', touched);
            return;
        } else {
            if (existing?.cell_type === 'aisle' && !window.confirm('Không thể đặt ghế tại vị trí lối đi nếu chưa xác nhận thay thế. Bạn có chắc muốn thay lối đi?')) return;
            if (existing?.cell_type === 'seat' && existing.type === activeTool && existing.status === 'active') return;
            if (existing && !window.confirm(`Tọa độ này đã được sử dụng bởi ${existing.seat_code || 'lối đi'}. Bạn có chắc muốn thay thế?`)) return;
            remember();
            cells.set(key, existing?.cell_type === 'seat'
                ? {...existing, type: activeTool, status: 'active'}
                : createSeat(x, y, activeTool));
        }

        dirty = true;
        render();
    }

    function rebuildCellMap(transform) {
        const transformed = Array.from(cells.values()).map(cell => transform({...cell}));
        const coordinates = new Set();
        for (const cell of transformed) {
            const key = `${cell.x}:${cell.y}`;
            if (coordinates.has(key)) throw new Error('Tọa độ này đã được sử dụng.');
            coordinates.add(key);
        }
        cells.clear();
        transformed.forEach(cell => cells.set(`${cell.x}:${cell.y}`, cell));
    }

    function canvasAction(action, amount) {
        hideEditorError();
        const columns = Number(columnsInput.value);
        if (action.startsWith('expand') && columns + amount > 40) {
            showEditorError('Chiều rộng vùng thiết kế vượt quá giới hạn 40 cột.');
            return;
        }
        if (action.startsWith('shrink') && columns - amount < 1) {
            showEditorError('Chiều rộng vùng thiết kế phải có ít nhất một cột.');
            return;
        }
        if (action === 'shrink-left' && Array.from(cells.values()).some(cell => cell.x <= amount)) {
            showEditorError('Không thể thu hẹp bên trái vì sẽ làm mất một vị trí có ý nghĩa.');
            return;
        }
        if (action === 'shrink-right' && Array.from(cells.values()).some(cell => cell.x > columns - amount)) {
            showEditorError('Không thể thu hẹp bên phải vì sẽ làm mất một vị trí có ý nghĩa.');
            return;
        }
        remember();
        if (action === 'expand-left') rebuildCellMap(cell => ({...cell, x: cell.x + amount}));
        if (action === 'shrink-left') rebuildCellMap(cell => ({...cell, x: cell.x - amount}));
        columnsInput.value = action.startsWith('expand') ? columns + amount : columns - amount;
        lastColumns = Number(columnsInput.value);
        dirty = true;
        render();
    }

    function rowCells(y) {
        return Array.from(cells.values()).filter(cell => cell.y === y);
    }

    function shiftRow(y, delta) {
        const members = rowCells(y);
        if (!members.length) return true;
        const columns = Number(columnsInput.value);
        if (members.some(cell => cell.x + delta < 1 || cell.x + delta > columns)) {
            showEditorError(`Không thể dịch hàng ra ngoài vùng thiết kế. Không còn khoảng trống ở bên ${delta < 0 ? 'trái' : 'phải'} hàng ${rowLabel(y)}.`);
            return false;
        }
        rebuildCellMap(cell => cell.y === y ? {...cell, x: cell.x + delta} : cell);
        return true;
    }

    function rowAction(action, amount = 1) {
        if (!selectedRow) {
            showEditorError('Hãy chọn một hàng trước khi dùng điều khiển hàng.');
            return;
        }
        hideEditorError();
        const y = selectedRow;
        const columns = Number(columnsInput.value);
        const rows = Number(rowsInput.value);

        if (action === 'add-row-before') {
            showEditorError('Không thể thêm hàng phía trước vì thao tác sẽ phải đổi tên các hàng hiện có. Hãy thêm hàng phía sau hàng cuối cùng.');
            return;
        }
        if (action === 'add-row-after') {
            if (y !== rows) {
                showEditorError('Chỉ có thể thêm hàng phía sau hàng cuối cùng để giữ nguyên mã các hàng hiện có.');
                return;
            }
            if (rows >= 30) {
                showEditorError('Số hàng ghế vượt quá giới hạn 30 hàng.');
                return;
            }
            remember();
            rowsInput.value = rows + 1;
            lastRows = rows + 1;
            selectedRow = rows + 1;
        } else if (action === 'add-left' || action === 'add-right') {
            if (columns + amount > 40) {
                showEditorError('Chiều rộng vùng thiết kế vượt quá giới hạn 40 cột.');
                return;
            }
            remember();
            columnsInput.value = columns + amount;
            lastColumns = columns + amount;
            if (action === 'add-left') shiftRow(y, amount);
        } else if (action === 'move-left' || action === 'move-right') {
            const delta = action === 'move-left' ? -1 : 1;
            const members = rowCells(y);
            if (!members.length || members.some(cell => cell.x + delta < 1 || cell.x + delta > columns)) {
                showEditorError(`Không thể dịch hàng ra ngoài vùng thiết kế. Không còn khoảng trống ở bên ${delta < 0 ? 'trái' : 'phải'} hàng ${rowLabel(y)}.`);
                return;
            }
            remember();
            shiftRow(y, delta);
        } else if (action === 'center') {
            const members = rowCells(y);
            if (!members.length) return;
            const min = Math.min(...members.map(cell => cell.x));
            const max = Math.max(...members.map(cell => cell.x));
            const targetMin = Math.floor((columns - (max - min + 1)) / 2) + 1;
            const delta = targetMin - min;
            if (!delta) return;
            remember();
            shiftRow(y, delta);
        } else if (action === 'trim') {
            let left = 0;
            let right = 0;
            const all = Array.from(cells.values());
            while (left < Number(columnsInput.value) - 1 && !all.some(cell => cell.x === left + 1)) left++;
            while (right < Number(columnsInput.value) - left - 1 && !all.some(cell => cell.x === Number(columnsInput.value) - right)) right++;
            if (!left && !right) return;
            remember();
            if (left) rebuildCellMap(cell => ({...cell, x: cell.x - left}));
            columnsInput.value = Number(columnsInput.value) - left - right;
            lastColumns = Number(columnsInput.value);
        }

        dirty = true;
        render();
    }

    function splitSelectedCouple() {
        const cell = selectedCoordinate ? cells.get(selectedCoordinate) : null;
        const pair = validPair(cell);
        if (!pair) return;
        if (pair.some(member => member.has_bookings)) {
            showEditorError('Không thể tách ghế đôi đã có lịch sử đặt vé.', pair.map(member => `${member.x}:${member.y}`));
            return;
        }
        if (!window.confirm(`Tách ghế đôi ${pair.map(member => member.seat_code).join('–')} thành hai ghế thường?`)) return;
        remember();
        pair.forEach(member => Object.assign(member, {type: 'normal', status: 'active', pair_code: null, pair_position: null}));
        dirty = true;
        render();
    }

    function validatePairs() {
        clientCellErrors.clear();
        const invalid = [];
        const visited = new Set();
        Array.from(cells.values()).filter(cell => cell.type === 'couple').forEach(cell => {
            if (visited.has(cell.pair_code)) return;
            visited.add(cell.pair_code);
            const pair = validPair(cell);
            const members = pairMembers(cell);
            const coordinates = members.length ? members.map(member => `${member.x}:${member.y}`) : [`${cell.x}:${cell.y}`];
            const consistentStatus = pair && new Set(pair.map(member => member.status)).size === 1;
            if (!pair || !consistentStatus) {
                const message = !pair
                    ? 'Mỗi ghế đôi phải gồm đúng hai ô liền nhau trong cùng hàng, không bị ngăn bởi lối đi.'
                    : 'Hai nửa ghế đôi phải có cùng trạng thái.';
                coordinates.forEach(coordinate => clientCellErrors.set(coordinate, message));
                invalid.push({message, coordinates});
            }
        });
        return invalid;
    }

    function serializedLayout() {
        normalizeSeatMetadata();
        return {
            schema_version: 3,
            expected_updated_at: expectedUpdatedAt,
            name: document.getElementById('layoutName').value,
            rows: Number(rowsInput.value),
            columns: Number(columnsInput.value),
            screen_position: screenInput.value,
            cells: Array.from(cells.values()).map(cell => cell.cell_type === 'aisle'
                ? {kind: 'aisle', x: cell.x, y: cell.y}
                : {
                    kind: cell.type,
                    seat_id: cell.seat_id || null,
                    type: cell.type,
                    status: cell.status,
                    x: cell.x,
                    y: cell.y,
                    row: cell.row,
                    number: cell.number,
                    seat_code: cell.seat_code,
                    pair_code: cell.pair_code,
                    pair_position: cell.pair_position,
                    has_bookings: Boolean(cell.has_bookings),
                }),
        };
    }

    document.querySelectorAll('[data-tool]').forEach(button => button.addEventListener('click', () => {
        activeTool = button.dataset.tool;
        setEditorMode(activeTool === 'couple' ? 'couple' : 'paint');
        document.querySelectorAll('[data-tool]').forEach(item => {
            item.classList.remove('!border-brand-start', '!text-brand-start');
            item.setAttribute('aria-pressed', 'false');
        });
        button.classList.add('!border-brand-start', '!text-brand-start');
        button.setAttribute('aria-pressed', 'true');
    }));

    function setEditorMode(mode) {
        editorMode = mode;
        document.querySelectorAll('[data-editor-mode]').forEach(button => {
            const active = button.dataset.editorMode === mode;
            button.classList.toggle('!border-brand-start', active);
            button.classList.toggle('!text-brand-start', active);
            button.setAttribute('aria-pressed', String(active));
        });
        document.getElementById('layoutTools')?.classList.toggle('opacity-50', mode === 'expand' || mode === 'move');
        if (mode === 'couple') activeTool = 'couple';
    }

    document.querySelectorAll('[data-editor-mode]').forEach(button => button.addEventListener('click', () => setEditorMode(button.dataset.editorMode)));
    document.querySelectorAll('[data-canvas-action]').forEach(button => button.addEventListener('click', () => canvasAction(button.dataset.canvasAction, Number(button.dataset.amount || 1))));
    document.querySelectorAll('[data-row-action]').forEach(button => button.addEventListener('click', () => rowAction(button.dataset.rowAction, Number(button.dataset.amount || 1))));
    document.getElementById('splitCoupleButton')?.addEventListener('click', splitSelectedCouple);
    document.getElementById('undoLayoutAction')?.addEventListener('click', () => {
        const previous = undoStack.pop();
        if (!previous) return;
        restoreSnapshot(previous);
        document.getElementById('undoLayoutAction').disabled = undoStack.length === 0;
    });

    rowsInput?.addEventListener('change', () => {
        const value = Math.max(1, Math.min(30, Number(rowsInput.value) || lastRows));
        if (value < lastRows && Array.from(cells.values()).some(cell => cell.y > value)) {
            rowsInput.value = lastRows;
            showEditorError('Không thể giảm số hàng vì sẽ làm mất một vị trí có ý nghĩa.');
            return;
        }
        rowsInput.value = value;
        lastRows = value;
        dirty = true;
        render();
    });
    columnsInput?.addEventListener('change', () => {
        const value = Math.max(1, Math.min(40, Number(columnsInput.value) || lastColumns));
        if (value < lastColumns && Array.from(cells.values()).some(cell => cell.x > value)) {
            columnsInput.value = lastColumns;
            showEditorError('Không thể thu nhỏ vùng thiết kế vì sẽ làm mất ghế, lối đi hoặc vị trí có ý nghĩa.');
            return;
        }
        columnsInput.value = value;
        lastColumns = value;
        dirty = true;
        render();
    });
    [screenInput, document.getElementById('layoutName')].forEach(input => input?.addEventListener('change', () => {
        dirty = true;
        render();
    }));

    document.getElementById('saveLayoutForm')?.addEventListener('submit', event => {
        hideEditorError();
        if (!navigator.onLine) {
            event.preventDefault();
            showEditorError('Không có kết nối mạng. Bản nháp vẫn còn trên màn hình; hãy kết nối lại rồi lưu.');
            return;
        }
        const invalid = validatePairs();
        if (invalid.length) {
            event.preventDefault();
            showEditorError(invalid[0].message, invalid[0].coordinates);
            requestAnimationFrame(() => grid.querySelector('[aria-invalid="true"]')?.focus());
            return;
        }
        document.getElementById('layoutPayload').value = JSON.stringify(serializedLayout());
    });

    document.getElementById('publishLayoutForm')?.addEventListener('submit', event => {
        if (dirty) {
            event.preventDefault();
            showEditorError('Bạn đang có thay đổi chưa lưu. Hãy lưu bản nháp trước khi phát hành.');
            return;
        }
        if (!window.confirm('Phát hành sơ đồ ghế này? Sau khi phát hành sẽ không thể chỉnh sửa.')) event.preventDefault();
    });

    window.addEventListener('offline', () => showEditorError('Kết nối mạng đã bị gián đoạn. Bản nháp vẫn còn trên màn hình và chưa được gửi đi.'));
    render();
    if (serverCellErrors.size) requestAnimationFrame(() => grid.querySelector('[aria-invalid="true"]')?.focus());
    else if (serverErrorKeys.length) document.getElementById('layoutServerErrors')?.focus();
});
</script>
@endif
@endsection
