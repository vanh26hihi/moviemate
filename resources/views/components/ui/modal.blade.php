@props(['id', 'title', 'descriptionId' => null, 'variant' => 'default'])
@php($solidAdmin = $variant === 'admin-solid')
<div id="{{ $id }}" @class([
    'fixed inset-0 hidden place-items-center overflow-y-auto backdrop-blur-sm',
    'z-[120] overscroll-contain bg-black/75 room-type-modal-overlay p-3 sm:p-4' => $solidAdmin,
    'z-[100] bg-black/70 p-4' => ! $solidAdmin,
]) role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" @if($descriptionId) aria-describedby="{{ $descriptionId }}" @endif data-modal @if($solidAdmin) data-room-type-modal @endif hidden>
    <div @class([
        'my-auto overflow-y-auto rounded-3xl shadow-2xl',
        'room-type-modal-panel max-h-[90dvh] w-[calc(100vw-1.5rem)] max-w-xl border app-card app-border p-5 sm:w-full sm:p-7' => $solidAdmin,
        'cinema-card max-h-[calc(100dvh-2rem)] w-full max-w-lg p-6 sm:p-8' => ! $solidAdmin,
    ]) data-modal-panel tabindex="-1">
        <div class="flex items-center justify-between gap-4 border-b app-border pb-4">
            <h2 id="{{ $id }}-title" class="text-xl font-extrabold app-text">{{ $title }}</h2>
            <button type="button" data-modal-close="{{ $id }}" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border app-border app-secondary app-muted transition hover:border-brand-start hover:text-brand-start focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-start" aria-label="Đóng"><i class="ph-bold ph-x" aria-hidden="true"></i></button>
        </div>
        <div class="pt-5">{{ $slot }}</div>
    </div>
</div>
