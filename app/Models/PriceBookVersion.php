<?php

namespace App\Models;

use App\Exceptions\PriceBookException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PriceBookVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'price_book_id', 'version_number', 'status', 'base_price_vnd',
        'effective_from', 'effective_until', 'published_at', 'retired_at',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'base_price_vnd' => 'integer',
            'effective_from' => 'immutable_date',
            'effective_until' => 'immutable_date',
            'published_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (PriceBookVersion $version): void {
            $originalStatus = (string) $version->getOriginal('status');
            if ($originalStatus === self::STATUS_DRAFT) {
                return;
            }

            $economic = [
                'price_book_id', 'version_number', 'base_price_vnd', 'effective_from', 'effective_until',
                'published_at',
            ];
            if ($originalStatus === self::STATUS_RETIRED) {
                $economic[] = 'retired_at';
            }
            $invalidTransition = $version->status === self::STATUS_DRAFT
                || ($originalStatus === self::STATUS_RETIRED && $version->status !== self::STATUS_RETIRED)
                || ($originalStatus === self::STATUS_PUBLISHED
                    && ! in_array($version->status, [self::STATUS_PUBLISHED, self::STATUS_RETIRED], true));

            if ($version->isDirty($economic) || $invalidTransition) {
                throw PriceBookException::immutable();
            }
        });

        self::deleting(function (PriceBookVersion $version): void {
            if ($version->status !== self::STATUS_DRAFT) {
                throw PriceBookException::immutable();
            }
        });
    }

    public function setEffectiveFromAttribute(mixed $value): void
    {
        $this->attributes['effective_from'] = $value === null
            ? null
            : CarbonImmutable::parse($value)->toDateString();
    }

    public function setEffectiveUntilAttribute(mixed $value): void
    {
        $this->attributes['effective_until'] = $value === null
            ? null
            : CarbonImmutable::parse($value)->toDateString();
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PriceBookAdjustment::class);
    }

    public function showtimeTicketPrices(): HasMany
    {
        return $this->hasMany(ShowtimeTicketPrice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
