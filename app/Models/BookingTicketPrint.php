<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BookingTicketPrint extends Model
{
    public const STATUS_PRINTING = 'printing';

    public const STATUS_PRINTED = 'printed';

    public const STATUS_RETRY_ALLOWED = 'retry_allowed';

    public const STATUS_RETRY_REQUIRES_AUTHORIZATION = 'retry_requires_authorization';

    public const STATUS_RETRY_AUTHORIZED = 'retry_authorized';

    protected $guarded = [];

    protected $hidden = ['active_operation_token_hash'];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'retry_authorized_at' => 'datetime',
            'active_operation_expires_at' => 'datetime',
            'attempts_count' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function admissionTicket(): BelongsTo
    {
        return $this->belongsTo(AdmissionTicket::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }

    public function lastFailedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_failed_by_user_id');
    }

    public function retryAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retry_authorized_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingTicketPrintEvent::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('ticket_print', $this->status);
    }
}
