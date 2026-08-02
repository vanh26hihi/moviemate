@props([
    'title',
    'description',
    'icon' => 'ph-inbox',
    'compact' => false,
])

<div {{ $attributes->class([
    'cinema-card rounded-3xl border app-border text-center',
    'p-8 sm:p-12' => ! $compact,
    'p-6' => $compact,
]) }}>
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
        <i class="ph-fill {{ $icon }} text-4xl"></i>
    </div>
    <h2 class="mt-5 text-2xl font-extrabold app-text">{{ $title }}</h2>
    <p class="mx-auto mt-2 max-w-xl app-muted">{{ $description }}</p>
    @if(trim((string) $slot) !== '')
        <div class="mt-6 flex flex-wrap justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
