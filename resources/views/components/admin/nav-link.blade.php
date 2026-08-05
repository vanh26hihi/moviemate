@props([
    'routeName' => null,
    'label',
    'icon',
    'activePattern' => null,
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
    </a>
@endif
