@props(['showtime'])
@php
    $label = $showtime['starts_at']->format('H:i').' ~ '.$showtime['customer_visible_ends_at']->format('H:i');
    $classes = 'inline-flex min-h-12 flex-col items-center justify-center rounded-xl border px-4 py-2 text-sm font-extrabold';
@endphp
@if($showtime['bookable'])
    <a
        href="{{ $showtime['booking_url'] }}"
        class="{{ $classes }} border-brand-start/30 bg-brand-start/10 text-brand-start hover:bg-brand-start hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-start/30"
        aria-label="Đặt vé suất {{ $label }}"
        data-customer-showtime
        data-server-now="{{ $showtime['server_now']->toIso8601String() }}"
        data-start-at="{{ $showtime['starts_at']->toIso8601String() }}"
        data-end-at="{{ $showtime['customer_visible_ends_at']->toIso8601String() }}"
        data-booking-cutoff-at="{{ $showtime['booking_closes_at']->toIso8601String() }}"
    >
        <span>{{ $label }}</span>
        @if($showtime['starting_price'] !== null)<span class="text-[11px] font-semibold opacity-80">Từ {{ number_format($showtime['starting_price'], 0, ',', '.') }} ₫</span>@endif
        <span class="hidden text-[11px] font-semibold" data-showtime-booking-status>Đã đóng đặt vé</span>
    </a>
@else
    <span class="{{ $classes }} app-border app-muted opacity-60" aria-disabled="true"><span>{{ $label }}</span><span class="text-[11px] font-semibold">Đã đóng đặt vé</span></span>
@endif
