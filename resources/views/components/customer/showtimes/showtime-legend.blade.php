@props([
    'showTitle' => true,
    'showDescription' => true,
    'showSeatStates' => true,
    'showBookingStates' => true,
    'showTimeStates' => true,
    'compact' => false,
])

@php
    $bookingStates = [
        [
            'key' => 'available',
            'label' => 'Đang mở bán',
            'description' => 'Suất chiếu đang nhận đặt vé và khách hàng có thể tiếp tục chọn ghế.',
            'icon' => 'ph-ticket',
            'class' => 'border-success/20 bg-success/5 text-success',
            'iconClass' => 'bg-success/10 text-success',
        ],

        [
            'key' => 'unavailable',
            'label' => 'Không khả dụng',
            'description' => 'Suất chiếu hiện không nhận thêm lượt đặt vé mới.',
            'icon' => 'ph-lock',
            'class' => 'border-slate-500/20 bg-slate-500/5 text-slate-500',
            'iconClass' => 'bg-slate-500/10 text-slate-500',
        ],

        [
            'key' => 'cancelled',
            'label' => 'Đã hủy',
            'description' => 'Suất chiếu đã bị hủy và không còn hiệu lực để đặt vé.',
            'icon' => 'ph-x-circle',
            'class' => 'border-error/20 bg-error/5 text-error',
            'iconClass' => 'bg-error/10 text-error',
        ],
    ];

    $timeStates = [
        [
            'key' => 'starting_soon',
            'label' => 'Sắp bắt đầu',
            'description' => 'Suất chiếu sẽ bắt đầu trong thời gian ngắn.',
            'icon' => 'ph-clock-countdown',
            'class' => 'border-warning/20 bg-warning/5 text-warning',
            'iconClass' => 'bg-warning/10 text-warning',
        ],

        [
            'key' => 'started',
            'label' => 'Đã bắt đầu',
            'description' => 'Thời gian bắt đầu của suất chiếu đã qua.',
            'icon' => 'ph-play-circle',
            'class' => 'border-slate-500/20 bg-slate-500/5 text-slate-500',
            'iconClass' => 'bg-slate-500/10 text-slate-500',
        ],

        [
            'key' => 'finished',
            'label' => 'Đã kết thúc',
            'description' => 'Suất chiếu đã hoàn tất và chỉ còn phục vụ tra cứu.',
            'icon' => 'ph-check-circle',
            'class' => 'border-slate-500/20 bg-slate-500/5 text-slate-500',
            'iconClass' => 'bg-slate-500/10 text-slate-500',
        ],
    ];

    $seatStates = [
        [
            'key' => 'good',
            'label' => 'Còn nhiều ghế',
            'description' => 'Suất chiếu vẫn còn nhiều vị trí để lựa chọn.',
            'icon' => 'ph-armchair',
            'class' => 'border-success/20 bg-success/5 text-success',
            'iconClass' => 'bg-success/10 text-success',
        ],

        [
            'key' => 'limited',
            'label' => 'Còn ít ghế',
            'description' => 'Số lượng ghế trống đang giảm, nên chọn sớm.',
            'icon' => 'ph-warning-circle',
            'class' => 'border-warning/20 bg-warning/5 text-warning',
            'iconClass' => 'bg-warning/10 text-warning',
        ],

        [
            'key' => 'critical',
            'label' => 'Gần hết ghế',
            'description' => 'Chỉ còn rất ít ghế trống trong suất chiếu.',
            'icon' => 'ph-warning',
            'class' => 'border-error/20 bg-error/5 text-error',
            'iconClass' => 'bg-error/10 text-error',
        ],

        [
            'key' => 'sold_out',
            'label' => 'Hết ghế',
            'description' => 'Suất chiếu không còn ghế trống để đặt.',
            'icon' => 'ph-x-circle',
            'class' => 'border-error/20 bg-error/5 text-error',
            'iconClass' => 'bg-error/10 text-error',
        ],
    ];
@endphp


<section
    {{ $attributes->class([
        'overflow-hidden rounded-3xl border app-border app-card',
    ]) }}
    data-showtime-legend
>

    {{-- =====================================================
        HEADER
    ====================================================== --}}
    @if($showTitle)

        <header
            class="relative overflow-hidden border-b app-border p-5 sm:p-6"
        >

            <div
                class="pointer-events-none absolute inset-0 bg-gradient-to-r from-brand-start/5 via-transparent to-ai-start/5"
                aria-hidden="true"
            ></div>


            <div
                class="relative flex items-start gap-3"
            >

                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                >
                    <i
                        class="ph ph-info text-xl"
                        aria-hidden="true"
                    ></i>
                </span>


                <div>

                    <p
                        class="text-[10px] font-black uppercase tracking-[0.18em] app-muted"
                    >
                        Chú thích
                    </p>


                    <h3
                        class="mt-1 text-xl font-black app-heading"
                    >
                        Trạng thái suất chiếu
                    </h3>


                    @if($showDescription)

                        <p
                            class="mt-1 max-w-2xl text-sm leading-6 app-muted"
                        >
                            Các nhãn dưới đây giúp bạn nhận biết nhanh
                            tình trạng mở bán, thời gian và số ghế còn lại.
                        </p>

                    @endif

                </div>

            </div>

        </header>

    @endif


    <div
        class="{{ $compact ? 'space-y-4 p-4' : 'space-y-6 p-5 sm:p-6' }}"
    >

        {{-- =================================================
            BOOKING STATES
        ================================================== --}}
        @if($showBookingStates)

            <div>

                <div
                    class="flex items-center gap-2"
                >

                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"
                    >
                        <i
                            class="ph ph-ticket"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-xs font-black uppercase tracking-wider app-muted"
                        >
                            Trạng thái đặt vé
                        </p>

                        @unless($compact)

                            <p
                                class="mt-0.5 text-xs app-muted"
                            >
                                Cho biết suất chiếu hiện có thể đặt hay không.
                            </p>

                        @endunless

                    </div>

                </div>


                <div
                    class="mt-3 grid gap-3 md:grid-cols-3"
                >

                    @foreach($bookingStates as $state)

                        <article
                            class="rounded-2xl border app-border p-4"
                            data-showtime-legend-item="{{ $state['key'] }}"
                        >

                            <div
                                class="flex items-start gap-3"
                            >

                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $state['iconClass'] }}"
                                >
                                    <i
                                        class="ph {{ $state['icon'] }}"
                                        aria-hidden="true"
                                    ></i>
                                </span>


                                <div>

                                    <span
                                        class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-black {{ $state['class'] }}"
                                    >
                                        {{ $state['label'] }}
                                    </span>


                                    @unless($compact)

                                        <p
                                            class="mt-2 text-xs leading-5 app-muted"
                                        >
                                            {{ $state['description'] }}
                                        </p>

                                    @endunless

                                </div>

                            </div>

                        </article>

                    @endforeach

                </div>

            </div>

        @endif


        {{-- =================================================
            TIME STATES
        ================================================== --}}
        @if($showTimeStates)

            <div
                class="border-t app-border pt-5"
            >

                <div
                    class="flex items-center gap-2"
                >

                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-warning/10 text-warning"
                    >
                        <i
                            class="ph ph-clock"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-xs font-black uppercase tracking-wider app-muted"
                        >
                            Trạng thái thời gian
                        </p>

                        @unless($compact)

                            <p
                                class="mt-0.5 text-xs app-muted"
                            >
                                Cho biết suất chiếu đang ở giai đoạn nào theo thời gian.
                            </p>

                        @endunless

                    </div>

                </div>


                <div
                    class="mt-3 grid gap-3 md:grid-cols-3"
                >

                    @foreach($timeStates as $state)

                        <article
                            class="rounded-2xl border app-border p-4"
                            data-showtime-legend-item="{{ $state['key'] }}"
                        >

                            <div
                                class="flex items-start gap-3"
                            >

                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $state['iconClass'] }}"
                                >
                                    <i
                                        class="ph {{ $state['icon'] }}"
                                        aria-hidden="true"
                                    ></i>
                                </span>


                                <div>

                                    <span
                                        class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-black {{ $state['class'] }}"
                                    >
                                        {{ $state['label'] }}
                                    </span>


                                    @unless($compact)

                                        <p
                                            class="mt-2 text-xs leading-5 app-muted"
                                        >
                                            {{ $state['description'] }}
                                        </p>

                                    @endunless

                                </div>

                            </div>

                        </article>

                    @endforeach

                </div>

            </div>

        @endif


        {{-- =================================================
            SEAT STATES
        ================================================== --}}
        @if($showSeatStates)

            <div
                class="border-t app-border pt-5"
            >

                <div
                    class="flex items-center gap-2"
                >

                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-success/10 text-success"
                    >
                        <i
                            class="ph ph-armchair"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-xs font-black uppercase tracking-wider app-muted"
                        >
                            Tình trạng ghế
                        </p>

                        @unless($compact)

                            <p
                                class="mt-0.5 text-xs app-muted"
                            >
                                Mức độ còn trống của từng suất chiếu.
                            </p>

                        @endunless

                    </div>

                </div>


                <div
                    class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >

                    @foreach($seatStates as $state)

                        <article
                            class="group rounded-2xl border app-border p-4 transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                            data-showtime-legend-item="{{ $state['key'] }}"
                        >

                            <div
                                class="flex items-start justify-between gap-3"
                            >

                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl {{ $state['iconClass'] }}"
                                >
                                    <i
                                        class="ph {{ $state['icon'] }}"
                                        aria-hidden="true"
                                    ></i>
                                </span>


                                <span
                                    class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-black {{ $state['class'] }}"
                                >
                                    {{ $state['label'] }}
                                </span>

                            </div>


                            @unless($compact)

                                <p
                                    class="mt-3 text-xs leading-5 app-muted"
                                >
                                    {{ $state['description'] }}
                                </p>

                            @endunless

                        </article>

                    @endforeach

                </div>

            </div>

        @endif


        {{-- =================================================
            QUICK GUIDE
        ================================================== --}}
        @unless($compact)

            <div
                class="border-t app-border pt-5"
            >

                <div
                    class="rounded-3xl border border-brand-start/10 bg-brand-start/5 p-5"
                >

                    <div
                        class="flex items-start gap-3"
                    >

                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"
                        >
                            <i
                                class="ph ph-lightbulb"
                                aria-hidden="true"
                            ></i>
                        </span>


                        <div>

                            <p
                                class="font-black app-text"
                            >
                                Cách chọn suất nhanh
                            </p>


                            <p
                                class="mt-1 text-xs leading-5 app-muted"
                            >
                                Nếu muốn có nhiều lựa chọn ghế,
                                hãy ưu tiên các suất có nhãn
                                “Còn nhiều ghế” hoặc “Còn ghế”.
                            </p>

                        </div>

                    </div>


                    <div
                        class="mt-4 grid gap-3 md:grid-cols-3"
                    >

                        <div
                            class="rounded-2xl border app-border app-card p-4"
                        >

                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-start text-xs font-black text-white"
                            >
                                1
                            </span>


                            <p
                                class="mt-3 text-sm font-black app-text"
                            >
                                Xem trạng thái
                            </p>


                            <p
                                class="mt-1 text-xs leading-5 app-muted"
                            >
                                Chọn suất đang mở bán và chưa bắt đầu.
                            </p>

                        </div>


                        <div
                            class="rounded-2xl border app-border app-card p-4"
                        >

                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-ai-start text-xs font-black text-white"
                            >
                                2
                            </span>


                            <p
                                class="mt-3 text-sm font-black app-text"
                            >
                                Kiểm tra ghế
                            </p>


                            <p
                                class="mt-1 text-xs leading-5 app-muted"
                            >
                                Ưu tiên suất còn nhiều chỗ trống.
                            </p>

                        </div>


                        <div
                            class="rounded-2xl border app-border app-card p-4"
                        >

                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-success text-xs font-black text-white"
                            >
                                3
                            </span>


                            <p
                                class="mt-3 text-sm font-black app-text"
                            >
                                Chọn giờ
                            </p>


                            <p
                                class="mt-1 text-xs leading-5 app-muted"
                            >
                                Chọn khung giờ phù hợp rồi tiếp tục đặt vé.
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        @endunless

    </div>


    {{-- =====================================================
        FOOTER
    ====================================================== --}}
    <footer
        class="border-t app-border bg-slate-500/5 px-5 py-4 sm:px-6"
    >

        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >

            <p
                class="flex items-center gap-2 text-xs app-muted"
            >
                <i
                    class="ph ph-info text-brand-start"
                    aria-hidden="true"
                ></i>

                Trạng thái có thể thay đổi theo thời gian thực.
            </p>


            <span
                class="text-[10px] font-black uppercase tracking-wide app-muted"
            >
                MovieMate Showtime
            </span>

        </div>

    </footer>


    {{-- =====================================================
        ACCESSIBILITY
    ====================================================== --}}
    <div
        class="sr-only"
        role="note"
    >
        Chú thích các trạng thái của suất chiếu,
        gồm trạng thái đặt vé,
        thời gian và tình trạng ghế.
    </div>

</section>