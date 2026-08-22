<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class ShowtimeTicketPrice extends Model
{
    protected $fillable = [
        'showtime_id',
        'seat_type_id',
        'price_book_version_id',
        'base_price_vnd',
        'adjustment_total_vnd',
        'final_unit_amount_vnd',
        'breakdown_json',
        'pricing_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'showtime_id' => 'integer',
            'seat_type_id' => 'integer',
            'price_book_version_id' => 'integer',
            'base_price_vnd' => 'integer',
            'adjustment_total_vnd' => 'integer',
            'final_unit_amount_vnd' => 'integer',
            'breakdown_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Showtime ticket price snapshots are immutable.'));
        self::deleting(function (ShowtimeTicketPrice $snapshot): void {
            if (BookingSeat::query()->where('showtime_id', $snapshot->showtime_id)->exists()) {
                throw new LogicException('Cannot replace Showtime ticket prices after booking history exists.');
            }
        });
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function seatType(): BelongsTo
    {
        return $this->belongsTo(SeatType::class);
    }

    public function priceBookVersion(): BelongsTo
    {
        return $this->belongsTo(PriceBookVersion::class);
    }

    public function bookingSeats(): HasMany
    {
        return $this->hasMany(BookingSeat::class);
    }

    public function getFinalAmountAttribute(): int
    {
        return (int) $this->final_unit_amount_vnd;
    }

    public function getBaseAmountAttribute(): int
    {
        return (int) $this->base_price_vnd;
    }

    public function getSurchargeTotalAttribute(): int
    {
        return (int) $this->adjustment_total_vnd;
    }

    public function getSeatTypeCodeAttribute(): string
    {
        return (string) $this->seatType?->code;
    }

    public function getSurchargesAttribute(): array
    {
        return array_map(
            fn (array $item): array => [...$item, 'amount' => (int) ($item['amount_vnd'] ?? 0)],
            $this->breakdown_json['adjustments'] ?? [],
        );
    }

    public function breakdown(): array
    {
        return $this->breakdown_json;
    }
}
