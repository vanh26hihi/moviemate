<?php

namespace App\Services;

use Illuminate\Support\Str;

final class ActivityLogSanitizer
{
    private const ALLOWED_KEYS = [
        'active',
        'booking_id',
        'booking_code',
        'changed_fields',
        'count',
        'layout_id',
        'layout_version',
        'movie_id',
        'payment_id',
        'payment_status',
        'reconciliation_result',
        'permission_slugs',
        'previous_status',
        'price',
        'provider',
        'recipient_mask',
        'reason',
        'result',
        'role_slug',
        'room_code',
        'room_id',
        'room_layout_id',
        'seat_code',
        'seat_count',
        'seat_id',
        'seat_type',
        'show_date',
        'show_time',
        'showtime_id',
        'source',
        'status',
        'delivery_status',
        'delivery_id',
        'attempt_number',
        'error_category',
        'checkin_event_id',
        'checkin_result',
        'vip_price',
    ];

    /** @return array<string, mixed>|null */
    public function sanitize(array $data): ?array
    {
        $safe = [];

        foreach ($data as $key => $value) {
            $normalizedKey = Str::snake((string) $key);
            if ($this->isSensitiveKey($normalizedKey) || ! in_array($normalizedKey, self::ALLOWED_KEYS, true)) {
                continue;
            }

            $sanitized = $this->sanitizeValue($value);
            if ($sanitized !== null) {
                $safe[$normalizedKey] = $sanitized;
            }
        }

        return $safe === [] ? null : $safe;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (! is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            return array_values(array_filter(
                array_map(fn (mixed $item): mixed => $this->sanitizeValue($item), array_slice($value, 0, 100)),
                static fn (mixed $item): bool => $item !== null,
            ));
        }

        return $this->sanitize($value);
    }

    private function sanitizeString(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        if (preg_match('#https?://#iu', $value)) {
            return '[liên kết đã ẩn]';
        }

        $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', '[email đã ẩn]', $value) ?? $value;
        $value = preg_replace('/(?<!\d)(?:\+?84|0)(?:[ .-]?\d){8,10}(?!\d)/u', '[số điện thoại đã ẩn]', $value) ?? $value;
        $value = preg_replace('/\b[A-Za-z0-9+\/_=.-]{40,}\b/u', '[giá trị dài đã ẩn]', $value) ?? $value;

        return Str::limit($value, 500, '…');
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match(
            '/password|secret|token|cookie|authorization|credential|secure_?hash|signature|smtp|payload|signed_?url|payment_?url|provider_?url|guest_?access|ticket_?email/i',
            $key,
        ) === 1;
    }
}
