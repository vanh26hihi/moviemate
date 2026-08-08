@props(['errors', 'heading' => 'Vui lòng kiểm tra lại các thông tin bên dưới.', 'except' => []])

@php
    $seenValidationMessages = [];
    $uniqueValidationMessages = [];
    foreach ($errors->getMessages() as $field => $messages) {
        if (in_array($field, $except, true)) {
            continue;
        }
        foreach ($messages as $message) {
            $normalized = preg_replace('/\s+/u', ' ', trim((string) $message)) ?? '';
            if ($normalized === '' || isset($seenValidationMessages[$normalized])) {
                continue;
            }
            $seenValidationMessages[$normalized] = true;
            $uniqueValidationMessages[] = $normalized;
        }
    }
@endphp

@if($uniqueValidationMessages !== [])
    <div {{ $attributes->merge(['class' => 'flash-banner-error flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm']) }} role="alert" aria-live="assertive" tabindex="-1">
        <i class="ph-fill ph-warning-octagon mt-0.5 shrink-0 text-xl" aria-hidden="true"></i>
        <div class="min-w-0 safe-break">
            <p class="font-extrabold">{{ $heading }}</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">@foreach($uniqueValidationMessages as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    </div>
@endif
