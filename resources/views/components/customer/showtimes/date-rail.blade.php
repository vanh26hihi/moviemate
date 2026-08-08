@props(['dates', 'selectedDate'])
<fieldset>
    <legend class="sr-only">Chọn ngày xem lịch</legend>
    <div class="flex gap-2 overflow-x-auto pb-2" role="list">
        @foreach($dates as $date)
            @php($active = $selectedDate === $date['date'])
            <button type="submit" name="date" value="{{ $date['date'] }}" aria-pressed="{{ $active ? 'true' : 'false' }}"
                class="shrink-0 min-w-20 rounded-2xl border px-4 py-3 text-center focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-start/30 {{ $active ? 'border-brand-start bg-brand-start text-white' : 'app-border app-secondary app-text' }}">
                <span class="block text-lg font-black">{{ $date['day'] }}</span>
                <span class="block text-xs font-bold">{{ $date['label'] }}</span>
            </button>
        @endforeach
    </div>
</fieldset>
