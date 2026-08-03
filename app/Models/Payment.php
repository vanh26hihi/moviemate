<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVIEW = 'review';

    protected $fillable = [
        'booking_id',
        'provider',
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
        'transaction_code',
        'transaction_status',
        'payment_url',
        'response_code',
        'card_type',
        'bank_code',
        'transaction_id',
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

    protected $casts = [
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'callback_received_at' => 'datetime',
        'last_queried_at' => 'datetime',
        'verified_at' => 'datetime',
        'failed_at' => 'datetime',
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
}
