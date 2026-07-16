@extends('layouts.admin')

@section('title', 'Quản lý ghế - ' . $room->name)
@section('page-title', 'Sơ đồ ghế')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">{{ $room->cinema->name ?? 'MovieMate' }}</p>
            <h1 class="text-3xl font-extrabold app-text">{{ $room->name }}</h1>
            <p class="app-muted mt-2">{{ $room->room_type }} · {{ $seats->count() }} ghế hiện có</p>
        </div>
        <a href="{{ route('admin.rooms.index') }}" class="btn-secondary">
            <i class="ph ph-arrow-left"></i> Quay lại phòng chiếu
        </a>
    </div>

    @if(session('success') || session('error'))
        <div class="rounded-2xl border {{ session('success') ? 'border-success/30 bg-success/10 text-success' : 'border-error/30 bg-error/10 text-error' }} px-4 py-3 text-sm font-bold">
            {{ session('success') ?? session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 cinema-card p-5 sm:p-6 overflow-hidden">
            <div class="text-center mb-8">
                <div class="h-2 w-full max-w-3xl mx-auto bg-gradient-to-r from-brand-start to-brand-end rounded-t-[100%] shadow-[0_14px_36px_rgba(255,61,87,0.32)]"></div>
                <p class="app-muted text-xs font-extrabold tracking-[0.35em] uppercase mt-4">Màn hình</p>
            </div>

            <div class="overflow-x-auto pb-4">
                <div class="min-w-[560px] flex flex-col items-center gap-2.5">
                    @foreach($seats->groupBy('row') as $row => $rowSeats)
                        <div class="flex items-center gap-3">
                            <span class="w-6 text-center app-muted text-xs font-extrabold">{{ $row }}</span>
                            <div class="flex gap-1.5">
                                @foreach($rowSeats->sortBy('number') as $seat)
                                    @php
                                        $seatClass = match (true) {
                                            $seat->status !== 'active' => 'bg-error/15 border-error/35 text-error',
                                            $seat->type === 'vip' => 'bg-warning/15 border-warning/45 text-warning',
                                            $seat->type === 'couple' => 'bg-ai-start/15 border-ai-start/45 text-ai-start',
                                            default => 'app-input app-border app-muted',
                                        };
                                    @endphp
                                    <div class="w-9 h-9 rounded-t-xl border text-[11px] font-extrabold flex items-center justify-center {{ $seatClass }}" title="{{ $seat->seat_code }} - {{ $seat->type }} - {{ $seat->status }}">
                                        {{ $seat->number }}
                                    </div>
                                @endforeach
                            </div>
                            <span class="w-6 text-center app-muted text-xs font-extrabold">{{ $row }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap justify-center gap-4 mt-6 pt-5 border-t app-border text-xs">
                <span class="inline-flex items-center gap-2 app-muted"><i class="w-5 h-5 rounded-t-lg app-input border app-border inline-block"></i>Thường</span>
                <span class="inline-flex items-center gap-2 app-muted"><i class="w-5 h-5 rounded-t-lg bg-warning/15 border border-warning/45 inline-block"></i>VIP</span>
                <span class="inline-flex items-center gap-2 app-muted"><i class="w-5 h-5 rounded-t-lg bg-ai-start/15 border border-ai-start/45 inline-block"></i>Couple</span>
                <span class="inline-flex items-center gap-2 app-muted"><i class="w-5 h-5 rounded-t-lg bg-error/15 border border-error/45 inline-block"></i>Bảo trì</span>
            </div>
        </div>

        <div class="cinema-card p-5 sm:p-6">
            <h2 class="text-xl font-extrabold app-text mb-2">Tạo ghế tự động</h2>
            <p class="app-muted text-sm mb-6">Nhập dải hàng và số ghế mỗi hàng để tạo sơ đồ nhanh.</p>

            <form action="{{ route('admin.seats.generate', $room) }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label class="cinema-label">Khoảng hàng</label>
                    <input type="text" name="rows" placeholder="A-H" required class="cinema-input">
                    <p class="app-muted text-xs mt-2">Ví dụ: A-H</p>
                </div>

                <div>
                    <label class="cinema-label">Số ghế mỗi hàng</label>
                    <input type="number" name="seats_per_row" min="1" required class="cinema-input">
                </div>

                <div>
                    <label class="cinema-label">Hàng VIP</label>
                    <input type="text" name="vip_rows" placeholder="E,F,G" class="cinema-input">
                    <p class="app-muted text-xs mt-2">Nhập các hàng cách nhau bằng dấu phẩy.</p>
                </div>

                <button type="submit" class="btn-primary w-full">Tạo ghế</button>
            </form>
        </div>
    </div>
</div>
@endsection
