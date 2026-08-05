<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'actor_role_snapshot',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'request_id',
        'route_name',
        'method',
        'safe_ip_hash',
        'user_agent_summary',
        'before_data',
        'after_data',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity logs are append-only and cannot be updated.'));
        static::deleting(fn () => throw new LogicException('Activity logs are append-only and cannot be deleted.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
