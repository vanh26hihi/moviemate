<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReviewEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_id',
        'actor_user_id',
        'action',
        'previous_status',
        'resulting_status',
        'provider_result_category',
        'provider_result_code',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
