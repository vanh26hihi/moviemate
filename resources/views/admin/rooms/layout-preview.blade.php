@extends('layouts.admin')
@section('title', 'Xem trước sơ đồ ghế - MovieMate')
@section('page-title', 'Xem trước sơ đồ ghế')
@section('content')
@php
    $cellMap = $layout->cells->keyBy(fn ($cell) => $cell->x_position.':'.$cell->y_position);
    $seatGroups = \App\Support\SeatPresentation::groups($layout->cells->pluck('seat')->filter()->values());
    $seatGroupLookup = collect();
    foreach ($seatGroups as $group) {
        foreach ($group['seats'] as $member) {
            $seatGroupLookup->put($member->id, $group);
        }
    }
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="text-sm font-extrabold uppercase tracking-[0.2em] text-brand-start">{{ $room->code }} — {{ $room->name }}</p><h1 class="mt-2 text-3xl font-extrabold app-text">{{ $layout->display_name }} · phiên bản {{ $layout->version }}</h1><p class="app-muted">{{ $layout->rows }} × {{ $layout->columns }} · {{ $layout->cells->where('cell_type', 'seat')->count() }} ghế · {{ $layout->status_label }}</p></div>
        @can('seats.manage') @if($room->status === 'active')<a href="{{ route('admin.rooms.layout.show', $room) }}" class="btn-primary"><i class="ph ph-pencil-ruler" aria-hidden="true"></i>Mở trình thiết kế</a>@endif @endcan
    </div>
    <div class="cinema-card overflow-x-auto p-6">
        @if($layout->screen_position === 'top')<div class="mx-auto mb-8 h-2 min-w-[30rem] max-w-4xl rounded-t-[100%] bg-brand-start/50" aria-hidden="true"></div>@endif
        <div class="mx-auto grid w-max gap-1.5" style="grid-template-columns: repeat({{ $layout->columns }}, 2.35rem)">
            @for($y=1; $y <= $layout->rows; $y++) @for($x=1; $x <= $layout->columns; $x++)
                @php
                    $cell = $cellMap->get($x.':'.$y);
                    $seat = $cell?->seat;
                    $group = $seat ? $seatGroupLookup->get($seat->id) : null;
                    $groupSeats = $seat ? ($group['seats'] ?? collect([$seat])) : collect();
                    $isMergedCouple = (bool) ($group['is_couple'] ?? false) && (bool) ($group['is_valid'] ?? false);
                    $primarySeatId = $isMergedCouple ? $groupSeats->sortBy('x_position')->first()?->id : $seat?->id;
                    $consistentStatus = $seat && $groupSeats->pluck('status')->unique()->count() === 1;
                    $available = $seat && $consistentStatus && $groupSeats->every(fn ($member) => $member->status === 'active');
                @endphp
                @if(!$cell)<span class="h-9 w-9"></span>
                @elseif($cell->cell_type === 'aisle')<span class="flex h-9 w-9 items-center justify-center rounded-lg border border-dashed app-border text-xs app-muted" aria-label="Lối đi"><i class="ph ph-arrows-down-up" aria-hidden="true"></i></span>
                @elseif($cell->cell_type === 'blocked')<span class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-500 bg-slate-800 text-slate-200" aria-label="Vật cản cố định hàng {{ chr(64 + $y) }}, cột {{ $x }}, vị trí cấu trúc không bố trí ghế"><i class="ph ph-bricks" aria-hidden="true"></i></span>
                @elseif($isMergedCouple && $seat->id !== $primarySeatId) @continue
                @else
                    @php
                        $invalidPair = (bool) ($group['is_couple'] ?? false) && ! (bool) ($group['is_valid'] ?? false);
                        $visualClass = match(true) {
                            $invalidPair || ! $consistentStatus => 'border-error/70 bg-error/10 text-error',
                            ! $available => 'border-gray-400 bg-gray-300 text-gray-600',
                            ($group['type'] ?? $seat->type) === 'vip' => 'border-ai-start/50 bg-ai-start/10 text-ai-start',
                            ($group['type'] ?? $seat->type) === 'couple' => 'border-warning/50 bg-warning/10 text-warning',
                            default => 'app-input app-muted',
                        };
                        $displayCode = $group['seat_code'] ?? $seat->seat_code;
                        $stateLabel = $invalidPair || ! $consistentStatus
                            ? 'Dữ liệu ghế đôi không đồng nhất, không khả dụng'
                            : \App\Support\StatusLabel::for('seat', $groupSeats->first()->status);
                    @endphp
                    <span class="flex h-9 items-center justify-center rounded-lg border px-1 text-[9px] font-bold {{ $isMergedCouple ? 'col-span-2 min-w-[5.1rem]' : 'w-9' }} {{ $visualClass }}" aria-label="{{ ($group['is_couple'] ?? false) ? 'Ghế đôi '.$displayCode : 'Ghế '.$displayCode }}, {{ $stateLabel }}">
                        @if($invalidPair || ! $consistentStatus)<i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>@endif{{ $displayCode }}
                    </span>
                @endif
            @endfor @endfor
        </div>
        @if($layout->screen_position === 'bottom')<div class="mx-auto mt-8 h-2 min-w-[30rem] max-w-4xl rounded-b-[100%] bg-brand-start/50" aria-hidden="true"></div>@endif
    </div>
</div>
@endsection
