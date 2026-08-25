<?php

namespace App\Services\Tickets;

use App\Models\AdmissionTicket;
use App\Models\BookingTicketPrint;
use App\Models\BookingTicketPrintEvent;
use App\Models\SeatIncidentResolution;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use App\Services\Seats\SeatIncidentResolutionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TicketPrintService
{
    public const INCIDENT_REPRINT_REASON = 'seat_incident_relocation';

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
        'paper_jam' => 'Máy in kẹt giấy',
        'out_of_paper' => 'Hết giấy',
        'printer_offline' => 'Máy in không kết nối',
        'printer_error' => 'Lỗi máy in',
        'browser_interrupted' => 'Phiên in bị gián đoạn',
        'wrong_format' => 'Vé in sai định dạng',
        'damaged_ticket' => 'Vé bị rách/hỏng',
        'faded_ticket' => 'Vé bị nhòe mực',
        'customer_lost_ticket' => 'Khách làm mất vé',
        'customer_request' => 'Khách yêu cầu cấp lại vé',
        'other' => 'Lý do khác',
    ];

    private const OPERATION_TTL_MINUTES = 10;

    public function __construct(
        private readonly BookingTicketEligibility $eligibility,
        private readonly ActivityLogger $activities,
        private readonly CinemaAccessService $cinemaAccess,
        private readonly SeatIncidentResolutionService $incidentResolutions,
    ) {}

    public function start(AdmissionTicket $ticket, User $actor, string $operationId, string $token): BookingTicketPrint
    {
        return DB::transaction(function () use ($ticket, $actor, $operationId, $token): BookingTicketPrint {
            $ticket = $this->authorizedLockedTicket($ticket, $actor);
            $state = BookingTicketPrint::query()->where('admission_ticket_id', $ticket->id)->lockForUpdate()->first();
            if ($state?->status === BookingTicketPrint::STATUS_PRINTING) {
                return $this->sameActiveOperation($state, $actor, $operationId, $token)
                    ? $state
                    : throw new HttpException(409, 'Một lần in khác đang được xử lý.');
            }
            if ($state || $ticket->print_count > 0) {
                throw new HttpException(409, 'Vé đã được in. Vui lòng dùng thao tác in lại và cung cấp lý do.');
            }

            $state = BookingTicketPrint::query()->create([
                'admission_ticket_id' => $ticket->id,
                'booking_id' => $ticket->booking_id,
                'status' => BookingTicketPrint::STATUS_PRINTING,
                'attempts_count' => 1,
                'active_operation_id' => $operationId,
                'active_operation_token_hash' => hash('sha256', $token),
                'active_operator_user_id' => $actor->id,
                'active_operation_expires_at' => now()->addMinutes(self::OPERATION_TTL_MINUTES),
            ]);
            $this->event($state, $actor, 'print_started', null, null, $operationId);
            $this->activities->log('ticket.print_started', $ticket->booking, context: $this->activityContext($ticket, $state, $actor));

            return $state;
        }, 3);
    }

    public function reprint(AdmissionTicket $ticket, User $actor, string $operationId, string $token, string $reason, ?string $note): BookingTicketPrint
    {
        abort_unless(array_key_exists($reason, self::REPRINT_REASONS), 422);
        $safeNote = $this->safeNote($note);
        if ($reason === 'other' && $safeNote === null) {
            throw new HttpException(422, 'Vui lòng mô tả ngắn gọn khi chọn lý do khác.');
        }

        return DB::transaction(function () use ($ticket, $actor, $operationId, $token, $reason, $safeNote): BookingTicketPrint {
            $ticket = $this->authorizedLockedTicket($ticket, $actor);
            $incidentReplacementPending = SeatIncidentResolution::query()
                ->where('reprint_required', true)->whereNull('reprint_satisfied_at')
                ->whereHas('impact', fn ($query) => $query->where('booking_seat_id', $ticket->booking_seat_id)
                    ->where('resolution_status', 'unresolved')
                    ->whereHas('incident', fn ($incident) => $incident->where('status', 'open')))
                ->exists();
            if ($incidentReplacementPending) {
                throw new HttpException(409, 'Vé đang chờ in thay thế do đổi ghế. Vui lòng dùng thao tác in theo sự cố.');
            }
            $state = BookingTicketPrint::query()->where('admission_ticket_id', $ticket->id)->lockForUpdate()->firstOrFail();
            if ($state->status === BookingTicketPrint::STATUS_PRINTING) {
                return $this->sameActiveOperation($state, $actor, $operationId, $token)
                    ? $state
                    : throw new HttpException(409, 'Một lần in khác đang được xử lý.');
            }
            if (! in_array($state->status, [
                BookingTicketPrint::STATUS_PRINTED,
                BookingTicketPrint::STATUS_RETRY_ALLOWED,
                BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION,
                BookingTicketPrint::STATUS_RETRY_AUTHORIZED,
            ], true)) {
                throw new HttpException(409, 'Vé chưa đủ điều kiện in lại.');
            }

            $state->forceFill([
                'status' => BookingTicketPrint::STATUS_PRINTING,
                'attempts_count' => $state->attempts_count + 1,
                'active_operation_id' => $operationId,
                'active_operation_token_hash' => hash('sha256', $token),
                'active_operator_user_id' => $actor->id,
                'active_seat_incident_resolution_id' => null,
                'active_operation_expires_at' => now()->addMinutes(self::OPERATION_TTL_MINUTES),
            ])->save();
            $this->event($state, $actor, 'reprint_requested', $reason, $safeNote, $operationId);
            $this->event($state, $actor, 'print_started', $reason, $safeNote, $operationId);
            $this->activities->log('ticket.reprint_requested', $ticket->booking, context: $this->activityContext($ticket, $state, $actor) + ['reprint_reason_code' => $reason]);

            return $state;
        }, 3);
    }

    public function incidentReprint(
        AdmissionTicket $ticket,
        SeatIncidentResolution $resolution,
        User $actor,
        string $operationId,
        string $token,
    ): BookingTicketPrint {
        return DB::transaction(function () use ($ticket, $resolution, $actor, $operationId, $token): BookingTicketPrint {
            $ticket = $this->authorizedLockedTicket($ticket, $actor);
            $resolution = SeatIncidentResolution::query()->with(['impact.incident'])->lockForUpdate()->findOrFail($resolution->id);
            abort_unless($resolution->resolution_type !== SeatIncidentResolution::TYPE_REQUIRES_REFUND
                && $resolution->reprint_required
                && $resolution->reprint_satisfied_at === null
                && $resolution->impact->resolution_status === 'unresolved'
                && $resolution->impact->incident->status === 'open'
                && (int) $resolution->impact->booking_seat_id === (int) $ticket->booking_seat_id
                && (int) $ticket->print_count > 0, 409, 'Không có yêu cầu in lại do đổi ghế đang chờ xử lý.');

            $state = BookingTicketPrint::query()->where('admission_ticket_id', $ticket->id)->lockForUpdate()->firstOrFail();
            if ($state->status === BookingTicketPrint::STATUS_PRINTING) {
                return $this->sameActiveOperation($state, $actor, $operationId, $token)
                    && (int) $state->active_seat_incident_resolution_id === (int) $resolution->id
                    ? $state
                    : throw new HttpException(409, 'Một lần in khác đang được xử lý.');
            }
            if (! in_array($state->status, [
                BookingTicketPrint::STATUS_PRINTED,
                BookingTicketPrint::STATUS_RETRY_ALLOWED,
                BookingTicketPrint::STATUS_RETRY_REQUIRES_AUTHORIZATION,
                BookingTicketPrint::STATUS_RETRY_AUTHORIZED,
            ], true)) {
                throw new HttpException(409, 'Vé chưa đủ điều kiện in thay thế.');
            }

            $state->forceFill([
                'status' => BookingTicketPrint::STATUS_PRINTING,
                'attempts_count' => $state->attempts_count + 1,
                'active_operation_id' => $operationId,
                'active_operation_token_hash' => hash('sha256', $token),
                'active_operator_user_id' => $actor->id,
                'active_seat_incident_resolution_id' => $resolution->id,
                'active_operation_expires_at' => now()->addMinutes(self::OPERATION_TTL_MINUTES),
            ])->save();
            $this->event($state, $actor, 'incident_reprint_requested', self::INCIDENT_REPRINT_REASON, null, $operationId, $resolution->id);
            $this->event($state, $actor, 'print_started', self::INCIDENT_REPRINT_REASON, null, $operationId, $resolution->id);
            $this->activities->log('ticket.incident_reprint_requested', $ticket->booking, context: $this->activityContext($ticket, $state, $actor) + [
                'reprint_reason_code' => self::INCIDENT_REPRINT_REASON,
                'seat_incident_resolution_id' => $resolution->id,
            ]);

            return $state;
        }, 3);
    }

    public function active(AdmissionTicket $ticket, User $actor, string $operationId, string $token): BookingTicketPrint
    {
        return DB::transaction(function () use ($ticket, $actor, $operationId, $token): BookingTicketPrint {
            $ticket = $this->authorizedLockedTicket($ticket, $actor);
            $state = BookingTicketPrint::query()->where('admission_ticket_id', $ticket->id)->lockForUpdate()->first();
            abort_unless($state && $this->sameActiveOperation($state, $actor, $operationId, $token), 410, 'Lần in này đã hết hiệu lực.');

            return $state;
        }, 3);
    }

    public function succeed(AdmissionTicket $ticket, User $actor, string $operationId, string $token): BookingTicketPrint
    {
        return DB::transaction(function () use ($ticket, $actor, $operationId, $token): BookingTicketPrint {
            $ticket = $this->authorizedLockedTicket($ticket, $actor);
            $state = BookingTicketPrint::query()->where('admission_ticket_id', $ticket->id)->lockForUpdate()->firstOrFail();
            if ($state->status === BookingTicketPrint::STATUS_PRINTED && $state->last_completed_operation_id === $operationId) {
                return $state;
            }
            $this->assertCurrentOperation($state, $actor, $operationId, $token);
            $printedAt = now();
            $incidentResolutionId = $state->active_seat_incident_resolution_id;
            $state->forceFill([
                'status' => BookingTicketPrint::STATUS_PRINTED,
                'printed_by_user_id' => $actor->id,
                'printed_at' => $printedAt,
                'last_completed_operation_id' => $operationId,
                ...$this->clearActiveOperation(),
            ])->save();
            $ticket->forceFill([
                'print_count' => $ticket->print_count + 1,
                'last_printed_at' => $printedAt,
                'last_printed_by_user_id' => $actor->id,
            ])->save();
            if ($incidentResolutionId) {
                $this->incidentResolutions->completeRequiredReprint(
                    SeatIncidentResolution::query()->findOrFail($incidentResolutionId),
                    (int) $ticket->id,
                );
            }
            $this->event(
                $state,
                $actor,
                'print_succeeded',
                $incidentResolutionId ? self::INCIDENT_REPRINT_REASON : null,
                null,
                $operationId,
                $incidentResolutionId,
            );
            $this->activities->log('ticket.print_succeeded', $ticket->booking, context: $this->activityContext($ticket, $state, $actor));

            return $state;
        }, 3);
    }

    public function fail(AdmissionTicket $ticket, User $actor, string $operationId, string $token, string $reason, ?string $note): BookingTicketPrint
    {
        abort_unless(array_key_exists($reason, self::FAILURE_REASONS), 422);

        return DB::transaction(function () use ($ticket, $actor, $operationId, $token, $reason, $note): BookingTicketPrint {
            $ticket = $this->authorizedLockedTicket($ticket, $actor);
            $state = BookingTicketPrint::query()->where('admission_ticket_id', $ticket->id)->lockForUpdate()->firstOrFail();
            if ($state->last_completed_operation_id === $operationId) {
                return $state;
            }
            $this->assertCurrentOperation($state, $actor, $operationId, $token);
            $incidentResolutionId = $state->active_seat_incident_resolution_id;
            $state->forceFill([
                'status' => BookingTicketPrint::STATUS_RETRY_ALLOWED,
                'last_failed_by_user_id' => $actor->id,
                'last_failed_at' => now(),
                'last_failure_code' => $reason,
                'last_completed_operation_id' => $operationId,
                ...$this->clearActiveOperation(),
            ])->save();
            $this->event($state, $actor, 'print_failed', $reason, $this->safeNote($note), $operationId, $incidentResolutionId);
            $this->activities->log('ticket.print_failed', $ticket->booking, context: $this->activityContext($ticket, $state, $actor) + ['failure_code' => $reason]);

            return $state;
        }, 3);
    }

    public function failExpired(AdmissionTicket $ticket, User $actor, string $reason = 'browser_interrupted', ?string $note = null): BookingTicketPrint
    {
        abort_unless(array_key_exists($reason, self::FAILURE_REASONS), 422);

        return DB::transaction(function () use ($ticket, $actor, $reason, $note): BookingTicketPrint {
            $ticket = $this->authorizedLockedTicket($ticket, $actor);
            $state = BookingTicketPrint::query()->where('admission_ticket_id', $ticket->id)->lockForUpdate()->firstOrFail();
            if ($state->status !== BookingTicketPrint::STATUS_PRINTING) {
                return $state;
            }
            abort_unless($state->active_operator_user_id === $actor->id, 403);
            abort_unless($state->active_operation_expires_at?->isPast(), 409, 'Phiên in vẫn còn hiệu lực.');
            $operationId = $state->active_operation_id;
            $incidentResolutionId = $state->active_seat_incident_resolution_id;
            $state->forceFill([
                'status' => BookingTicketPrint::STATUS_RETRY_ALLOWED,
                'last_failed_by_user_id' => $actor->id,
                'last_failed_at' => now(),
                'last_failure_code' => $reason,
                'last_completed_operation_id' => $operationId,
                ...$this->clearActiveOperation(),
            ])->save();
            $this->event($state, $actor, 'print_failed', $reason, $this->safeNote($note), $operationId, $incidentResolutionId);

            return $state;
        }, 3);
    }

    private function authorizedLockedTicket(AdmissionTicket $ticket, User $actor): AdmissionTicket
    {
        $ticket = AdmissionTicket::query()->with(['booking.payments'])->lockForUpdate()->findOrFail($ticket->id);
        abort_unless($ticket->booking->cinema_id
            && $this->cinemaAccess->allowsInCurrentContext($actor, (int) $ticket->booking->cinema_id), 404);
        if (! $this->eligibility->isPrintable($ticket->booking)) {
            throw new HttpException(409, 'Chỉ vé thuộc đơn đã thanh toán và còn hiệu lực mới có thể in.');
        }

        return $ticket;
    }

    private function sameActiveOperation(BookingTicketPrint $state, User $actor, string $operationId, string $token): bool
    {
        return $state->status === BookingTicketPrint::STATUS_PRINTING
            && $state->active_operator_user_id === $actor->id
            && $state->active_operation_id === $operationId
            && $state->active_operation_expires_at?->isFuture()
            && hash_equals((string) $state->active_operation_token_hash, hash('sha256', $token));
    }

    private function assertCurrentOperation(BookingTicketPrint $state, User $actor, string $operationId, string $token): void
    {
        abort_unless($this->sameActiveOperation($state, $actor, $operationId, $token), 410, 'Lần in này đã hết hiệu lực.');
    }

    private function event(BookingTicketPrint $state, User $actor, string $type, ?string $reason, ?string $note, ?string $operationId, ?int $incidentResolutionId = null): void
    {
        BookingTicketPrintEvent::query()->create([
            'booking_ticket_print_id' => $state->id,
            'admission_ticket_id' => $state->admission_ticket_id,
            'booking_id' => $state->booking_id,
            'seat_incident_resolution_id' => $incidentResolutionId,
            'actor_user_id' => $actor->id,
            'actor_role_snapshot' => Str::limit((string) $actor->role?->slug, 64, ''),
            'event_type' => $type,
            'attempt_number' => $state->attempts_count,
            'operation_id' => $operationId,
            'failure_code' => $reason,
            'safe_note' => $note,
            'request_id' => $this->requestId(),
        ]);
    }

    private function activityContext(AdmissionTicket $ticket, BookingTicketPrint $state, User $actor): array
    {
        return [
            'booking_id' => $ticket->booking_id,
            'booking_code' => $ticket->booking->booking_code,
            'admission_ticket_id' => $ticket->id,
            'ticket_code' => $ticket->ticket_code,
            'cinema_id' => $ticket->booking->cinema_id,
            'print_state_id' => $state->id,
            'attempt_number' => $state->attempts_count,
            'actor_id' => $actor->id,
        ];
    }

    private function clearActiveOperation(): array
    {
        return [
            'active_operation_id' => null,
            'active_operation_token_hash' => null,
            'active_operator_user_id' => null,
            'active_seat_incident_resolution_id' => null,
            'active_operation_expires_at' => null,
        ];
    }

    private function safeNote(?string $note): ?string
    {
        $safe = $note === null ? '' : Str::limit(strip_tags(trim($note)), 300, '');

        return $safe === '' ? null : $safe;
    }

    private function requestId(): string
    {
        $existing = request()->attributes->get('activity_request_id');
        if (is_string($existing)) {
            return $existing;
        }
        $header = trim((string) request()->header('X-Request-ID', ''));
        $requestId = preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $header) === 1 ? $header : (string) Str::uuid();
        request()->attributes->set('activity_request_id', $requestId);

        return $requestId;
    }
}
