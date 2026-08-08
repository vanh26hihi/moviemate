@props(['showtime'])
@php
    $label = $showtime['starts_at']->format('H:i').' ~ '.$showtime['customer_visible_ends_at']->format('H:i');
    $classes = 'inline-flex min-h-12 flex-col items-center justify-center rounded-xl border px-4 py-2 text-sm font-extrabold';
@endphp
@if($showtime['bookable'])
    <a href="{{ $showtime['booking_url'] }}" class="{{ $classes }} border-brand-start/30 bg-brand-start/10 text-brand-start hover:bg-brand-start hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-start/30" aria-label="Đặt vé suất {{ $label }}">
        <span>{{ $label }}</span>
        @if($showtime['starting_price'] !== null)<span class="text-[11px] font-semibold opacity-80">Từ {{ number_format($showtime['starting_price'], 0, ',', '.') }} ₫</span>@endif
    </a>
@else
    <span class="{{ $classes }} app-border app-muted opacity-60" aria-disabled="true"><span>{{ $label }}</span><span class="text-[11px] font-semibold">Không khả dụng</span></span>
@endif
