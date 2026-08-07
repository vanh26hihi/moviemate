<?php

namespace App\Models;

use App\Support\SeatPresentation;
use App\Support\StatusLabel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Booking extends Model
{
    public const SALES_CHANNEL_ONLINE = 'online';

    public const SALES_CHANNEL_COUNTER = 'counter';

    public const SALES_CHANNELS = [self::SALES_CHANNEL_ONLINE, self::SALES_CHANNEL_COUNTER];

    public const STATUSES = ['pending_payment', 'paid', 'used', 'cancelled', 'expired'];

    public const PAYMENT_STATUSES = ['unpaid', 'paid', 'failed', 'refunded'];

    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'guest_access_token_hash',
        'guest_access_expires_at',
        'checkout_idempotency_key_hash',
        'checkout_request_fingerprint_hash',
        'showtime_id',
        'cinema_id',
        'booking_code',
        'total_amount',
        'seat_subtotal',
        'food_subtotal',
        'currency',
        'payment_status',
        'booking_status',
        'expires_at',
        'paid_at',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
        'guest_access_expires_at' => 'datetime',
        'ticket_email_token_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'ticket_emailed_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'seat_subtotal' => 'integer',
        'food_subtotal' => 'integer',
    ];

    protected $hidden = [
        'guest_access_token_hash',
        'ticket_email_token_nonce',
        'ticket_email_token_hash',
        'checkout_idempotency_key_hash',
        'checkout_request_fingerprint_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_staff_id');
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->sales_channel ??= self::SALES_CHANNEL_ONLINE;
            if (! in_array($booking->sales_channel, self::SALES_CHANNELS, true)) {
                throw new \LogicException('Unsupported booking sales channel.');
            }
            if ($booking->sales_channel === self::SALES_CHANNEL_ONLINE) {
                $booking->created_by_staff_id = null;
            } elseif ($booking->created_by_staff_id === null) {
                throw new \LogicException('Counter bookings require an authenticated creator.');
            }
            $showtime = Showtime::query()->with('room')->findOrFail($booking->showtime_id);
            if ((int) $showtime->cinema_id !== (int) $showtime->room?->cinema_id) {
                throw new \LogicException('Showtime and room cinema ownership are inconsistent.');
            }
            $booking->cinema_id = $showtime->cinema_id;
        });

        static::updating(function (Booking $booking): void {
            if ($booking->isDirty(['sales_channel', 'created_by_staff_id'])) {
                throw new \LogicException('Booking channel and creator attribution are immutable.');
            }
        });
    }

    public function bookingSeats(): HasMany
    {
        return $this->hasMany(BookingSeat::class);
    }

    public function payment(): HasOne
    {
        // Backward-compatible singular access now resolves the newest attempt.
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function authoritativePayment(): HasOne
    {
        return $this->hasOne(Payment::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('status', Payment::STATUS_SUCCESS)
                ->where(fn ($evidence) => $evidence->whereNotNull('verified_at')->orWhereNotNull('settled_at')),
        );
    }

    public function ticketDelivery(): HasOne
    {
        return $this->hasOne(BookingTicketDelivery::class);
    }

    public function ticketPrint(): HasOne
    {
        return $this->hasOne(BookingTicketPrint::class);
    }

    public function ticketCheckinEvents(): HasMany
    {
        return $this->hasMany(TicketCheckinEvent::class);
    }

    public function acceptedTicketCheckin(): HasOne
    {
        return $this->hasOne(TicketCheckinEvent::class)
            ->where('result', TicketCheckinEvent::RESULT_ACCEPTED)
            ->oldestOfMany('id');
    }

    public function foodOrder(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function getRecipientEmailAttribute(): ?string
    {
        return $this->customer_email ?: $this->user?->email;
    }

    public function getSeatCodesAttribute(): string
    {
        return $this->seat_display_groups->pluck('label')->filter()->join(', ');
    }

    public function getSeatDisplayGroupsAttribute(): Collection
    {
        return SeatPresentation::groups($this->bookingSeats->pluck('seat')->filter()->values());
    }

    public function getShowtimeLabelAttribute(): string
    {
        if (! $this->showtime?->show_date || ! $this->showtime?->show_time) {
            return 'Đang cập nhật';
        }

        return $this->showtime->show_date->format('d/m/Y').' '
            .Carbon::parse($this->showtime->show_time)->format('H:i');
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('booking', $this->booking_status);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return StatusLabel::for('booking_payment', $this->payment_status);
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format((int) $this->total_amount, 0, ',', '.').' VNĐ';
    }
}
