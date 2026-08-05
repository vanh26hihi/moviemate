@props(['label' => 'Đang tải dữ liệu'])
<div {{ $attributes->merge(['class' => 'flex items-center justify-center gap-3 rounded-2xl border app-border app-secondary px-5 py-8 app-muted']) }} role="status" aria-live="polite">
    <span class="h-5 w-5 animate-spin rounded-full border-2 border-brand-start/25 border-t-brand-start" aria-hidden="true"></span>
    <span class="text-sm font-semibold">{{ $label }}</span>
</div>
