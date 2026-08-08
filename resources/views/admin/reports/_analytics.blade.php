@php
    $money = fn (int $value): string => number_format($value, 0, ',', '.').' ₫';
    $cards = [
        ['label' => 'Doanh thu', 'value' => $money($summary['revenue']), 'note' => 'Đã xác minh/thu tiền trong kỳ', 'icon' => 'ph-currency-circle-dollar', 'color' => 'text-success'],
        ['label' => 'Đơn vị vé bán', 'value' => number_format($summary['logicalTickets'], 0, ',', '.'), 'note' => 'Ghế đôi tính một đơn vị', 'icon' => 'ph-ticket', 'color' => 'text-brand-start'],
        ['label' => 'Số chỗ', 'value' => number_format($summary['physicalSeats'], 0, ',', '.'), 'note' => 'Chỗ vật lý đã phân bổ', 'icon' => 'ph-armchair', 'color' => 'text-ai-start'],
        ['label' => 'Đơn đã thanh toán', 'value' => number_format($summary['paidBookings'], 0, ',', '.'), 'note' => 'Một giao dịch có bằng chứng/đơn', 'icon' => 'ph-receipt', 'color' => 'text-warning'],
        ['label' => 'Suất chiếu', 'value' => number_format($summary['showtimes'], 0, ',', '.'), 'note' => 'Theo ngày bắt đầu suất chiếu', 'icon' => 'ph-video-camera', 'color' => 'text-blue-400'],
    ];
@endphp

<section aria-label="Chỉ số trong khoảng đã chọn" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
    @foreach($cards as $card)
        <article class="app-card rounded-2xl border app-border p-5">
            <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide app-muted">{{ $card['label'] }}</p><p class="mt-3 text-2xl font-black {{ $card['color'] }}">{{ $card['value'] }}</p></div><i class="ph {{ $card['icon'] }} text-2xl {{ $card['color'] }}" aria-hidden="true"></i></div>
            <p class="mt-3 text-xs app-muted">{{ $card['note'] }}</p>
        </article>
    @endforeach
</section>

@unless($hasPeriodData)
    <div class="mt-6 rounded-2xl border border-warning/30 bg-warning/5 p-4 text-sm font-bold text-warning" role="status">Chưa có dữ liệu trong khoảng thời gian đã chọn.</div>
@endunless

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section class="app-card rounded-2xl border app-border p-5 sm:p-6 xl:col-span-2" aria-labelledby="revenue-series-title">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="revenue-series-title" class="text-xl font-black app-text">Doanh thu theo ngày thu tiền</h2><p class="mt-1 text-xs app-muted">Online dùng verified_at; tiền mặt dùng settled_at theo múi giờ chi nhánh.</p></div><strong class="text-success">{{ $money($summary['revenue']) }}</strong></div>
        <div class="mt-6 overflow-x-auto pb-2" role="region" aria-label="Biểu đồ doanh thu theo ngày">
            <div class="flex h-64 min-w-max items-end gap-2 border-b app-border px-2">
                @foreach($revenueSeries as $day)
                    <div class="flex h-full w-10 flex-col justify-end text-center" title="{{ $day['label'] }}: {{ $money($day['revenue']) }}">
                        <div class="mx-auto w-6 rounded-t bg-gradient-to-t from-brand-start to-brand-end" style="height: {{ $day['heightPercent'] }}%" aria-hidden="true"></div>
                        <span class="mt-2 text-[10px] app-muted">{{ $day['label'] }}</span>
                        <span class="sr-only">{{ $money($day['revenue']) }}, {{ $day['transactions'] }} giao dịch</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <aside class="app-card rounded-2xl border app-border p-5 sm:p-6" aria-labelledby="attention-title">
        <h2 id="attention-title" class="text-xl font-black app-text">Giao dịch cần hỗ trợ</h2>
        <p class="mt-1 text-xs app-muted">Trạng thái hiện tại, không cộng vào doanh thu.</p>
        <p class="mt-6 text-4xl font-black {{ $attention['total'] ? 'text-warning' : 'text-success' }}">{{ number_format($attention['total']) }}</p>
        <dl class="mt-5 grid grid-cols-2 gap-3"><div class="app-secondary rounded-xl p-3"><dt class="text-xs app-muted">Chưa xác định</dt><dd class="mt-1 font-black">{{ $attention['unresolved'] }}</dd></div><div class="app-secondary rounded-xl p-3"><dt class="text-xs app-muted">Cần xem xét</dt><dd class="mt-1 font-black">{{ $attention['review'] }}</dd></div></dl>
        @can('payments.reconcile')<a class="mt-5 inline-flex font-bold text-brand-start" href="{{ route('admin.payment-reconciliation.index') }}">Mở đối soát <i class="ph ph-arrow-right ml-1"></i></a>@endcan
    </aside>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="app-card rounded-2xl border app-border p-5 sm:p-6" aria-labelledby="top-movies-title">
        <h2 id="top-movies-title" class="text-xl font-black app-text">Top phim</h2>
        <p class="mt-1 text-xs app-muted">Mặc định theo doanh thu đơn hàng đã thu; phim lưu trữ vẫn được giữ trong lịch sử.</p>
        <div class="mt-5 space-y-3">@forelse($topMovies as $movie)<article class="flex items-center gap-3 rounded-xl app-secondary p-3"><span class="w-7 font-black text-brand-start">#{{ $loop->iteration }}</span><div class="min-w-0 flex-1"><p class="truncate font-bold app-text">{{ $movie['title'] }}</p><p class="text-xs app-muted">{{ $movie['logical_tickets'] }} đơn vị vé · {{ $movie['physical_seats'] }} chỗ · {{ $movie['booking_count'] }} đơn</p></div><strong class="shrink-0 text-right text-sm text-success">{{ $money($movie['revenue']) }}</strong></article>@empty<p class="rounded-xl border border-dashed app-border p-6 text-center app-muted">Chưa có dữ liệu phim trong kỳ.</p>@endforelse</div>
    </section>
    <section class="app-card rounded-2xl border app-border p-5 sm:p-6" aria-labelledby="today-showtimes-title">
        <div class="flex items-start justify-between gap-3"><div><h2 id="today-showtimes-title" class="text-xl font-black app-text">Lịch chiếu hôm nay</h2><p class="mt-1 text-xs app-muted">Luôn theo ngày nghiệp vụ hiện tại của từng chi nhánh.</p></div>@can('showtimes.view')<a class="text-sm font-bold text-brand-start" href="{{ route('admin.showtimes.index') }}">Vận hành</a>@endcan</div>
        <div class="mt-5 max-h-96 space-y-3 overflow-y-auto">@forelse($todayShowtimes as $showtime)<article class="rounded-xl app-secondary p-3"><div class="flex justify-between gap-3"><div><p class="font-bold app-text">{{ $showtime['movie'] }}</p><p class="text-xs app-muted">{{ $showtime['cinema'] }} · {{ $showtime['room'] }}</p></div><span class="text-sm font-black text-brand-start">{{ $showtime['start']->format('H:i') }}</span></div><p class="mt-2 text-xs app-muted">Kết thúc {{ $showtime['end']->format('H:i') }} · Vệ sinh đến {{ $showtime['cleaningUntil']->format('H:i') }} · {{ $showtime['logicalTickets'] }} vé / {{ $showtime['physicalSeats'] }} chỗ · {{ \App\Support\StatusLabel::for('showtime', $showtime['status']) }}</p></article>@empty<p class="rounded-xl border border-dashed app-border p-6 text-center app-muted">Hôm nay chưa có suất chiếu trong phạm vi được phép.</p>@endforelse</div>
    </section>
</div>

<div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
    <section class="app-card rounded-2xl border app-border p-5" aria-labelledby="current-movies-title"><h2 id="current-movies-title" class="text-lg font-black app-text">Phim đang chiếu</h2><p class="mt-1 text-xs app-muted">Có lịch chiếu hợp lệ trong kỳ đã chọn.</p><div class="mt-4 space-y-3">@forelse($currentMovies as $movie)<div class="rounded-xl app-secondary p-3"><p class="font-bold app-text">{{ $movie['title'] }}</p><p class="text-xs app-muted">{{ $movie['genres'] ?: 'Đang cập nhật thể loại' }} · {{ $movie['showtime_count'] }} suất · {{ $movie['logical_tickets'] }} vé</p></div>@empty<p class="app-muted">Chưa có phim đang chiếu phù hợp.</p>@endforelse</div></section>
    <section class="app-card rounded-2xl border app-border p-5" aria-labelledby="peak-title"><h2 id="peak-title" class="text-lg font-black app-text">Khung giờ cao điểm</h2><p class="mt-1 text-xs app-muted">Theo giờ bắt đầu suất chiếu, kể cả suất qua nửa đêm.</p><div class="mt-4 space-y-3">@foreach($peakTimes as $bucket)<div class="flex items-center justify-between rounded-xl app-secondary p-3"><span class="font-bold">{{ $bucket['label'] }}</span><span class="text-sm app-muted">{{ $bucket['logical_tickets'] }} vé · {{ $bucket['physical_seats'] }} chỗ</span></div>@endforeach</div></section>
    <section class="app-card rounded-2xl border app-border p-5" aria-labelledby="genre-title"><h2 id="genre-title" class="text-lg font-black app-text">Thể loại được quan tâm</h2><p class="mt-1 text-xs app-muted">Hiệu suất liên kết thể loại; phim nhiều thể loại đóng góp cho từng thể loại.</p><div class="mt-4 space-y-3">@forelse(array_slice($genres, 0, 8) as $genre)<div class="flex items-center justify-between rounded-xl app-secondary p-3"><span class="font-bold">{{ $genre['name'] }}</span><span class="text-sm app-muted">{{ $genre['logical_tickets'] }} vé · {{ $genre['showtime_count'] }} suất</span></div>@empty<p class="app-muted">Chưa có dữ liệu thể loại.</p>@endforelse</div></section>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="app-card rounded-2xl border app-border p-5" aria-labelledby="channel-title"><h2 id="channel-title" class="text-lg font-black app-text">Kênh bán</h2><div class="mt-4 overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm"><thead class="app-muted"><tr><th class="pb-3">Kênh</th><th class="pb-3">Đơn</th><th class="pb-3">Đơn vị vé</th><th class="pb-3 text-right">Doanh thu</th></tr></thead><tbody>@foreach($salesChannels as $channel)<tr class="border-t app-border"><td class="py-3 font-bold">{{ $channel['label'] }}</td><td>{{ $channel['bookings'] }}</td><td>{{ $channel['logical_tickets'] }} / {{ $channel['physical_seats'] }} chỗ</td><td class="text-right font-bold text-success">{{ $money($channel['revenue']) }}</td></tr>@endforeach</tbody></table></div></section>
    <section class="app-card rounded-2xl border app-border p-5" aria-labelledby="provider-title"><h2 id="provider-title" class="text-lg font-black app-text">Phương thức thanh toán</h2><div class="mt-4 overflow-x-auto"><table class="w-full min-w-[420px] text-left text-sm"><thead class="app-muted"><tr><th class="pb-3">Phương thức</th><th class="pb-3">Giao dịch</th><th class="pb-3 text-right">Doanh thu</th></tr></thead><tbody>@foreach($paymentMethods as $provider)<tr class="border-t app-border"><td class="py-3 font-bold">{{ $provider['label'] }}</td><td>{{ $provider['transactions'] }}</td><td class="text-right font-bold text-success">{{ $money($provider['revenue']) }}</td></tr>@endforeach</tbody></table></div></section>
</div>

<section class="app-card mt-6 rounded-2xl border app-border p-5" aria-labelledby="ticket-operations-title">
    <h2 id="ticket-operations-title" class="text-lg font-black app-text">Vận hành in vé & soát vé</h2><p class="mt-1 text-xs app-muted">Theo ngày bắt đầu suất chiếu; trạng thái in và bằng chứng soát vé được tính độc lập.</p>
    <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">@foreach([['Chưa in','unprinted'],['Đã in','printed'],['In lỗi','printFailed'],['Chờ/được phép in lại','printWaiting'],['Đã soát','checkedIn'],['Chưa soát','notCheckedIn'],['Tỷ lệ soát','checkinPercent']] as [$label,$key])<div class="rounded-xl app-secondary p-3"><dt class="text-xs app-muted">{{ $label }}</dt><dd class="mt-1 text-xl font-black app-text">{{ $ticketOperations[$key] }}{{ $key === 'checkinPercent' ? '%' : '' }}</dd></div>@endforeach</dl>
</section>

@if($detailed)
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    @foreach([['Đơn tạo tại quầy theo nhân viên', $counterCreators], ['Thu tiền tại quầy theo nhân viên', $counterSettlers]] as [$title, $actors])
        <section class="app-card rounded-2xl border app-border p-5"><h2 class="text-lg font-black app-text">{{ $title }}</h2><p class="mt-1 text-xs app-muted">Chỉ dùng đúng vai trò được ghi nhận; không phải bảng xếp hạng nhân sự.</p><div class="mt-4 overflow-x-auto"><table class="w-full min-w-[620px] text-left text-sm"><thead class="app-muted"><tr><th class="pb-3">Nhân viên</th><th class="pb-3">Chi nhánh</th><th class="pb-3">Đơn</th><th class="pb-3">Vé/chỗ</th><th class="pb-3 text-right">Tiền mặt</th></tr></thead><tbody>@forelse($actors as $actor)<tr class="border-t app-border"><td class="py-3 font-bold">{{ $actor['name'] }}</td><td>{{ $actor['cinema'] }}</td><td>{{ $actor['bookings'] }}</td><td>{{ $actor['logical_tickets'] }} / {{ $actor['physical_seats'] }}</td><td class="text-right font-bold text-success">{{ $money($actor['revenue']) }}</td></tr>@empty<tr><td colspan="5" class="py-6 text-center app-muted">Chưa có dữ liệu tại quầy trong kỳ.</td></tr>@endforelse</tbody></table></div></section>
    @endforeach
</div>
@endif

<p class="mt-5 text-xs app-muted">Tạo lúc {{ $generatedAt->format('d/m/Y H:i') }}. Doanh thu là tổng tiền đã thu, gồm đồ ăn khi đơn có đồ ăn; chỉ số vé/chỗ không gồm đồ ăn. Không triển khai hoàn tiền trong R9.</p>
