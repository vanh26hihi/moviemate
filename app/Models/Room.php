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

    protected $fillable = [
        'cinema_id',
        'code',
        'name',
        'room_type',
        'room_type_id',
        'total_seats',
        'cleaning_buffer_minutes',
        'status',
    ];

    protected $casts = [
        'total_seats' => 'integer',
        'cleaning_buffer_minutes' => 'integer',
    ];

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
