@extends('layouts.admin')
@section('title', 'Thiết kế sơ đồ ghế - MovieMate')
@section('page-title', 'Thiết kế sơ đồ ghế')

@section('content')
@php
    $isDraft = $layout?->status === 'draft';
    $initialCells = $layout?->cells->map(function ($cell) {
        if ($cell->cell_type === 'aisle') {
            return ['x' => $cell->x_position, 'y' => $cell->y_position, 'cell_type' => 'aisle'];
        }
        return [
            'x' => $cell->x_position,
            'y' => $cell->y_position,
            'cell_type' => 'seat',
            'type' => $cell->seat->type,
            'status' => $cell->seat->status,
            'seat_code' => $cell->seat->seat_code,
            'pair_code' => $cell->seat->pair_code,
            'pair_position' => $cell->seat->pair_position,
        ];
    })->values() ?? collect();
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm font-extrabold uppercase tracking-[0.2em] text-brand-start">{{ $room->code }} — {{ $room->name }}</p>
            <h1 class="mt-2 text-3xl font-extrabold app-text">Sơ đồ ghế theo phiên bản</h1>
            <p class="mt-2 app-muted">Sơ đồ đã phát hành không thể chỉnh sửa. Mọi thay đổi luôn được thực hiện trên một bản nháp riêng.</p>
        </div>
        <div class="flex gap-2">
            @if($layout)
                <a class="btn-secondary" href="{{ route('admin.rooms.layout.preview', ['room' => $room, 'version' => $layout->version]) }}">Xem trước phiên bản {{ $layout->version }}</a>
            @endif
            <a class="btn-secondary" href="{{ route('admin.rooms.index') }}">Danh sách phòng</a>
        </div>
    </div>

    @foreach(['success' => 'success', 'warning' => 'warning'] as $key => $color)
        @if(session($key))<div class="rounded-2xl border border-{{ $color }}/30 bg-{{ $color }}/10 px-4 py-3 text-sm font-bold text-{{ $color }}">{{ session($key) }}</div>@endif
    @endforeach
    @if($errors->any())
        <div class="rounded-2xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if(!$layout || !$isDraft)
        <div class="cinema-card p-6">
            <h2 class="text-xl font-bold app-text">{{ $layout ? "Sơ đồ đã phát hành phiên bản {$layout->version}" : 'Phòng chưa có sơ đồ ghế' }}</h2>
            <p class="mt-2 app-muted">{{ $layout ? 'Sao chép sơ đồ này thành bản nháp mới trước khi chỉnh sửa.' : 'Chọn kích thước lưới ban đầu. Các ô trống sẽ không được lưu.' }}</p>
            <form method="POST" action="{{ route('admin.rooms.layout.draft', $room) }}" class="mt-5 flex flex-wrap items-end gap-4">
                @csrf
                @unless($layout)
                    <label class="cinema-label">Số hàng ghế <input class="cinema-input mt-1 w-28" type="number" name="rows" min="1" max="30" value="10"></label>
                    <label class="cinema-label">Số ô mỗi hàng <input class="cinema-input mt-1 w-28" type="number" name="columns" min="1" max="40" value="12"></label>
                    <label class="cinema-label">Vị trí màn hình <select class="cinema-input mt-1" name="screen_position"><option value="top">Phía trên</option><option value="bottom">Phía dưới</option></select></label>
                @endunless
                <button class="btn-primary">{{ $layout ? 'Sao chép thành bản nháp phiên bản '.($layout->version + 1) : 'Tạo bản nháp trống' }}</button>
            </form>
        </div>
    @endif

    @if($layout)
        <div class="cinema-card p-5 sm:p-6">
            <div class="grid gap-4 md:grid-cols-4">
                <label class="cinema-label">Tên sơ đồ<input id="layoutName" class="cinema-input mt-1" value="{{ $layout->display_name }}" @disabled(!$isDraft)></label>
                <label class="cinema-label">Số hàng ghế<input id="layoutRows" type="number" min="1" max="30" class="cinema-input mt-1" value="{{ $layout->rows }}" @disabled(!$isDraft)></label>
                <label class="cinema-label">Số ô mỗi hàng<input id="layoutColumns" type="number" min="1" max="40" class="cinema-input mt-1" value="{{ $layout->columns }}" @disabled(!$isDraft)></label>
                <label class="cinema-label">Màn hình<select id="screenPosition" class="cinema-input mt-1" @disabled(!$isDraft)><option value="top" @selected($layout->screen_position === 'top')>Phía trên</option><option value="bottom" @selected($layout->screen_position === 'bottom')>Phía dưới</option></select></label>
            </div>

            @if($isDraft)
                <div id="layoutTools" class="mt-5 flex flex-wrap gap-2" aria-label="Công cụ thiết kế sơ đồ ghế">
                    @foreach(['normal'=>'Ghế thường','vip'=>'Ghế VIP','couple'=>'Ghế đôi','aisle'=>'Lối đi','empty'=>'Ô trống','maintenance'=>'Bảo trì','inactive'=>'Không sử dụng'] as $tool=>$label)
                        <button type="button" data-tool="{{ $tool }}" class="btn-secondary !px-3 !py-2 text-xs {{ $tool === 'normal' ? '!border-brand-start !text-brand-start' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 overflow-x-auto pb-4">
                <div id="screenTop" class="mx-auto mb-5 h-2 min-w-[480px] max-w-4xl rounded-t-[100%] bg-brand-start/50 {{ $layout->screen_position === 'bottom' ? 'hidden' : '' }}"></div>
                <div id="layoutGrid" class="mx-auto grid w-max gap-1.5" aria-label="Lưới thiết kế sơ đồ ghế"></div>
                <div id="screenBottom" class="mx-auto mt-5 h-2 min-w-[480px] max-w-4xl rounded-b-[100%] bg-brand-start/50 {{ $layout->screen_position === 'top' ? 'hidden' : '' }}"></div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t app-border pt-5">
                <p class="text-sm app-muted"><span id="seatCount">0</span> ghế · Bản nháp phiên bản {{ $layout->version }} · Chọn một ô để áp dụng công cụ hiện tại.</p>
                @if($isDraft)
                    <div class="flex gap-2">
                        <form id="saveLayoutForm" method="POST" action="{{ route('admin.rooms.layout.update', $room) }}">@csrf @method('PATCH')<input type="hidden" name="layout" id="layoutPayload"><button class="btn-secondary">Lưu bản nháp</button></form>
                        <form method="POST" action="{{ route('admin.rooms.layout.publish', $room) }}" onsubmit="return confirm('Phát hành sơ đồ ghế này? Sau khi phát hành sẽ không thể chỉnh sửa.');">@csrf<button class="btn-primary">Phát hành phiên bản {{ $layout->version }}</button></form>
                    </div>
                @endif
            </div>
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
    const typeLabels = @json(\App\Support\StatusLabel::options('seat_type'));
    const statusLabels = @json(\App\Support\StatusLabel::options('seat'));
    const cells = new Map(initialCells.map(cell => [`${cell.x}:${cell.y}`, cell]));
    let activeTool = 'normal';

    const rowLabel = index => index <= 26 ? String.fromCharCode(64 + index) : `A${String.fromCharCode(64 + index - 26)}`;
    const classes = {
        normal: 'app-input app-muted', vip: 'bg-ai-start/10 text-ai-start border-ai-start/50',
        couple: 'bg-warning/10 text-warning border-warning/50', aisle: 'border-dashed app-muted opacity-50',
        maintenance: 'bg-gray-300 text-gray-600 border-gray-400', inactive: 'bg-dark-border/30 app-muted'
    };

    function normalizeSeatMetadata() {
        const rows = Number(rowsInput.value);
        for (let y = 1; y <= rows; y++) {
            const rowSeats = Array.from(cells.values()).filter(cell => cell.y === y && cell.cell_type === 'seat').sort((a,b) => a.x - b.x);
            let pendingCouple = null;
            rowSeats.forEach((cell, index) => {
                cell.row = rowLabel(y); cell.number = index + 1; cell.seat_code = `${cell.row}${cell.number}`;
                cell.pair_code = null; cell.pair_position = null;
                if (cell.type === 'couple') {
                    if (!pendingCouple) {
                        pendingCouple = cell;
                    } else {
                        const group = `${cell.row}-PAIR-${Math.ceil(cell.number / 2)}`;
                        pendingCouple.pair_code = group; pendingCouple.pair_position = 'left';
                        cell.pair_code = group; cell.pair_position = 'right'; pendingCouple = null;
                    }
                } else {
                    pendingCouple = null;
                }
            });
            if (pendingCouple) {
                pendingCouple.pair_code = `${pendingCouple.row}-INCOMPLETE`;
                pendingCouple.pair_position = 'left';
            }
        }
    }

    function render() {
        const rows = Math.max(1, Math.min(30, Number(rowsInput.value) || 1));
        const columns = Math.max(1, Math.min(40, Number(columnsInput.value) || 1));
        rowsInput.value = rows; columnsInput.value = columns;
        for (const [key, cell] of cells) if (cell.x > columns || cell.y > rows) cells.delete(key);
        normalizeSeatMetadata();
        grid.style.gridTemplateColumns = `repeat(${columns}, 2.35rem)`;
        grid.replaceChildren();
        for (let y = 1; y <= rows; y++) for (let x = 1; x <= columns; x++) {
            const key = `${x}:${y}`; const cell = cells.get(key);
            const button = document.createElement('button');
            button.type = 'button'; button.dataset.coordinate = key;
            button.className = 'h-9 w-9 rounded-lg border text-[9px] font-bold transition-colors ';
            if (!cell) { button.className += 'border-dashed app-border opacity-30'; button.textContent = '+'; }
            else if (cell.cell_type === 'aisle') { button.className += classes.aisle; button.textContent = '│'; }
            else {
                button.className += classes[cell.status !== 'active' ? cell.status : cell.type] || classes.normal;
                button.textContent = cell.seat_code;
                button.title = `${cell.seat_code} · ${typeLabels[cell.type] || 'Chưa xác định'} · ${statusLabels[cell.status] || 'Chưa xác định'}`;
            }
            if (editable) button.addEventListener('click', () => applyTool(x, y)); else button.disabled = true;
            grid.appendChild(button);
        }
        document.getElementById('seatCount').textContent = Array.from(cells.values()).filter(cell => cell.cell_type === 'seat').length;
        document.getElementById('screenTop').classList.toggle('hidden', screenInput.value !== 'top');
        document.getElementById('screenBottom').classList.toggle('hidden', screenInput.value !== 'bottom');
    }

    function applyTool(x, y) {
        const key = `${x}:${y}`; const existing = cells.get(key);
        if (activeTool === 'empty') cells.delete(key);
        else if (activeTool === 'aisle') cells.set(key, {x, y, cell_type: 'aisle'});
        else if (activeTool === 'maintenance' || activeTool === 'inactive') cells.set(key, {...(existing?.cell_type === 'seat' ? existing : {x,y,cell_type:'seat',type:'normal'}), status: activeTool});
        else cells.set(key, {x, y, cell_type:'seat', type:activeTool, status:'active'});
        render();
    }

    document.querySelectorAll('[data-tool]').forEach(button => button.addEventListener('click', () => {
        activeTool = button.dataset.tool;
        document.querySelectorAll('[data-tool]').forEach(item => item.classList.remove('!border-brand-start','!text-brand-start'));
        button.classList.add('!border-brand-start','!text-brand-start');
    }));
    [rowsInput, columnsInput, screenInput].forEach(input => input.addEventListener('change', render));
    document.getElementById('saveLayoutForm')?.addEventListener('submit', () => {
        normalizeSeatMetadata();
        document.getElementById('layoutPayload').value = JSON.stringify({
            name: document.getElementById('layoutName').value,
            rows: Number(rowsInput.value), columns: Number(columnsInput.value), screen_position: screenInput.value,
            cells: Array.from(cells.values()).map(cell => cell.cell_type === 'aisle'
                ? {kind:'aisle', x:cell.x, y:cell.y}
                : {kind:cell.type, type:cell.type, status:cell.status, x:cell.x, y:cell.y, row:cell.row, number:cell.number, seat_code:cell.seat_code, pair_code:cell.pair_code, pair_position:cell.pair_position})
        });
    });
    render();
});
</script>
@endif
@endsection
