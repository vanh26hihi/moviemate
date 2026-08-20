@props(['dates', 'selectedDate'])
<fieldset class="min-w-0 max-w-full">
    <legend class="sr-only">Chọn ngày xem lịch</legend>
    <div class="flex w-full max-w-full gap-2 overflow-x-auto pb-2" role="list">
        @foreach($dates as $date)
            @php($active = $selectedDate === $date['date'])
            <button type="submit" name="date" value="{{ $date['date'] }}"
                data-showtime-date-chip
                aria-pressed="{{ $active ? 'true' : 'false' }}"
                @if($active) aria-current="date" @endif
                class="showtime-date-chip relative shrink-0 min-w-20 rounded-2xl border px-4 py-3 text-center">
                <span class="showtime-date-chip__indicator" aria-hidden="true"><i class="ph-bold ph-check"></i></span>
                <span class="block text-lg font-black">{{ $date['day'] }}</span>
                <span class="block text-xs font-bold">{{ $date['label'] }}</span>
            </button>
        @endforeach
    </div>
</fieldset>
