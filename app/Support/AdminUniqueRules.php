<?php

namespace App\Support;

use App\Models\Cinema;
use App\Models\Genre;
use App\Models\PresentationFormat;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomLayoutTemplate;
use App\Models\RoomType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class AdminUniqueRules
{
    public const PROMOTION_CODE = 'promotion.code';

    public const CINEMA_CODE = 'cinema.code';

    public const ROOM_CODE = 'room.code';

    public const ROOM_TYPE_CODE = 'room-type.code';

    public const ROOM_TYPE_NAME = 'room-type.name';

    public const PRESENTATION_FORMAT_CODE = 'presentation-format.code';

    public const PRESENTATION_FORMAT_NAME = 'presentation-format.name';

    public const LAYOUT_TEMPLATE_CODE = 'layout-template.code';

    public const GENRE_SLUG = 'genre.slug';

    public const RULES = [
        self::PROMOTION_CODE,
        self::CINEMA_CODE,
        self::ROOM_CODE,
        self::ROOM_TYPE_CODE,
        self::ROOM_TYPE_NAME,
        self::PRESENTATION_FORMAT_CODE,
        self::PRESENTATION_FORMAT_NAME,
        self::LAYOUT_TEMPLATE_CODE,
        self::GENRE_SLUG,
    ];

    public static function promotionCode(?Promotion $promotion = null): Unique
    {
        return Rule::unique('promotions', 'code')->ignore($promotion?->getKey());
    }

    public static function cinemaCode(?Cinema $cinema = null): Unique
    {
        return Rule::unique('cinemas', 'code')->ignore($cinema?->getKey());
    }

    public static function roomCode(int $cinemaId, ?Room $room = null): Unique
    {
        return Rule::unique('rooms', 'code')
            ->where('cinema_id', $cinemaId)
            ->ignore($room?->getKey());
    }

    public static function roomTypeCode(?RoomType $roomType = null): Unique
    {
        return Rule::unique('room_types', 'code')->ignore($roomType?->getKey());
    }

    public static function roomTypeName(?RoomType $roomType = null): Unique
    {
        return Rule::unique('room_types', 'name')->ignore($roomType?->getKey());
    }

    public static function presentationFormatCode(?PresentationFormat $format = null): Unique
    {
        return Rule::unique('presentation_formats', 'code')->ignore($format?->getKey());
    }

    public static function presentationFormatName(?PresentationFormat $format = null): Unique
    {
        return Rule::unique('presentation_formats', 'name')->ignore($format?->getKey());
    }

    public static function layoutTemplateCode(?RoomLayoutTemplate $template = null): Unique
    {
        return Rule::unique('room_layout_templates', 'code')->ignore($template?->getKey());
    }

    public static function genreSlug(?Genre $genre = null): Unique
    {
        return Rule::unique('genres', 'slug')->ignore($genre?->getKey());
    }
}
