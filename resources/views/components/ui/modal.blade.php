@props(['id', 'title', 'descriptionId' => null])
<div id="{{ $id }}" class="fixed inset-0 z-[100] hidden place-items-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" @if($descriptionId) aria-describedby="{{ $descriptionId }}" @endif data-modal hidden>
    <div class="cinema-card my-auto max-h-[calc(100dvh-2rem)] w-full max-w-lg overflow-y-auto rounded-3xl p-6 shadow-2xl sm:p-8" data-modal-panel tabindex="-1">
        <div class="flex items-center justify-between gap-4 border-b app-border pb-4">
            <h2 id="{{ $id }}-title" class="text-xl font-extrabold app-text">{{ $title }}</h2>
            <button type="button" data-modal-close="{{ $id }}" class="h-10 w-10 rounded-xl app-secondary app-muted hover:text-brand-start" aria-label="Đóng"><i class="ph-bold ph-x"></i></button>
        </div>
        <div class="pt-5">{{ $slot }}</div>
    </div>
</div>
