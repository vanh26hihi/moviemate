@props(['current'])

@php
    $steps = [
        'seat' => ['label' => 'Ghế', 'number' => 1],
        'food' => ['label' => 'Đồ ăn', 'number' => 2],
        'review' => ['label' => 'Xác nhận', 'number' => 3],
        'payment' => ['label' => 'Thanh toán', 'number' => 4],
    ];
    $currentIndex = array_search($current, array_keys($steps), true);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;
@endphp

<nav {{ $attributes->class(['checkout-progress']) }} aria-label="Tiến trình đặt vé">
    <ol class="grid grid-cols-4">
        @foreach($steps as $key => $step)
            @php
                $index = $loop->index;
                $complete = $index < $currentIndex;
                $active = $index === $currentIndex;
            @endphp
            <li @class([
                'checkout-progress__step',
                'is-complete' => $complete,
                'is-active' => $active,
            ]) @if($active) aria-current="step" @endif>
                <span class="checkout-progress__rail" aria-hidden="true"></span>
                <span class="checkout-progress__marker" aria-hidden="true">
                    @if($complete)
                        <i class="ph-bold ph-check"></i>
                    @else
                        {{ $step['number'] }}
                    @endif
                </span>
                <span class="checkout-progress__label">{{ $step['label'] }}</span>
                @if($complete)<span class="sr-only">đã hoàn tất</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
