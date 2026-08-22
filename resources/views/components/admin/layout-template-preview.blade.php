@props(['template'])

@php
    $cells = $template->cells;
    $byCoordinate = $cells->keyBy(fn ($cell) => $cell->x_position.':'.$cell->y_position);
    $rowLabel = function (int $index): string {
        $label = '';
        while ($index > 0) {
            $index--;
            $label = chr(65 + ($index % 26)).$label;
            $index = intdiv($index, 26);
        }

        return $label;
    };
@endphp

<div class="max-w-full overflow-x-auto rounded-2xl border app-border app-bg p-4 sm:p-6" tabindex="0" aria-label="Sơ đồ ghế chỉ đọc, có thể cuộn ngang">
    <div class="mx-auto w-max min-w-fit">
        @if($template->screen_position === 'top')
            <div class="layout-template-screen" aria-label="Màn hình ở phía trên"><span>MÀN HÌNH</span></div>
        @endif

        <div class="layout-template-grid" role="grid" aria-label="Sơ đồ {{ $template->rows }} hàng và {{ $template->columns }} cột" style="grid-template-columns: repeat({{ $template->columns }}, 3rem)">
            @for($y = 1; $y <= $template->rows; $y++)
                @for($x = 1; $x <= $template->columns; $x++)
                    @php
                        $cell = $byCoordinate->get($x.':'.$y);
                        $coordinate = $rowLabel($y).$x;
                        $kind = ! $cell
                            ? 'empty'
                            : ($cell->cell_type === 'aisle'
                                ? 'aisle'
                                : ($cell->cell_type === 'blocked'
                                    ? 'blocked'
                                    : ($cell->seat_type === 'vip'
                                    ? 'vip'
                                    : ($cell->seat_type === 'couple' ? 'couple' : 'normal'))));
                        $pairPosition = $cell?->metadata['pair_position'] ?? null;
                        $label = match ($kind) {
                            'empty' => "Ô trống {$coordinate}",
                            'aisle' => "Lối đi {$coordinate}",
                            'blocked' => "Vật cản cố định {$coordinate}, vị trí cấu trúc không bố trí ghế",
                            'vip' => 'Ghế VIP '.$cell->seat_label,
                            'couple' => 'Ghế đôi '.$cell->seat_label,
                            default => 'Ghế thường '.$cell->seat_label,
                        };
                    @endphp
                    <span role="gridcell" aria-label="{{ $label }}" title="{{ $label }}" @class([
                        'layout-template-seat',
                        'is-empty' => $kind === 'empty',
                        'is-aisle' => $kind === 'aisle',
                        'is-blocked' => $kind === 'blocked',
                        'is-normal' => $kind === 'normal',
                        'is-vip' => $kind === 'vip',
                        'is-couple' => $kind === 'couple',
                        'is-couple-left' => $kind === 'couple' && $pairPosition === 'left',
                        'is-couple-right' => $kind === 'couple' && $pairPosition === 'right',
                    ])>
                        @if($kind === 'aisle')
                            <i class="ph ph-arrows-down-up" aria-hidden="true"></i>
                        @elseif($kind === 'blocked')
                            <i class="ph ph-bricks" aria-hidden="true"></i>
                        @elseif($cell)
                            {{ $cell->seat_label }}
                            @if($kind === 'vip')<span class="layout-template-seat-accent" aria-hidden="true">★</span>@endif
                        @endif
                    </span>
                @endfor
            @endfor
        </div>

        @if($template->screen_position === 'bottom')
            <div class="layout-template-screen" aria-label="Màn hình ở phía dưới"><span>MÀN HÌNH</span></div>
        @endif
    </div>
</div>
