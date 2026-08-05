<?php
<div class="relative z-30 p-4 sm:p-5 lg:p-6 border-b app-border">
    <div class="flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <span class="text-sm font-bold app-muted">Vị trí</span>
                <details class="relative group w-full sm:w-auto">
                    <summary class="list-none cursor-pointer inline-flex items-center justify-between gap-3 w-full sm:w-auto px-4 py-2.5 rounded-2xl app-secondary border app-border app-text font-bold text-sm hover:border-brand-start transition-colors">
                        <span class="inline-flex items-center gap-2">
                            <i class="ph-fill ph-map-pin text-brand-start"></i>
                            {{ $cityLabel }}
                        </span>
                        <i class="ph ph-caret-down app-muted transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="absolute left-0 top-full mt-2 z-40 w-full sm:w-72 max-w-[calc(100vw-2rem)] rounded-2xl border app-border cinema-card p-2 shadow-2xl">
                        <a data-showtime-filter href="{{ $homeShowtimeUrl(['brand' => $selectedBrand, 'date' => $selectedDate]) }}" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-bold transition-colors {{ ! $selectedCity ? 'bg-brand-start/10 text-brand-start' : 'app-text hover:bg-brand-start/10 hover:text-brand-start' }}">
                            Tất cả thành phố
                            @if(! $selectedCity)<i class="ph-bold ph-check"></i>@endif
                        </a>
                        @foreach($cityOptions->keys() as $city)
                            <a data-showtime-filter href="{{ $homeShowtimeUrl(['city' => $city, 'brand' => $selectedBrand, 'date' => $selectedDate]) }}" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-bold transition-colors {{ $selectedCity === $city ? 'bg-brand-start/10 text-brand-start' : 'app-text hover:bg-brand-start/10 hover:text-brand-start' }}">
                                {{ $city }}
                                @if($selectedCity === $city)<i class="ph-bold ph-check"></i>@endif
                            </a>
                        @endforeach
                    </div>
                </details>
                <button type="button" id="nearbyCinemaBtn" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl border font-extrabold text-sm transition-colors {{ $isNearby ? 'bg-gradient-to-r from-brand-start to-brand-end border-transparent text-white shadow-lg shadow-brand-start/20' : 'bg-brand-start/10 border-brand-start/25 text-brand-start hover:bg-brand-start hover:text-white' }}">
                    <i class="ph-fill ph-navigation-arrow"></i>
                    <span data-nearby-label>Gần bạn</span>
                </button>
            </div>

            <div class="text-xs app-muted">
                {{ $cinemaList->count() }} rạp phù hợp · {{ $safeDate($selectedDate, 'd/m/Y') }}
            </div>
        </div>

         @if($isNearby)
            <div class="inline-flex items-center gap-2 text-xs font-bold text-brand-start bg-brand-start/10 border border-brand-start/20 rounded-2xl px-3 py-2 w-fit">
                <i class="ph-fill ph-crosshair"></i>
                Đang gợi ý rạp gần vị trí của bạn
            </div>
        @endif


        
    </div>
</div>