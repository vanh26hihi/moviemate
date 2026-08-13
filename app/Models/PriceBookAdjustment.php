<?php

namespace App\Models;

use App\Exceptions\PriceBookException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PriceBookAdjustment extends Model
{
    public const DIMENSIONS = [
        'seat_type', 'room_type', 'time_window', 'weekend', 'holiday', 'cinema', 'room',
    ];

    protected $fillable = [
        'price_book_version_id', 'dimension', 'label', 'amount_vnd',
        'seat_type_id', 'room_type_id', 'cinema_id', 'room_id',
        'time_start', 'time_end', 'holiday_date_from', 'holiday_date_until', 'weekend_days',
    ];

    protected function casts(): array
    {
        return [
            'amount_vnd' => 'integer',
            'seat_type_id' => 'integer',
            'room_type_id' => 'integer',
            'cinema_id' => 'integer',
            'room_id' => 'integer',
            'holiday_date_from' => 'immutable_date',
            'holiday_date_until' => 'immutable_date',
            'weekend_days' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $assertDraft = function (PriceBookAdjustment $adjustment): void {
            $versionIds = array_unique(array_filter([
                (int) $adjustment->getOriginal('price_book_version_id'),
                (int) $adjustment->price_book_version_id,
            ]));
            if (PriceBookVersion::query()->whereIn('id', $versionIds)
                ->where('status', '!=', PriceBookVersion::STATUS_DRAFT)->exists()) {
                throw PriceBookException::immutable();
            }
        };

        self::creating($assertDraft);
        self::updating($assertDraft);
        self::deleting($assertDraft);
    }

    public function setHolidayDateFromAttribute(mixed $value): void
    {
        $this->attributes['holiday_date_from'] = $value === null
            ? null
            : CarbonImmutable::parse($value)->toDateString();
    }

    public function setHolidayDateUntilAttribute(mixed $value): void
    {
        $this->attributes['holiday_date_until'] = $value === null
            ? null
            : CarbonImmutable::parse($value)->toDateString();
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PriceBookVersion::class, 'price_book_version_id');
    }
}
