@props(['type' => 'info'])
@php
    $styles = match ($type) {
        'success' => 'border-success/30 bg-success/10 text-success',
        'error' => 'border-error/30 bg-error/10 text-error',
        'warning' => 'border-warning/30 bg-warning/10 text-warning',
        default => 'border-brand-start/30 bg-brand-start/10 text-brand-start',
    };
@endphp
<div {{ $attributes->merge(['class' => "rounded-2xl border px-4 py-3 text-sm font-semibold {$styles}"]) }} role="alert">{{ $slot }}</div>
