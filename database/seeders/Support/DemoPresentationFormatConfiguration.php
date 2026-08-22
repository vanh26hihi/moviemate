<?php

namespace Database\Seeders\Support;

use App\Models\Room;

final class DemoPresentationFormatConfiguration
{
    /**
     * MovieMate-owned demo configuration. These values are not imported or inferred from TMDB.
     *
     * @var list<string>
     */
    public const THREE_D_MOVIE_SLUGS = [
        'spider-man-brand-new-day',
        'the-odyssey',
    ];

    /** @return list<string> */
    public static function roomCapabilityCodes(Room $room): array
    {
        if ($room->room_type === 'IMAX') {
            return ['2D', '3D'];
        }

        if ($room->cinema?->code === 'CG' && in_array($room->code, ['P02', 'DEMO'], true)) {
            return ['2D', '3D'];
        }

        return ['2D'];
    }
}
