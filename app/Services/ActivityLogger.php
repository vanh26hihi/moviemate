<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomType;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ActivityLogger
{
    public function __construct(
        private readonly Request $request,
        private readonly ActivityLogSanitizer $sanitizer,
    ) {}

    public function log(
        string $action,
        Model $subject,
        array $before = [],
        array $after = [],
        array $context = [],
    ): ActivityLog {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $action) !== 1) {
            throw new InvalidArgumentException('Activity action must be a stable, lowercase identifier.');
        }

        $actor = $this->request->user();

        return ActivityLog::query()->create([
            'actor_user_id' => $actor?->getAuthIdentifier(),
            'actor_role_snapshot' => $actor?->role?->slug,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey() === null ? null : (string) $subject->getKey(),
            'subject_label' => $this->subjectLabel($subject),
            'request_id' => $this->requestId(),
            'route_name' => $this->routeName(),
            'method' => $this->request->method(),
            'safe_ip_hash' => $this->safeIpHash($this->request->ip()),
            'user_agent_summary' => $this->userAgentSummary($this->request->userAgent()),
            'before_data' => $this->sanitizer->sanitize($before),
            'after_data' => $this->sanitizer->sanitize($after),
            'context' => $this->sanitizer->sanitize($context),
        ]);
    }

    private function requestId(): string
    {
        $existing = $this->request->attributes->get('activity_request_id');
        if (is_string($existing)) {
            return $existing;
        }

        $header = trim((string) $this->request->header('X-Request-ID', ''));
        $requestId = preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $header) === 1
            ? $header
            : (string) Str::uuid();
        $this->request->attributes->set('activity_request_id', $requestId);

        return $requestId;
    }

    private function routeName(): ?string
    {
        $name = $this->request->route()?->getName();

        return is_string($name) ? Str::limit($name, 191, '') : null;
    }

    private function safeIpHash(?string $ip): ?string
    {
        $applicationKey = (string) config('app.key');
        if ($ip === null || $ip === '' || $applicationKey === '') {
            return null;
        }

        if (str_starts_with($applicationKey, 'base64:')) {
            $decoded = base64_decode(substr($applicationKey, 7), true);
            $applicationKey = $decoded === false ? $applicationKey : $decoded;
        }

        $derivedKey = hash_hmac('sha256', 'moviemate/activity-ip/v1', $applicationKey, true);

        return hash_hmac('sha256', $ip, $derivedKey);
    }

    private function userAgentSummary(?string $userAgent): ?string
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Trình duyệt khác',
        };
        $platform = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Nền tảng khác',
        };

        return "{$browser} / {$platform}";
    }

    private function subjectLabel(Model $subject): string
    {
        return match (true) {
            $subject instanceof Booking => 'Đơn đặt vé '.$subject->booking_code,
            $subject instanceof BookingTicketDelivery => 'Gửi vé điện tử #'.$subject->getKey().' / đơn #'.$subject->booking_id,
            $subject instanceof Payment => 'Giao dịch #'.$subject->getKey().' / '.$subject->provider,
            $subject instanceof Room => 'Phòng '.$subject->code,
            $subject instanceof RoomType => 'Loại phòng '.$subject->code,
            $subject instanceof RoomLayout => 'Sơ đồ #'.$subject->getKey().' / phòng #'.$subject->room_id,
            $subject instanceof Showtime => 'Suất chiếu #'.$subject->getKey(),
            $subject instanceof Seat => 'Ghế '.$subject->seat_code.' / phòng #'.$subject->room_id,
            $subject instanceof User => 'Người dùng #'.$subject->getKey(),
            $subject instanceof Role => 'Vai trò '.$subject->slug,
            default => class_basename($subject).' #'.$subject->getKey(),
        };
    }
}
