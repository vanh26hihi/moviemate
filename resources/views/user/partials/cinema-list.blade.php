<div class="lg:max-h-[520px] overflow-x-auto lg:overflow-x-hidden lg:overflow-y-auto p-3 sm:p-4 overscroll-x-contain">
    <div class="flex lg:block gap-3 lg:space-y-3">
        @forelse($cinemaList as $cinema)
            @php
                $isActiveCinema = $selectedCinema && (int) $cinema->id === (int) $selectedCinema->id;
                $showtimeCount = (int) ($cinema->active_showtimes_count ?? 0);
            @endphp

            <a data-showtime-filter
               href="{{ $homeShowtimeUrl(['city' => $selectedCity, 'brand' => $selectedBrand, 'cinema_id' => $cinema->id, 'date' => $selectedDate]) }}"
               class="block min-w-[17rem] max-w-[82vw] lg:max-w-none lg:min-w-0 w-full text-left rounded-3xl border p-4 transition-all duration-200 {{ $isActiveCinema ? 'border-brand-start/60 bg-gradient-to-r from-brand-start/15 to-brand-end/10 shadow-lg shadow-brand-start/10' : 'app-border app-secondary hover:border-brand-start/45 hover:bg-brand-start/5 hover:-translate-y-0.5' }}">