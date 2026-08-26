<section class="cinema-card overflow-hidden" aria-labelledby="simple-prices-title">
    <div class="border-b app-border p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-start">Bước 1</p>
                <h2 id="simple-prices-title" class="mt-1 text-2xl font-extrabold app-heading">Giá bán theo loại vé</h2>
                <p class="mt-1 text-sm app-muted">Giá ngày thường, chưa gồm phụ thu theo giờ, ngày lễ, chi nhánh hoặc phòng.</p>
            </div>
            <span class="status-badge border app-border">Giá khách trả</span>
        </div>
    </div>

    @if($canManagePriceBook && $version->status === 'draft')
        <form method="POST" action="{{ route('admin.price-books.versions.simple-prices.update', $version) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="base_price_vnd" value="{{ $version->base_price_vnd }}">
            <div class="grid gap-3 p-5 sm:p-6 lg:grid-cols-2 xl:grid-cols-3">
                @foreach($seatTypes as $seatType)
                    @php
                        $adjustment = $seatAdjustments->get((int) $seatType->id);
                        $ticketPrice = (int) $version->base_price_vnd + (int) ($adjustment?->amount_vnd ?? 0);
                        $seatTypeLabel = \App\Support\StatusLabel::for('seat_type', $seatType->code);
                    @endphp
                    <label class="rounded-2xl border app-border p-4" for="ticket-price-{{ $seatType->id }}">
                        <span class="flex items-start justify-between gap-3">
                            <span>
                                <span class="block font-extrabold app-heading">{{ $seatTypeLabel }}</span>
                                <span class="mt-1 block text-xs app-muted">{{ $seatType->is_pair ? 'Một cặp hai ghế · tính một lần' : 'Một ghế vật lý' }}</span>
                            </span>
                            @if($seatType->code === 'normal')<span class="status-badge border app-border">Giá chuẩn</span>@endif
                        </span>
                        <span class="relative mt-4 block">
                            <input class="admin-input pr-12 text-lg font-black tabular-nums" id="ticket-price-{{ $seatType->id }}" name="ticket_prices[{{ $seatType->id }}]" type="number" min="1" step="1" required value="{{ old('ticket_prices.'.$seatType->id, $ticketPrice) }}">
                            <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 font-bold app-muted">₫</span>
                        </span>
                        @error('ticket_prices.'.$seatType->id)<span class="mt-2 block text-sm text-error">{{ $message }}</span>@enderror
                    </label>
                @endforeach
            </div>

            <div class="border-t app-border p-5 sm:p-6">
                <h3 class="font-extrabold app-heading">Thời gian áp dụng</h3>
                <p class="mt-1 text-sm app-muted">Chọn ngày đầu tiên và ngày cuối cùng khách hàng được áp dụng bảng giá này.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="admin-label" for="effective-from">Bắt đầu áp dụng từ ngày</label>
                        <input class="admin-input" id="effective-from" type="date" name="effective_from" required value="{{ old('effective_from', $version->effective_from?->format('Y-m-d')) }}">
                        @error('effective_from')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="admin-label" for="effective-end-date">Áp dụng đến hết ngày</label>
                        <input class="admin-input" id="effective-end-date" type="date" name="effective_end_date" value="{{ old('effective_end_date', $version->effective_until?->copy()->subDay()->format('Y-m-d')) }}">
                        <p class="mt-1 text-xs app-muted">Để trống nếu chưa xác định ngày kết thúc.</p>
                        @error('effective_end_date')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <button class="admin-btn-primary mt-5">Lưu giá vé và thời gian áp dụng</button>
            </div>
        </form>
    @else
        <div class="grid gap-3 p-5 sm:p-6 lg:grid-cols-2 xl:grid-cols-3">
            @foreach($seatTypes as $seatType)
                @php
                    $adjustment = $seatAdjustments->get((int) $seatType->id);
                    $ticketPrice = (int) $version->base_price_vnd + (int) ($adjustment?->amount_vnd ?? 0);
                    $seatTypeLabel = \App\Support\StatusLabel::for('seat_type', $seatType->code);
                @endphp
                <article class="rounded-2xl border app-border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-extrabold app-heading">{{ $seatTypeLabel }}</h3>
                            <p class="mt-1 text-xs app-muted">{{ $seatType->is_pair ? 'Một cặp hai ghế · tính một lần' : 'Một ghế vật lý' }}</p>
                        </div>
                        @if($seatType->code === 'normal')<span class="status-badge border app-border">Giá chuẩn</span>@endif
                    </div>
                    <p class="mt-4 text-3xl font-black tabular-nums app-heading">{{ number_format($ticketPrice, 0, ',', '.') }} ₫</p>
                </article>
            @endforeach
        </div>
        <dl class="grid gap-4 border-t app-border p-5 text-sm sm:grid-cols-3 sm:p-6">
            <div><dt class="app-muted">Bắt đầu áp dụng</dt><dd class="mt-1 font-bold">{{ $version->effective_from?->format('d/m/Y') ?? 'Chưa chọn' }}</dd></div>
            <div><dt class="app-muted">Áp dụng đến hết</dt><dd class="mt-1 font-bold">{{ $version->effective_until ? $version->effective_until->copy()->subDay()->format('d/m/Y') : 'Không giới hạn' }}</dd></div>
            <div><dt class="app-muted">Giá chuẩn hệ thống</dt><dd class="mt-1 font-bold">{{ number_format((int) $version->base_price_vnd, 0, ',', '.') }} ₫</dd></div>
        </dl>
    @endif
</section>
