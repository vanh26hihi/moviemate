@extends('layouts.user')

@section('title', 'Lịch chiếu tại '.$cinema->name)

@section('content')
<section class="cinema-surface py-10 md:py-14">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <a href="{{ route('cinemas.index') }}" class="text-sm font-bold text-brand-start"><i class="ph ph-arrow-left" aria-hidden="true"></i> Tất cả rạp</a>
        <div class="mt-5 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"><div><p class="text-sm font-extrabold uppercase tracking-wider text-brand-start">{{ $cinema->district }}, {{ $cinema->city }}</p><h1 class="hero-title mt-2 text-4xl font-extrabold app-text md:text-5xl">Lịch chiếu tại {{ $cinema->name }}</h1><p class="mt-3 app-muted">{{ $cinema->address }}@if($cinema->phone) · {{ $cinema->phone }}@endif</p><p class="mt-2 text-sm app-muted">Định dạng: {{ $formats->join(', ') ?: 'Đang cập nhật' }}</p></div>
            @unless($preferredCinema?->is($cinema))<form method="POST" action="{{ route('cinema-context.update') }}">@csrf<input type="hidden" name="cinema" value="{{ $cinema->code }}"><button class="btn-secondary" type="submit"><i class="ph ph-push-pin" aria-hidden="true"></i>Chọn làm rạp ưu tiên</button></form>@endunless
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-[260px_1fr]">
        <aside class="cinema-card self-start rounded-3xl p-5 lg:sticky lg:top-24"><h2 class="font-extrabold app-text">Giờ hoạt động</h2><dl class="mt-4 space-y-2 text-sm">@foreach($cinema->operatingHours->sortBy('day_of_week') as $hours)<div class="flex justify-between gap-3"><dt class="app-muted">Thứ {{ $hours->day_of_week === 7 ? 'CN' : $hours->day_of_week + 1 }}</dt><dd class="font-bold app-text">{{ $hours->is_closed ? 'Đóng cửa' : substr($hours->opens_at,0,5).' – '.substr($hours->latest_show_start_at,0,5) }}</dd></div>@endforeach</dl></aside>
        <div class="min-w-0">
            <form method="GET" action="{{ route('cinemas.show', $cinema->code) }}" data-showtime-filter-form data-filter-endpoint="{{ route('showtimes.filter') }}" data-filter-context="cinema" data-cinema-code="{{ $cinema->code }}" class="cinema-card mb-6 p-4">
                <x-customer.showtimes.date-rail :dates="$dates" :selected-date="$selectedDate" />
            </form>
            <div data-showtime-filter-status class="sr-only" role="status" aria-live="polite"></div>
            <div data-showtime-results>@include('user.partials.showtime-results', ['context' => 'cinema', 'movie' => null])</div>
        </div>
    </div>
</section>
@endsection
