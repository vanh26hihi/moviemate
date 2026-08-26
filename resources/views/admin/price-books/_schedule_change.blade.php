@php
    $defaultChangeDate = now()->startOfDay()->greaterThan($version->effective_from)
        ? now()->format('Y-m-d')
        : $version->effective_from->format('Y-m-d');
    if ($version->effective_until && $defaultChangeDate >= $version->effective_until->format('Y-m-d')) {
        $defaultChangeDate = $version->effective_until->copy()->subDay()->format('Y-m-d');
    }
@endphp

<section class="cinema-card mt-6 overflow-hidden" aria-labelledby="safe-price-change-title">
    <div class="border-b app-border p-5 sm:p-6">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-start">Thay đổi giá an toàn</p>
        <h2 id="safe-price-change-title" class="mt-1 text-2xl font-extrabold app-heading">Bạn muốn giá thay đổi khi nào?</h2>
        <p class="mt-1 max-w-3xl text-sm app-muted">Chỉ cần chọn một cách, ngày áp dụng và nhập giá. Hệ thống tự chia lịch thành các khoảng liền nhau; bảng giá đã phát hành và giá của suất chiếu cũ không bị sửa.</p>
    </div>

    <form method="POST" action="{{ route('admin.price-books.versions.schedule-change.preview', $version) }}" class="p-5 sm:p-6">
        @csrf
        <fieldset>
            <legend class="admin-label">1. Chọn cách thay đổi</legend>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <label class="cursor-pointer rounded-2xl border app-border p-4 has-[:checked]:border-brand-start has-[:checked]:bg-brand-start/5">
                    <span class="flex items-start gap-3">
                        <input type="radio" name="change_kind" value="from_date" class="mt-1" @checked(old('change_kind', 'from_date') === 'from_date')>
                        <span>
                            <span class="block font-extrabold app-heading">Đổi giá từ một ngày trở đi</span>
                            <span class="mt-1 block text-sm app-muted">Giá cũ dùng đến hết ngày trước đó; giá mới tiếp tục đến cuối kỳ hiện tại.</span>
                        </span>
                    </span>
                </label>
                <label class="cursor-pointer rounded-2xl border app-border p-4 has-[:checked]:border-brand-start has-[:checked]:bg-brand-start/5">
                    <span class="flex items-start gap-3">
                        <input type="radio" name="change_kind" value="single_day" class="mt-1" @checked(old('change_kind') === 'single_day')>
                        <span>
                            <span class="block font-extrabold app-heading">Đặt giá đặc biệt cho một ngày</span>
                            <span class="mt-1 block text-sm app-muted">Giá nhập bên dưới thay giá theo loại vé trong ngày; các quy tắc theo giờ, cuối tuần, ngày lễ, loại phòng, phòng hoặc chi nhánh vẫn được giữ nguyên.</span>
                        </span>
                    </span>
                </label>
            </div>
            @error('change_kind')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
        </fieldset>

        <div class="mt-6 max-w-md">
            <label class="admin-label" for="schedule-change-date">2. Chọn ngày bắt đầu thay đổi</label>
            <input
                class="admin-input mt-2"
                id="schedule-change-date"
                type="date"
                name="change_date"
                min="{{ $version->effective_from->format('Y-m-d') }}"
                @if($version->effective_until) max="{{ $version->effective_until->copy()->subDay()->format('Y-m-d') }}" @endif
                value="{{ old('change_date', $defaultChangeDate) }}"
                required
            >
            <p class="mt-2 text-xs app-muted">Ngày phải nằm trong kỳ {{ $version->effective_from->format('d/m/Y') }}–{{ $version->effective_until ? $version->effective_until->copy()->subDay()->format('d/m/Y') : 'không giới hạn' }}.</p>
            @error('change_date')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <fieldset class="mt-6">
            <legend class="admin-label">3. Nhập giá mới theo loại vé</legend>
            <div class="mt-3 grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
                @foreach($seatTypes as $seatType)
                    @php
                        $adjustment = $seatAdjustments->get((int) $seatType->id);
                        $currentPrice = (int) $version->base_price_vnd + (int) ($adjustment?->amount_vnd ?? 0);
                        $seatTypeLabel = \App\Support\StatusLabel::for('seat_type', $seatType->code);
                    @endphp
                    <label class="rounded-2xl border app-border p-4" for="schedule-ticket-price-{{ $seatType->id }}">
                        <span class="block font-extrabold app-heading">{{ $seatTypeLabel }}</span>
                        <span class="mt-1 block text-xs app-muted">Hiện tại {{ number_format($currentPrice, 0, ',', '.') }} ₫ · {{ $seatType->is_pair ? 'một cặp hai ghế' : 'một ghế' }}</span>
                        <span class="relative mt-3 block">
                            <input class="admin-input pr-12 text-lg font-black tabular-nums" id="schedule-ticket-price-{{ $seatType->id }}" name="ticket_prices[{{ $seatType->id }}]" type="number" min="1" step="1" required value="{{ old('ticket_prices.'.$seatType->id, $currentPrice) }}">
                            <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 font-bold app-muted">₫</span>
                        </span>
                        @error('ticket_prices.'.$seatType->id)<span class="mt-2 block text-sm text-error">{{ $message }}</span>@enderror
                    </label>
                @endforeach
            </div>
        </fieldset>

        @error('ticket_prices')<p class="mt-3 text-sm text-error">{{ $message }}</p>@enderror
        @error('schedule_change')<p class="mt-3 text-sm text-error">{{ $message }}</p>@enderror

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button class="admin-btn-primary">Xem lịch giá trước khi áp dụng</button>
            <span class="text-xs app-muted">Bước này chỉ xem trước, chưa thay đổi dữ liệu.</span>
        </div>
    </form>
</section>
