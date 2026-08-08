<?php

namespace App\Services\Tickets;

use App\Models\Booking;
use App\Models\BookingTicketPrint;
use App\Models\BookingTicketPrintEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TicketPrintService
{
    public const FAILURE_REASONS = [
        'paper_jam' => 'Kẹt giấy',
        'out_of_paper' => 'Hết giấy',
        'printer_offline' => 'Máy in không kết nối',
        'printer_error' => 'Lỗi máy in',
        'browser_interrupted' => 'Trình duyệt/phiên in bị gián đoạn',
        'wrong_format' => 'Sai định dạng vé',
        'other' => 'Khác',
    ];

    public const REPRINT_REASONS = [
        'paper_jam' => 'Kẹt giấy',
        'out_of_paper' => 'Hết giấy',
        'printer_offline' => 'Máy in không kết nối',
        'printer_error' => 'Lỗi máy in',
        'browser_interrupted' => 'Phiên in bị gián đoạn',
        'wrong_format' => 'Vé in sai định dạng',
        'damaged_ticket' => 'Vé bị rách/hỏng',
        'faded_ticket' => 'Vé in mờ/không đọc được',
        'customer_lost_ticket' => 'Khách làm mất vé',
        'customer_request' => 'Khách yêu cầu cấp lại vé',
        'other' => 'Lý do khác',
    ];

    private const OPERATION_TTL_MINUTES = 10;

    public function __construct(
        private readonly BookingTicketEligibility $eligibility,
        private readonly ActivityLogger $activities,
        private readonly CinemaAccessService $cinemaAccess,
    ) {}

    public function start(Booking $booking, User $actor, string $operationId, string $token): BookingTicketPrint
    {
        return DB::transaction(function () use ($booking, $actor, $operationId, $token): BookingTicketPrint {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            abort_unless($lockedBooking->cinema_id
                && $this->cinemaAccess->allowsInCurrentContext($actor, (int) $lockedBooking->cinema_id), 404);
            $this->assertPrintable($lockedBooking);
            $state = BookingTicketPrint::query()->where('booking_id', $lockedBooking->id)->lockForUpdate()->first();
            $tokenHash = hash('sha256', $token);

            if ($state?->status === BookingTicketPrint::STATUS_PRINTING) {
                if ($state->active_operation_expires_at?->isFuture()
                    && $state->active_operation_id === $operationId
                    && $state->active_operator_user_id === $actor->id
                    && hash_equals((string) $state->active_operation_token_hash, $tokenHash)) {
                    return $state;
                }
                if ($state->active_operation_expires_at?->isFuture()) {
                    throw new HttpException(409, 'Một lần in khác đang được xử lý.');
                }

                throw new HttpException(409, 'Phiên in trước đã hết hiệu lực. Vui lòng xác nhận kết quả lần in trước trước khi tiếp tục.');
            }

            if ($state) {
                throw new HttpException(409, 'Vé đã có lịch sử in. Vui lòng dùng thao tác in lại và ghi rõ lý do.');
            }

            $before = $state?->status ?? 'unprinted';
            $state ??= new BookingTicketPrint(['booking_id' => $lockedBooking->id, 'attempts_count' => 0]);
            $state->forceFill([
                'status' => BookingTicketPrint::STATUS_PRINTING,
                'attempts_count' => $state->attempts_count + 1,
                'active_operation_id' => $operationId,
                'active_operation_token_hash' => $tokenHash,
                'active_operator_user_id' => $actor->id,
                'active_operation_expires_at' => now()->addMinutes(self::OPERATION_TTL_MINUTES),
            ])->save();
            $this->event($state, $actor, 'print_started', $state->attempts_count, null, null, $operationId);
            $this->activities->log('ticket.print_started', $lockedBooking,
                ['print_status' => $before], ['print_status' => $state->status],
                $this->activityContext($lockedBooking, $state, $actor));

            return $state;
        });
    }

    public function reprint(
        Booking $booking,
        User $actor,
        string $operationId,
        string $token,
        string $reason,
        ?string $note,
    ): BookingTicketPrint {
        abort_unless(array_key_exists($reason, self::REPRINT_REASONS), 422);
        $safeNote = $this->safeNote($note);
        if ($reason === 'other' && $safeNote === null) {
            throw new HttpException(422, 'Vui lòng mô tả ngắn gọn khi chọn lý do khác.');
        }

        return DB::transaction(function () use ($booking, $actor, $operationId, $token, $reason, $safeNote): BookingTicketPrint {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            abort_unless($lockedBooking->cinema_id
                && $this->cinemaAccess->allowsInCurrentContext($actor, (int) $lockedBooking->cinema_id), 404);
            $this->assertPrintable($lockedBooking);
            $state = BookingTicketPrint::query()
                ->where('booking_id', $lockedBooking->id)
                ->lockForUpdate()
                ->firstOrFail();
            $tokenHash = hash('sha256', $token);

            if ($state->status === BookingTicketPrint::STATUS_PRINTING) {
                if ($state->active_operation_expires_at?->isFuture()
                    && $state->active_operation_id === $operationId
                    && $state->active_operator_user_id === $actor->id
                    && hash_equals((string) $state->active_operation_token_hash, $tokenHash)) {
                    return $state;
                }
                if ($state->active_operation_expires_at?->isFuture()) {
                    throw new HttpException(409, 'Một lần in khác đang được xử lý.');
                }

                throw new HttpException(409, 'Phiên in trước đã hết hiệu lực. Vui lòng xác nhận kết quả lần in trước trước khi tiếp tục.');
            }

            if (! in_array($state->status, [
                BookingTicketPrint::STATUS_PRINTED,
                BookingTicketPrint::STATUS_RETRY_ALLOWED,
                BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION,
                BookingTicketPrint::STATUS_RETRY_AUTHORIZED,
            ], true)) {
                throw new HttpException(409, 'Vé chưa đủ điều kiện thực hiện lần in lại.');
            }

            $before = $state->status;
            $state->forceFill([
                'status' => BookingTicketPrint::STATUS_PRINTING,
                'attempts_count' => $state->attempts_count + 1,
                'active_operation_id' => $operationId,
                'active_operation_token_hash' => $tokenHash,
                'active_operator_user_id' => $actor->id,
                'active_operation_expires_at' => now()->addMinutes(self::OPERATION_TTL_MINUTES),
            ])->save();
            $this->event($state, $actor, 'reprint_requested', $state->attempts_count, $reason, $safeNote, $operationId);
            $this->event($state, $actor, 'print_started', $state->attempts_count, $reason, $safeNote, $operationId);
            $context = $this->activityContext($lockedBooking, $state, $actor) + ['reprint_reason_code' => $reason];
            $this->activities->log('ticket.reprint_requested', $lockedBooking,
                ['print_status' => $before], ['print_status' => $state->status], $context);
            $this->activities->log('ticket.print_started', $lockedBooking,
                ['print_status' => $before], ['print_status' => $state->status], $context);

            return $state;
        });
    }

    public function active(Booking $booking, User $actor, string $operationId, string $token): BookingTicketPrint
    {
        $state = BookingTicketPrint::query()->where('booking_id', $booking->id)->first();
        abort_unless($state
            && $state->status === BookingTicketPrint::STATUS_PRINTING
            && $state->active_operator_user_id === $actor->id
            && $state->active_operation_id === $operationId
            && $state->active_operation_expires_at?->isFuture()
            && hash_equals((string) $state->active_operation_token_hash, hash('sha256', $token)), 410,
            'Lần in này đã hết hiệu lực.');

        return $state;
    }

    public function succeed(Booking $booking, User $actor, string $operationId, string $token): BookingTicketPrint
    {
        return DB::transaction(function () use ($booking, $actor, $operationId, $token): BookingTicketPrint {
            $state = BookingTicketPrint::query()->where('booking_id', $booking->id)->lockForUpdate()->firstOrFail();
            if ($state->status === BookingTicketPrint::STATUS_PRINTED
                && $state->last_completed_operation_id === $operationId) {
                return $state;
            }
            $this->assertCurrentOperation($state, $actor, $operationId, $token);
            $state->forceFill([
                'status' => BookingTicketPrint::STATUS_PRINTED,
                'printed_by_user_id' => $actor->id,
                'printed_at' => now(),
                'last_completed_operation_id' => $operationId,
                ...$this->clearActiveOperation(),
            ])->save();
            $this->event($state, $actor, 'print_succeeded', $state->attempts_count, null, null, $operationId);
            $this->activities->log('ticket.print_succeeded', $booking,
                ['print_status' => BookingTicketPrint::STATUS_PRINTING], ['print_status' => $state->status],
                $this->activityContext($booking, $state, $actor));

            return $state;
        });
    }

    public function fail(Booking $booking, User $actor, string $operationId, string $token, string $reason, ?string $note): BookingTicketPrint
    {
        return DB::transaction(function () use ($booking, $actor, $operationId, $token, $reason, $note): BookingTicketPrint {
            $state = BookingTicketPrint::query()->where('booking_id', $booking->id)->lockForUpdate()->firstOrFail();
            if ($state->status === BookingTicketPrint::STATUS_PRINTED) {
                return $state;
            }
            if ($state->last_completed_operation_id === $operationId) {
                return $state;
            }
            $this->assertCurrentOperation($state, $actor, $operationId, $token);
            $next = BookingTicketPrint::STATUS_RETRY_ALLOWED;
            $safeNote = $this->safeNote($note);
            $state->forceFill([
                'status' => $next,
                'last_failed_by_user_id' => $actor->id,
                'last_failed_at' => now(),
                'last_failure_code' => $reason,
                'last_completed_operation_id' => $operationId,
                ...$this->clearActiveOperation(),
            ])->save();
            $this->event($state, $actor, 'print_failed', $state->attempts_count, $reason, $safeNote, $operationId);
            $this->activities->log('ticket.print_failed', $booking,
                ['print_status' => BookingTicketPrint::STATUS_PRINTING], ['print_status' => $state->status],
                $this->activityContext($booking, $state, $actor) + ['failure_code' => $reason]);

            return $state;
        });
    }

    public function failExpired(
        Booking $booking,
        User $actor,
        string $reason = 'browser_interrupted',
        ?string $note = null,
    ): BookingTicketPrint {
        abort_unless(array_key_exists($reason, self::FAILURE_REASONS), 422);

        return DB::transaction(function () use ($booking, $actor, $reason, $note): BookingTicketPrint {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            abort_unless($lockedBooking->cinema_id
                && $this->cinemaAccess->allowsInCurrentContext($actor, (int) $lockedBooking->cinema_id), 404);
            $state = BookingTicketPrint::query()
                ->where('booking_id', $lockedBooking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($state->status !== BookingTicketPrint::STATUS_PRINTING) {
                if ($state->last_failed_by_user_id === $actor->id
                    && $state->last_failure_code === $reason) {
                    return $state;
                }
                throw new HttpException(409, 'Phiên in này không còn ở trạng thái chờ xác nhận.');
            }
            abort_unless($state->active_operator_user_id === $actor->id, 403);
            abort_unless($state->active_operation_expires_at?->isPast(), 409,
                'Phiên in vẫn còn hiệu lực. Vui lòng báo lỗi từ trang in hiện tại.');

            $operationId = $state->active_operation_id;
            $next = BookingTicketPrint::STATUS_RETRY_ALLOWED;
            $safeNote = $this->safeNote($note);
            $state->forceFill([
                'status' => $next,
                'last_failed_by_user_id' => $actor->id,
                'last_failed_at' => now(),
                'last_failure_code' => $reason,
                'last_completed_operation_id' => $operationId,
                ...$this->clearActiveOperation(),
            ])->save();
            $this->event($state, $actor, 'print_failed', $state->attempts_count, $reason, $safeNote, $operationId);
            $this->activities->log('ticket.print_failed', $lockedBooking,
                ['print_status' => BookingTicketPrint::STATUS_PRINTING], ['print_status' => $state->status],
                $this->activityContext($lockedBooking, $state, $actor) + ['failure_code' => $reason]);

            return $state;
        });
    }

    private function assertPrintable(Booking $booking): void
    {
        $booking->loadMissing('payments');
        if (! $this->eligibility->isUsable($booking)) {
            throw new HttpException(409, 'Chỉ vé đã thanh toán, chưa sử dụng và chưa bị hủy mới có thể in.');
        }
    }

    private function assertCurrentOperation(BookingTicketPrint $state, User $actor, string $operationId, string $token): void
    {
        abort_unless($state->status === BookingTicketPrint::STATUS_PRINTING
            && $state->active_operator_user_id === $actor->id
            && $state->active_operation_id === $operationId
            && $state->active_operation_expires_at?->isFuture()
            && hash_equals((string) $state->active_operation_token_hash, hash('sha256', $token)), 410,
            'Lần in này đã hết hiệu lực.');
    }

    private function event(BookingTicketPrint $state, User $actor, string $type, int $attempt, ?string $failure = null, ?string $note = null, ?string $operationId = null): void
    {
        BookingTicketPrintEvent::query()->create([
            'booking_ticket_print_id' => $state->id,
            'booking_id' => $state->booking_id,
            'actor_user_id' => $actor->id,
            'actor_role_snapshot' => Str::limit((string) $actor->role?->slug, 64, ''),
            'event_type' => $type,
            'attempt_number' => $attempt,
            'operation_id' => $operationId,
            'failure_code' => $failure,
            'safe_note' => $note,
            'request_id' => $this->requestId(),
        ]);
    }

    private function activityContext(Booking $booking, BookingTicketPrint $state, User $actor): array
    {
        return ['booking_id' => $booking->id, 'booking_code' => $booking->booking_code,
            'cinema_id' => $booking->cinema_id, 'print_state_id' => $state->id,
            'attempt_number' => $state->attempts_count, 'actor_id' => $actor->id];
    }

    private function clearActiveOperation(): array
    {
        return ['active_operation_id' => null, 'active_operation_token_hash' => null,
            'active_operator_user_id' => null, 'active_operation_expires_at' => null];
    }

    private function safeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $safe = Str::limit(strip_tags(trim($note)), 300, '');

        return $safe === '' ? null : $safe;
    }

    private function requestId(): string
    {
        $existing = request()->attributes->get('activity_request_id');
        if (is_string($existing)) {
            return $existing;
        }

        $header = trim((string) request()->header('X-Request-ID', ''));
        $requestId = preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $header) === 1
            ? $header
            : (string) Str::uuid();
        request()->attributes->set('activity_request_id', $requestId);

        return $requestId;
    }
}
