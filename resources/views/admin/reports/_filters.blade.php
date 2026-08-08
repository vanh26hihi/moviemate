<section class="app-card mb-6 rounded-2xl border app-border p-4 sm:p-5" aria-labelledby="report-filter-title">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 id="report-filter-title" class="font-black app-text">Bộ lọc báo cáo</h2>
            <p class="mt-1 text-xs app-muted">Đang xem {{ $scope->from->format('d/m/Y') }} – {{ $scope->to->format('d/m/Y') }}</p>
        </div>
        <span class="rounded-full app-secondary px-3 py-1 text-xs font-bold text-brand-start">
            {{ $scope->selectedCinemaId ? $scope->cinemas->first()?->name : 'Tất cả chi nhánh được phép' }}
        </span>
    </div>
    <form method="GET" action="{{ $filterAction }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <label class="text-sm font-bold app-text">Từ ngày
            <input class="cinema-input mt-1" type="date" name="from" value="{{ $scope->from->toDateString() }}" required>
        </label>
        <label class="text-sm font-bold app-text">Đến ngày
            <input class="cinema-input mt-1" type="date" name="to" value="{{ $scope->to->toDateString() }}" required>
        </label>
        <label class="text-sm font-bold app-text">Chi nhánh
            <select class="cinema-input mt-1" name="cinema">
                <option value="all" @selected($scope->selectedCinemaId === null)>Tất cả chi nhánh</option>
                @foreach($cinemas as $cinema)
                    <option value="{{ $cinema->id }}" @selected($scope->selectedCinemaId === $cinema->id)>{{ $cinema->name }}{{ $cinema->status === 'active' ? '' : ' (lịch sử)' }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-bold app-text">Kênh bán
            <select class="cinema-input mt-1" name="sales_channel">
                <option value="">Tất cả kênh</option>
                <option value="online" @selected($scope->salesChannel === 'online')>Trực tuyến</option>
                <option value="counter" @selected($scope->salesChannel === 'counter')>Tại quầy</option>
            </select>
        </label>
        <label class="text-sm font-bold app-text">Thanh toán
            <select class="cinema-input mt-1" name="provider">
                <option value="">Tất cả phương thức</option>
                <option value="vnpay" @selected($scope->provider === 'vnpay')>VNPAY</option>
                <option value="zalopay" @selected($scope->provider === 'zalopay')>ZaloPay</option>
                <option value="payos" @selected($scope->provider === 'payos')>payOS</option>
                <option value="counter_cash" @selected($scope->provider === 'counter_cash')>Tiền mặt tại quầy</option>
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button class="btn-primary min-h-11 flex-1" type="submit"><i class="ph ph-funnel"></i>Áp dụng</button>
            <a class="btn-secondary min-h-11" href="{{ $filterAction }}" aria-label="Xóa bộ lọc"><i class="ph ph-x"></i></a>
        </div>
        @if(($detailed ?? false) === true)
            <label class="text-sm font-bold app-text sm:col-span-2 xl:col-span-2">Xếp hạng phim theo
                <select class="cinema-input mt-1" name="metric" onchange="this.form.submit()">
                    <option value="revenue" @selected($scope->metric === 'revenue')>Doanh thu đơn hàng</option>
                    <option value="logical_tickets" @selected($scope->metric === 'logical_tickets')>Đơn vị vé</option>
                    <option value="physical_seats" @selected($scope->metric === 'physical_seats')>Số chỗ</option>
                </select>
            </label>
        @else
            <input type="hidden" name="metric" value="{{ $scope->metric }}">
        @endif
    </form>
    @if($errors->any())<p class="mt-3 text-sm font-bold text-error" role="alert">{{ $errors->first() }}</p>@endif
</section>
