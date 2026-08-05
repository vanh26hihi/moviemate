@extends('layouts.admin')
@section('title', 'Preview layout - MovieMate')
@section('page-title', 'Preview layout')
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="text-sm font-extrabold uppercase tracking-[0.2em] text-brand-start">{{ $room->code }} — {{ $room->name }}</p><h1 class="mt-2 text-3xl font-extrabold app-text">{{ $layout->name }} · v{{ $layout->version }}</h1><p class="app-muted">{{ $layout->rows }} × {{ $layout->columns }} · {{ $layout->cells->where('cell_type', 'seat')->count() }} ghế · {{ $layout->status }}</p></div>
        @can('seats.manage')<a href="{{ route('admin.rooms.layout.show', $room) }}" class="btn-primary">Mở editor</a>@endcan
    </div>
    <div class="cinema-card overflow-x-auto p-6">
        @if($layout->screen_position === 'top')<div class="mx-auto mb-8 h-2 min-w-[480px] max-w-4xl rounded-t-[100%] bg-brand-start/50"></div>@endif
        <div class="mx-auto grid w-max gap-1.5" style="grid-template-columns: repeat({{ $layout->columns }}, 2.35rem)">
            @php $cellMap = $layout->cells->keyBy(fn($cell) => $cell->x_position.':'.$cell->y_position); @endphp
            @for($y=1; $y <= $layout->rows; $y++) @for($x=1; $x <= $layout->columns; $x++)
                @php $cell = $cellMap->get($x.':'.$y); $seat = $cell?->seat; @endphp
                @if(!$cell)<span class="h-9 w-9"></span>
                @elseif($cell->cell_type === 'aisle')<span class="flex h-9 w-9 items-center justify-center rounded-lg border border-dashed app-border text-xs app-muted" aria-label="Lối đi">│</span>
                @else<span class="flex h-9 w-9 items-center justify-center rounded-lg border text-[9px] font-bold {{ $seat->status !== 'active' ? 'bg-gray-300 text-gray-600 border-gray-400' : ($seat->type === 'vip' ? 'bg-ai-start/10 text-ai-start border-ai-start/50' : ($seat->type === 'couple' ? 'bg-warning/10 text-warning border-warning/50' : 'app-input app-muted')) }}" aria-label="Ghế {{ $seat->seat_code }}, {{ $seat->type }}, {{ $seat->status }}">{{ $seat->seat_code }}</span>
                @endif
            @endfor @endfor
        </div>
        @if($layout->screen_position === 'bottom')<div class="mx-auto mt-8 h-2 min-w-[480px] max-w-4xl rounded-b-[100%] bg-brand-start/50"></div>@endif
    </div>
</div>
@endsection
