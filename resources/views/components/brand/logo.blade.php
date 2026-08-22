@props([
    'variant' => 'theme',
    'alt' => 'MovieMate',
])

@php
    $variantClass = match ($variant) {
        'mark' => 'brand-logo--mark',
        'white' => 'brand-logo--white',
        'light' => 'brand-logo--light-static',
        'dark' => 'brand-logo--dark-static',
        default => 'brand-logo--theme',
    };
@endphp

<span {{ $attributes->class(['brand-logo', $variantClass]) }}>
    @switch($variant)
        @case('mark')
            <img class="brand-logo__image" src="{{ asset('images/brand/mark.png') }}" width="512" height="512" alt="{{ $alt }}">
            @break

        @case('white')
            <img class="brand-logo__image" src="{{ asset('images/brand/logo-white.png') }}" width="835" height="177" alt="{{ $alt }}">
            @break

        @case('light')
            <img class="brand-logo__image" src="{{ asset('images/brand/logo-on-light.png') }}" width="1055" height="215" alt="{{ $alt }}">
            @break

        @case('dark')
            <img class="brand-logo__image" src="{{ asset('images/brand/logo-on-dark.png') }}" width="978" height="211" alt="{{ $alt }}">
            @break

        @default
            <img class="brand-logo__image brand-logo__image--dark" src="{{ asset('images/brand/logo-on-dark.png') }}" width="978" height="211" alt="{{ $alt }}">
            <img class="brand-logo__image brand-logo__image--light" src="{{ asset('images/brand/logo-on-light.png') }}" width="1055" height="215" alt="{{ $alt }}">
            <img class="brand-logo__image brand-logo__image--compact" src="{{ asset('images/brand/mark.png') }}" width="512" height="512" alt="{{ $alt }}">
    @endswitch
</span>
