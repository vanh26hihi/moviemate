@props([
    'src' => null,
    'alt' => '',
    'imageClass' => 'h-full w-full object-cover',
    'fallbackClass' => 'fallback-poster',
    'fallbackTitle' => 'MovieMate',
    'fallbackText' => null,
])

@if($src)
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        class="{{ $imageClass }}"
        loading="lazy"
        data-movie-media
        onerror="this.hidden=true;this.nextElementSibling.hidden=false"
    >
    <div class="{{ $fallbackClass }}" data-movie-media-fallback hidden>
        <i class="ph-fill ph-film-slate" aria-hidden="true"></i>
        <strong>{{ $fallbackTitle }}</strong>
        @if($fallbackText)<span>{{ $fallbackText }}</span>@endif
    </div>
@else
    <div class="{{ $fallbackClass }}" data-movie-media-fallback>
        <i class="ph-fill ph-film-slate" aria-hidden="true"></i>
        <strong>{{ $fallbackTitle }}</strong>
        @if($fallbackText)<span>{{ $fallbackText }}</span>@endif
    </div>
@endif
