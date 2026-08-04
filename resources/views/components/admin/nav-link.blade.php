@props([
    'routeName' => null,
    'label',
    'icon',
    'activePattern' => null,
])

@php
    $enabled = filled($routeName) && \Illuminate\Support\Facades\Route::has($routeName);
    $active = $enabled && request()->routeIs($activePattern ?: $routeName);
    $classes = $active
        ? 'bg-brand-start/10 text-brand-start font-bold'
        : 'app-muted hover:bg-brand-start/5 hover:text-brand-start transition-colors text-sm font-medium';
@endphp

@if($enabled)
    <a href="{{ route($routeName) }}" {{ $attributes->class("flex items-center gap-3 px-3 py-2.5 rounded-xl {$classes}") }}>
        <i class="{{ $active ? 'ph-fill' : 'ph' }} {{ $icon }} text-lg"></i>
        <span>{{ $label }}</span>
    </a>
@else
    <span aria-disabled="true" title="Backend TEAM chưa cung cấp route này"
          {{ $attributes->class('flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium app-muted opacity-45') }}>
        <i class="ph {{ $icon }} text-lg"></i>
        <span>{{ $label }}</span>
        <i class="ph ph-lock-simple ml-auto text-xs"></i>
    </span>
@endif
