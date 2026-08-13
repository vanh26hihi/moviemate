<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use HasFactory;

    /** Technical ceiling keeps width_mm * length_mm within signed 64-bit integer range. */
    public const MAX_DIMENSION_MM = 3_000_000_000;

    protected $fillable = [
        'cinema_id',
        'code',
        'name',
        'room_type',
        'room_type_id',
        'width_mm',
        'length_mm',
        'cleaning_buffer_minutes',
        'status',
    ];

    protected $casts = [
        'width_mm' => 'integer',
        'length_mm' => 'integer',
        'cleaning_buffer_minutes' => 'integer',
    ];

    public function hasCompletePhysicalDimensions(): bool
    {
        return $this->width_mm !== null
            && $this->length_mm !== null
            && $this->width_mm > 0
            && $this->length_mm > 0;
    }

    public function areaMm2(): ?int
    {
        if (! $this->hasCompletePhysicalDimensions()) {
            return null;
        }

        return $this->width_mm * $this->length_mm;
    }

    public function widthMetersForInput(): ?string
    {
        return $this->width_mm === null ? null : $this->decimalFromScaledInteger($this->width_mm, 3, 0, '.');
    }

    public function lengthMetersForInput(): ?string
    {
        return $this->length_mm === null ? null : $this->decimalFromScaledInteger($this->length_mm, 3, 0, '.');
    }

    public function formattedWidthMeters(): ?string
    {
        return $this->width_mm === null ? null : $this->decimalFromScaledInteger($this->width_mm, 3, 2, ',');
    }

    public function formattedLengthMeters(): ?string
    {
        return $this->length_mm === null ? null : $this->decimalFromScaledInteger($this->length_mm, 3, 2, ',');
    }

    public function formattedAreaM2(): ?string
    {
        $area = $this->areaMm2();

        return $area === null ? null : $this->decimalFromScaledInteger($area, 6, 2, ',');
    }

    private function decimalFromScaledInteger(int $value, int $scale, int $minimumDecimals, string $separator): string
    {
        $factor = 10 ** $scale;
        $whole = intdiv($value, $factor);
        $fraction = str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);
        $fraction = rtrim($fraction, '0');
        $fraction = str_pad($fraction, $minimumDecimals, '0');

        return number_format($whole, 0, '', '.').($fraction === '' ? '' : $separator.$fraction);
    }

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function presentationCapabilities(): BelongsToMany
    {
        return $this->belongsToMany(PresentationFormat::class, 'room_presentation_capabilities')->withTimestamps();
    }

    public function getRoomTypeLabelAttribute(): string
    {
        return $this->roomType?->name ?: $this->room_type;
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    public function seatIncidents(): HasMany
    {
        return $this->hasMany(SeatIncident::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(CinemaPricingRule::class);
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(RoomLayout::class);
    }

    public function publishedLayouts(): HasMany
    {
        return $this->layouts()->published()->orderByDesc('version');
    }

    public function draftLayout(): HasOne
    {
        return $this->hasOne(RoomLayout::class)->where('status', 'draft')->latestOfMany('version');
    }

    public function latestPublishedLayout(): HasOne
    {
        return $this->hasOne(RoomLayout::class)->where('status', 'published')->latestOfMany('version');
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('room', $this->status);
    }
}
