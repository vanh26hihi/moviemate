@props([
    'showtime' => null,
    'basePrice' => null,
    'vipPrice' => null,
    'couplePrice' => null,
    'currency' => 'đ',
    'showComparison' => true,
    'showNotice' => true,
    'compact' => false,
])

@php
    $resolvedBasePrice = $basePrice
        ?? data_get($showtime, 'price')
        ?? data_get($showtime, 'base_price')
        ?? 0;

    $resolvedVipPrice = $vipPrice
        ?? data_get($showtime, 'vip_price');

    $resolvedCouplePrice = $couplePrice
        ?? data_get($showtime, 'couple_price');

    $resolvedBasePrice = max(
        0,
        (float) $resolvedBasePrice
    );

    $resolvedVipPrice = $resolvedVipPrice !== null
        ? max(0, (float) $resolvedVipPrice)
        : null;

    $resolvedCouplePrice = $resolvedCouplePrice !== null
        ? max(0, (float) $resolvedCouplePrice)
        : null;

    $formatPrice = function ($price) use ($currency) {
        return number_format(
            $price,
            0,
            ',',
            '.'
        ) . $currency;
    };

    $priceItems = collect([
        [
            'key' => 'standard',
            'label' => 'Ghế thường',
            'description' => 'Mức giá tiêu chuẩn',
            'price' => $resolvedBasePrice,
            'icon' => 'ph-armchair',
            'accent' => 'brand',
        ],

        $resolvedVipPrice !== null
            ? [
                'key' => 'vip',
                'label' => 'Ghế VIP',
                'description' => 'Vị trí đẹp, trải nghiệm tốt hơn',
                'price' => $resolvedVipPrice,
                'icon' => 'ph-star',
                'accent' => 'warning',
            ]
            : null,

        $resolvedCouplePrice !== null
            ? [
                'key' => 'couple',
                'label' => 'Ghế đôi',
                'description' => 'Ghế dành cho hai người',
                'price' => $resolvedCouplePrice,
                'icon' => 'ph-heart',
                'accent' => 'ai',
            ]
            : null,
    ])->filter();

    $minimumPrice = $priceItems
        ->pluck('price')
        ->min();

    $maximumPrice = $priceItems
        ->pluck('price')
        ->max();

    $hasMultiplePrices = $priceItems->count() > 1;

    $priceRange = $hasMultiplePrices
        ? $formatPrice($minimumPrice)
            . ' - '
            . $formatPrice($maximumPrice)
        : $formatPrice($minimumPrice ?? 0);
@endphp


<section
    {{ $attributes->class([
        'relative overflow-hidden rounded-3xl border app-border app-card',
    ]) }}
    data-showtime-price-card
>

    {{-- HEADER --}}
    <header
        class="{{ $compact ? 'p-4' : 'p-5 sm:p-6' }} border-b app-border"
    >

        <div
            class="flex items-start justify-between gap-4"
        >

            <div
                class="flex items-start gap-3"
            >

                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                >
                    <i
                        class="ph ph-ticket text-xl"
                        aria-hidden="true"
                    ></i>
                </span>


                <div>

                    <p
                        class="text-[10px] font-black uppercase tracking-[0.18em] app-muted"
                    >
                        Giá vé
                    </p>


                    <h3
                        class="mt-1 text-lg font-black app-heading"
                    >
                        Giá theo loại ghế
                    </h3>


                    @unless($compact)

                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Giá cuối cùng có thể thay đổi theo
                            loại ghế và chương trình khuyến mãi.
                        </p>

                    @endunless

                </div>

            </div>


            <div
                class="text-right"
            >

                <p
                    class="text-[9px] font-black uppercase tracking-wide app-muted"
                >
                    Giá từ
                </p>


                <p
                    class="mt-1 text-xl font-black text-brand-start"
                >
                    {{ $formatPrice($minimumPrice ?? 0) }}
                </p>

            </div>

        </div>

    </header>


    {{-- PRICE RANGE HERO --}}
    @if(!$compact)

        <div
            class="border-b app-border p-5 sm:p-6"
        >

            <div
                class="relative overflow-hidden rounded-3xl border border-brand-start/20 bg-brand-start/5 p-5"
            >

                <div
                    class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-brand-start/10 blur-3xl"
                    aria-hidden="true"
                ></div>


                <div
                    class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >

                    <div>

                        <p
                            class="text-[10px] font-black uppercase tracking-wider text-brand-start"
                        >
                            Khoảng giá
                        </p>


                        <p
                            class="mt-2 text-2xl font-black app-heading"
                        >
                            {{ $priceRange }}
                        </p>


                        <p
                            class="mt-1 text-xs app-muted"
                        >
                            Tùy thuộc vào loại ghế được chọn.
                        </p>

                    </div>


                    <span
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                    >
                        <i
                            class="ph ph-currency-circle-dollar text-2xl"
                            aria-hidden="true"
                        ></i>
                    </span>

                </div>

            </div>

        </div>

    @endif


    {{-- PRICE TYPES --}}
    <div
        class="{{ $compact ? 'p-4' : 'p-5 sm:p-6' }}"
    >

        <div
            class="grid gap-3 {{ $priceItems->count() >= 3 ? 'lg:grid-cols-3' : 'sm:grid-cols-2' }}"
        >

            @foreach($priceItems as $item)

                @php
                    $isStandard =
                        $item['key'] === 'standard';

                    $isVip =
                        $item['key'] === 'vip';

                    $isCouple =
                        $item['key'] === 'couple';

                    $iconClass = match ($item['accent']) {
                        'warning' =>
                            'bg-warning/10 text-warning',

                        'ai' =>
                            'bg-ai-start/10 text-ai-start',

                        default =>
                            'bg-brand-start/10 text-brand-start',
                    };

                    $priceClass = match ($item['accent']) {
                        'warning' =>
                            'text-warning',

                        'ai' =>
                            'text-ai-start',

                        default =>
                            'text-brand-start',
                    };
                @endphp


                <article
                    class="group relative overflow-hidden rounded-2xl border app-border app-card-soft p-4 transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                    data-seat-price="{{ $item['key'] }}"
                >

                    @if($isVip)

                        <span
                            class="absolute right-3 top-3 rounded-full border border-warning/20 bg-warning/5 px-2 py-1 text-[9px] font-black uppercase text-warning"
                        >
                            VIP
                        </span>

                    @endif


                    @if($isCouple)

                        <span
                            class="absolute right-3 top-3 rounded-full border border-ai-start/20 bg-ai-start/5 px-2 py-1 text-[9px] font-black uppercase text-ai-start"
                        >
                            2 người
                        </span>

                    @endif


                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $iconClass }}"
                    >
                        <i
                            class="ph {{ $item['icon'] }} text-xl"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <p
                        class="mt-4 text-sm font-black app-text"
                    >
                        {{ $item['label'] }}
                    </p>


                    <p
                        class="mt-1 min-h-[20px] text-[11px] leading-5 app-muted"
                    >
                        {{ $item['description'] }}
                    </p>


                    <div
                        class="mt-4 border-t app-border pt-4"
                    >

                        <p
                            class="text-[9px] font-black uppercase tracking-wide app-muted"
                        >
                            Giá vé
                        </p>


                        <p
                            class="mt-1 text-xl font-black {{ $priceClass }}"
                        >
                            {{ $formatPrice($item['price']) }}
                        </p>


                        @if($isCouple)

                            <p
                                class="mt-1 text-[10px] app-muted"
                            >
                                Giá cho một ghế đôi
                            </p>

                        @else

                            <p
                                class="mt-1 text-[10px] app-muted"
                            >
                                Giá cho một ghế
                            </p>

                        @endif

                    </div>


                    @if(
                        $showComparison
                        && $hasMultiplePrices
                        && !$isStandard
                    )

                        @php
                            $difference =
                                $item['price']
                                - $resolvedBasePrice;
                        @endphp


                        @if($difference > 0)

                            <div
                                class="mt-3 rounded-xl bg-slate-500/5 px-3 py-2"
                            >

                                <div
                                    class="flex items-center justify-between gap-2"
                                >

                                    <span
                                        class="text-[10px] app-muted"
                                    >
                                        Chênh lệch
                                    </span>


                                    <strong
                                        class="text-[10px] app-text"
                                    >
                                        +{{ $formatPrice($difference) }}
                                    </strong>

                                </div>

                            </div>

                        @endif

                    @endif

                </article>

            @endforeach

        </div>


        {{-- COMPARISON --}}
        @if(
            $showComparison
            && $hasMultiplePrices
            && !$compact
        )

            <div
                class="mt-5 rounded-3xl border app-border p-4 sm:p-5"
            >

                <div
                    class="flex items-start gap-3"
                >

                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-ai-start/10 text-ai-start"
                    >
                        <i
                            class="ph ph-chart-bar"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-sm font-black app-text"
                        >
                            So sánh nhanh
                        </p>


                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Giá ghế thường được dùng làm mốc
                            để so sánh các loại ghế khác.
                        </p>

                    </div>

                </div>


                <div
                    class="mt-4 space-y-3"
                >

                    @foreach($priceItems as $item)

                        @php
                            $relativePercentage =
                                $maximumPrice > 0
                                    ? max(
                                        5,
                                        min(
                                            100,
                                            (int) round(
                                                (
                                                    $item['price']
                                                    / $maximumPrice
                                                ) * 100
                                            )
                                        )
                                    )
                                    : 0;
                        @endphp


                        <div>

                            <div
                                class="mb-1.5 flex items-center justify-between gap-3"
                            >

                                <span
                                    class="text-xs font-bold app-muted"
                                >
                                    {{ $item['label'] }}
                                </span>


                                <strong
                                    class="text-xs app-text"
                                >
                                    {{ $formatPrice($item['price']) }}
                                </strong>

                            </div>


                            <div
                                class="h-2 overflow-hidden rounded-full bg-slate-500/10"
                            >

                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-brand-start to-brand-end"
                                    style="width: {{ $relativePercentage }}%"
                                ></div>

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>

        @endif


        {{-- NOTICE --}}
        @if($showNotice)

            <div
                class="mt-5 rounded-2xl border border-warning/20 bg-warning/5 p-4"
            >

                <div
                    class="flex items-start gap-3"
                >

                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning"
                    >
                        <i
                            class="ph ph-info"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-xs font-black text-warning"
                        >
                            Lưu ý về giá vé
                        </p>


                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Giá hiển thị là giá cơ bản của suất chiếu.
                            Tổng thanh toán thực tế được xác định
                            sau khi chọn ghế và áp dụng ưu đãi hợp lệ.
                        </p>

                    </div>

                </div>

            </div>

        @endif

    </div>


    {{-- FOOTER --}}
    <footer
        class="border-t app-border bg-slate-500/5 px-5 py-4"
    >

        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >

            <p
                class="flex items-center gap-2 text-[11px] app-muted"
            >
                <i
                    class="ph ph-shield-check text-success"
                    aria-hidden="true"
                ></i>

                Giá được xác nhận trước khi thanh toán.
            </p>


            <p
                class="text-[10px] font-black uppercase tracking-wide app-muted"
            >
                MovieMate
            </p>

        </div>

    </footer>


    <div
        class="sr-only"
        role="status"
    >
        Giá vé suất chiếu từ
        {{ $formatPrice($minimumPrice ?? 0) }}
        đến
        {{ $formatPrice($maximumPrice ?? 0) }}.
    </div>

</section>