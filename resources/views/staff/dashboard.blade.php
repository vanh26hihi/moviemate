@extends('layouts.staff')

@section('title', 'Bàn làm việc nhân viên - MovieMate')
@section('page-title', 'Bàn làm việc')

@section('content')
<div class="space-y-7">
    <header class="admin-page-header">
        <div><p class="text-sm font-bold text-brand-start">{{ $now->format('H:i · d/m/Y') }}</p><h1 class="admin-page-title">Bàn làm việc hôm nay</h1><p class="admin-page-subtitle">{{ $cinema?->name ?? 'Không có chi nhánh vận hành' }}</p></div>
        @if($cinema)<div class="flex flex-wrap gap-2"><a href="{{ route('staff.counter.index') }}" class="btn-primary"><i class="ph ph-storefront"></i>Bán vé tại quầy</a><a href="{{ route('staff.tickets.index') }}" class="btn-secondary"><i class="ph ph-qr-code"></i>Quét QR</a></div>@endif
    </header>

    @if(!$cinema)
        <x-empty-state title="Bạn chưa được phân công chi nhánh" description="Liên hệ quản lý để được phân công chi nhánh trước khi thực hiện nghiệp vụ." icon="ph-map-pin" />
    @else
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Tình hình vận hành hôm nay">
            @foreach([['Vé bán hôm nay',$stats['sold'],'ph-ticket'],['Chờ in',$stats['waiting_print'],'ph-printer'],['In cần chú ý',$stats['print_attention'],'ph-warning'],['Đơn quầy đang giữ',$stats['pending_counter'],'ph-hourglass']] as [$label,$value,$icon])
                <article class="cinema-card p-5"><i class="ph {{ $icon }} text-2xl text-brand-start"></i><p class="mt-3 text-3xl font-black app-heading">{{ $value }}</p><p class="mt-1 text-sm app-muted">{{ $label }}</p></article>
            @endforeach
        </section>

        <section class="cinema-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b app-border p-5"><div><h2 class="text-xl font-extrabold app-heading">Suất chiếu hôm nay</h2><p class="mt-1 text-sm app-muted">Chỉ hiển thị lịch vận hành của {{ $cinema->name }}.</p></div><a href="{{ route('staff.counter.index') }}" class="text-sm font-bold text-brand-start">Mở bán vé →</a></div>
            @if($showtimes->isEmpty())
                <div class="p-6"><x-empty-state title="Không có suất chiếu phù hợp hôm nay" description="Lịch vận hành hiện chưa có suất chiếu trong ngày." icon="ph-calendar-x" /></div>
            @else
                <div class="divide-y app-border">
                    @foreach($showtimes as $showtime)
                        @php
                            $startsAt=\Carbon\CarbonImmutable::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time, $cinema->timezone ?: config('cinema.timezone'));
                            $endsAt=$startsAt->addMinutes((int)($showtime->movie->duration ?: 90));
                            $status=match(true){$now->greaterThanOrEqualTo($endsAt)=>'Đã kết thúc',$now->greaterThanOrEqualTo($startsAt)=>'Đang chiếu',$now->greaterThanOrEqualTo($startsAt->subMinutes(30))=>'Đang mở cửa',default=>'Sắp chiếu'};
                            $remaining=max(0,(int)$showtime->operational_seats_count-(int)$showtime->sold_seats_count);
                        @endphp
                        <article class="grid gap-3 p-5 md:grid-cols-[6rem_1fr_auto] md:items-center"><div><p class="text-2xl font-black app-heading">{{ $startsAt->format('H:i') }}</p><p class="text-xs font-bold text-brand-start">{{ $status }}</p></div><div><h3 class="font-extrabold app-text">{{ $showtime->movie->title }}</h3><p class="mt-1 text-sm app-muted">{{ $showtime->room->name }} · Loại phòng: {{ $showtime->room->room_type_label }} · Định dạng trình chiếu: {{ $showtime->presentationFormat?->name ?? 'Không xác định' }} · Còn {{ $remaining }}/{{ (int)$showtime->operational_seats_count }} ghế</p></div>@if($startsAt->isFuture())<a class="btn-secondary" href="{{ route('staff.counter.seats',$showtime) }}">Chọn ghế</a>@endif</article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
