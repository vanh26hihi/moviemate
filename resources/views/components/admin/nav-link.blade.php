@props([
    'routeName' => null,
    'label',
    'icon',
    'activePattern' => null,
    'badge' => null,
])

@php
    $enabled = filled($routeName) && \Illuminate\Support\Facades\Route::has($routeName);
    $activePatterns = (array) ($activePattern ?: $routeName);
    $active = $enabled && request()->routeIs(...$activePatterns);
@endphp

@if($enabled)
    <a href="{{ route($routeName) }}"
       @class(['admin-nav-link', 'is-active' => $active])
       @if($active) aria-current="page" @endif
       data-admin-nav-route="{{ $routeName }}">
        <i class="{{ $active ? 'ph-fill' : 'ph' }} {{ $icon }} shrink-0 text-lg" aria-hidden="true"></i>
        <span>{{ $label }}</span>
        @if(filled($badge))<span class="ml-auto rounded-full bg-warning/15 px-2 py-0.5 text-[10px] font-extrabold text-warning" aria-label="{{ $badge }} giao dịch cần chú ý">{{ $badge }}</span>@endif
    </a>
@endif
