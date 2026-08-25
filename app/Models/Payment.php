<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const PROVIDER_COUNTER_CASH = 'counter_cash';

    public const PROVIDER_INTERNAL_ZERO = 'internal_zero';

    public const SUPPORTED_PROVIDERS = ['zalopay', 'vnpay', 'payos'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_UNRESOLVED = 'unresolved';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVIEW = 'review';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_UNRESOLVED,
        self::STATUS_SUCCESS,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
        self::STATUS_REVIEW,
    ];

    public const RECONCILABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_UNRESOLVED,
    ];

    public const UNSAFE_RETRY_STATUSES = [
        ...self::RECONCILABLE_STATUSES,
        self::STATUS_REVIEW,
    ];

    protected $fillable = [
        'booking_id',
        'app_id',
        'app_trans_id',
        'app_user',
        'app_time_ms',
        'payment_method',
        'order_code',
        'amount',
        'currency',
        'status',
        'description',
        'expires_at',
        'reconcile_until',
        'transaction_code',
        'transaction_status',
        'payment_url',
        'response_code',
        'card_type',
        'bank_code',
        'transaction_id',
        'provider_transaction_created_at',
        'provider_paid_at',
        'zp_trans_id',
        'zp_trans_token',
        'order_token',
        'order_url',
        'qr_code',
        'provider_return_code',
        'provider_sub_return_code',
        'provider_return_message',
        'provider_sub_return_message',
        'server_time_ms',
        'callback_received_at',
        'last_queried_at',
        'verified_at',
        'paid_at',
        'failed_at',
        'failure_reason',
        'create_response_hash',
        'callback_payload_hash',
        'query_response_hash',
    ];

    /** @param array<string, mixed> $attributes */
    public static function createForProvider(string $provider, array $attributes): self
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw new \InvalidArgumentException('Unsupported payment provider.');
        }

        unset($attributes['provider']);

        $payment = new self;
        $payment->fill($attributes);
        $payment->forceFill(['provider' => $provider]);
        $payment->save();

        return $payment;
    }

    protected $casts = [
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'reconcile_until' => 'datetime',
        'callback_received_at' => 'datetime',
        'last_queried_at' => 'datetime',
        'verified_at' => 'datetime',
        'settled_at' => 'datetime',
        'failed_at' => 'datetime',
        'provider_transaction_created_at' => 'datetime',
        'provider_paid_at' => 'datetime',
        'amount' => 'integer',
        'app_id' => 'integer',
        'app_time_ms' => 'integer',
        'server_time_ms' => 'integer',
        'provider_return_code' => 'integer',
        'provider_sub_return_code' => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by_user_id');
    }

    public function refundCases(): HasMany
    {
        return $this->hasMany(RefundCase::class);
    }

    public function hasAuthoritativeSuccessEvidence(): bool
    {
        return $this->status === self::STATUS_SUCCESS
            && ($this->verified_at !== null
                || ($this->provider === self::PROVIDER_COUNTER_CASH
                    && $this->settled_at !== null
                    && $this->settled_by_user_id !== null));
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('payment', $this->status);
    }
}
