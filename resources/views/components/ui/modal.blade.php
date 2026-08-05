@props(['id', 'title'])
<div id="{{ $id }}" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" hidden>
    <div class="cinema-card w-full max-w-lg rounded-3xl p-6 sm:p-8">
        <div class="flex items-center justify-between gap-4 border-b app-border pb-4">
            <h2 id="{{ $id }}-title" class="text-xl font-extrabold app-text">{{ $title }}</h2>
            <button type="button" data-modal-close="{{ $id }}" class="h-10 w-10 rounded-xl app-secondary app-muted hover:text-brand-start" aria-label="Đóng"><i class="ph-bold ph-x"></i></button>
        </div>
        <div class="pt-5">{{ $slot }}</div>
    </div>
</div>
