<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Services\Tickets\TicketPrintService;
use App\Services\Tickets\TicketResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TicketPrintController extends Controller
{
    public function start(Request $request, Booking $booking, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        $this->startResolved($request, $tickets->authorizedFirstTicket($booking, $request->user()), $prints);

        return redirect()->route('staff.tickets.print.show', $booking)->with('success', 'Đã bắt đầu lần in vé.');
    }

    public function startTicket(Request $request, AdmissionTicket $admissionTicket, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        return $this->startResolved($request, $tickets->authorizedTicket($admissionTicket, $request->user()), $prints);
    }

    public function reprint(Request $request, Booking $booking, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        $this->reprintResolved($request, $tickets->authorizedFirstTicket($booking, $request->user()), $prints);

        return redirect()->route('staff.tickets.print.show', $booking)->with('success', 'Đã ghi nhận lý do và bắt đầu in lại.');
    }

    public function reprintTicket(Request $request, AdmissionTicket $admissionTicket, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        return $this->reprintResolved($request, $tickets->authorizedTicket($admissionTicket, $request->user()), $prints);
    }

    public function show(Request $request, Booking $booking, TicketResolutionService $tickets, TicketPrintService $prints): Response|RedirectResponse
    {
        return $this->showResolved($request, $tickets->authorizedFirstTicket($booking, $request->user()), $prints);
    }

    public function showTicket(Request $request, AdmissionTicket $admissionTicket, TicketResolutionService $tickets, TicketPrintService $prints): Response|RedirectResponse
    {
        return $this->showResolved($request, $tickets->authorizedTicket($admissionTicket, $request->user()), $prints);
    }

    public function succeed(Request $request, Booking $booking, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        return $this->succeedResolved($request, $tickets->authorizedFirstTicket($booking, $request->user()), $prints);
    }

    public function succeedTicket(Request $request, AdmissionTicket $admissionTicket, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        return $this->succeedResolved($request, $tickets->authorizedTicket($admissionTicket, $request->user()), $prints);
    }

    public function fail(Request $request, Booking $booking, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        return $this->failResolved($request, $tickets->authorizedFirstTicket($booking, $request->user()), $prints);
    }

    public function failTicket(Request $request, AdmissionTicket $admissionTicket, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        return $this->failResolved($request, $tickets->authorizedTicket($admissionTicket, $request->user()), $prints);
    }

    public function recoverExpired(Request $request, Booking $booking, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        return $this->recoverResolved($request, $tickets->authorizedFirstTicket($booking, $request->user()), $prints);
    }

    public function recoverExpiredTicket(Request $request, AdmissionTicket $admissionTicket, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        return $this->recoverResolved($request, $tickets->authorizedTicket($admissionTicket, $request->user()), $prints);
    }

    private function startResolved(Request $request, AdmissionTicket $ticket, TicketPrintService $prints): RedirectResponse
    {
        $operation = $this->operation($request, $ticket, true);
        $prints->start($ticket, $request->user(), $operation['id'], $operation['token']);

        return redirect()->route('staff.admission-tickets.print.show', $ticket)->with('success', 'Đã bắt đầu lần in vé.');
    }

    private function reprintResolved(Request $request, AdmissionTicket $ticket, TicketPrintService $prints): RedirectResponse
    {
        $validated = $request->validate([
            'reason_code' => ['required', 'in:'.implode(',', array_keys(TicketPrintService::REPRINT_REASONS))],
            'safe_note' => ['nullable', 'string', 'max:300', 'required_if:reason_code,other'],
        ], [
            'reason_code.required' => 'Vui lòng chọn lý do in lại.',
            'safe_note.required_if' => 'Vui lòng mô tả ngắn gọn khi chọn lý do khác.',
        ]);
        $operation = $this->newOperation($request, $ticket);
        $prints->reprint($ticket, $request->user(), $operation['id'], $operation['token'], $validated['reason_code'], $validated['safe_note'] ?? null);

        return redirect()->route('staff.admission-tickets.print.show', $ticket)->with('success', 'Đã ghi nhận lý do và bắt đầu in lại.');
    }

    private function showResolved(Request $request, AdmissionTicket $ticket, TicketPrintService $prints): Response|RedirectResponse
    {
        $operation = $this->currentOperation($request, $ticket);
        if ($operation === null) {
            return $this->recoverNavigation($ticket);
        }
        try {
            $state = $prints->active($ticket, $request->user(), $operation['id'], $operation['token']);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 410) {
                throw $exception;
            }

            return $this->recoverNavigation($ticket);
        }
        $booking = $ticket->booking;
        $booking->loadMissing(['payments.settledBy:id,name', 'createdByStaff:id,name', 'foodOrder.items']);

        return response()->view('staff.tickets.print', [
            'ticket' => $ticket,
            'booking' => $booking,
            'printState' => $state,
            'ticketQrPayload' => $ticket->ticket_code,
            'failureReasons' => TicketPrintService::FAILURE_REASONS,
            'printOperationId' => $operation['id'],
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')->header('Pragma', 'no-cache');
    }

    private function succeedResolved(Request $request, AdmissionTicket $ticket, TicketPrintService $prints): RedirectResponse
    {
        $operation = $this->operation($request, $ticket, allowCompleted: true);
        $prints->succeed($ticket, $request->user(), $operation['id'], $operation['token']);
        $this->completeOperationSession($request, $ticket, $operation);

        return redirect()->route('staff.tickets.operations', $ticket->booking_id)->with('success', 'Đã xác nhận in vé thành công.');
    }

    private function failResolved(Request $request, AdmissionTicket $ticket, TicketPrintService $prints): RedirectResponse
    {
        $validated = $request->validate([
            'failure_code' => ['required', 'in:'.implode(',', array_keys(TicketPrintService::FAILURE_REASONS))],
            'safe_note' => ['nullable', 'string', 'max:300', 'required_if:failure_code,other'],
        ]);
        $operation = $this->operation($request, $ticket, allowCompleted: true);
        $prints->fail($ticket, $request->user(), $operation['id'], $operation['token'], $validated['failure_code'], $validated['safe_note'] ?? null);
        $this->completeOperationSession($request, $ticket, $operation);

        return redirect()->route('staff.tickets.operations', $ticket->booking_id)->with('success', 'Đã ghi nhận lỗi in vé.');
    }

    private function recoverResolved(Request $request, AdmissionTicket $ticket, TicketPrintService $prints): RedirectResponse
    {
        $prints->failExpired($ticket, $request->user());

        return redirect()->route('staff.tickets.operations', $ticket->booking_id)->with('success', 'Đã ghi nhận phiên in bị gián đoạn.');
    }

    /** @return array{id:string, token:string} */
    private function operation(Request $request, AdmissionTicket $ticket, bool $create = false, bool $allowCompleted = false): array
    {
        $operation = $request->session()->get($this->sessionKey($ticket));
        if ($allowCompleted && ! is_array($operation)) {
            $operation = $request->session()->get($this->completedSessionKey($ticket));
        }
        if ($create && (! is_array($operation) || ! isset($operation['id'], $operation['token']))) {
            $operation = $this->newOperation($request, $ticket);
        }
        abort_unless(is_array($operation) && is_string($operation['id'] ?? null) && is_string($operation['token'] ?? null), 410, 'Lần in này đã hết hiệu lực.');

        return $operation;
    }

    /** @return array{id:string, token:string} */
    private function newOperation(Request $request, AdmissionTicket $ticket): array
    {
        $operation = ['id' => (string) Str::uuid(), 'token' => Str::random(64)];
        $request->session()->put($this->sessionKey($ticket), $operation);

        return $operation;
    }

    private function currentOperation(Request $request, AdmissionTicket $ticket): ?array
    {
        $operation = $request->session()->get($this->sessionKey($ticket));

        return is_array($operation) && is_string($operation['id'] ?? null) && is_string($operation['token'] ?? null) ? $operation : null;
    }

    private function recoverNavigation(AdmissionTicket $ticket): RedirectResponse
    {
        $state = $ticket->printState;
        $message = match ($state?->status) {
            'printed' => 'Vé này đã được ghi nhận in thành công.',
            'printing' => $state->active_operation_expires_at?->isPast()
                ? 'Phiên in trước đã hết hiệu lực. Vui lòng xác nhận kết quả lần in trước trước khi tiếp tục.'
                : 'Phiên in đang được xử lý trong một cửa sổ khác.',
            default => 'Không có phiên in hợp lệ.',
        };

        return redirect()->route('staff.tickets.operations', $ticket->booking_id)
            ->with($state?->status === 'printed' ? 'success' : 'warning', $message);
    }

    private function sessionKey(AdmissionTicket $ticket): string
    {
        return 'ticket_print_operations.'.$ticket->id;
    }

    private function completedSessionKey(AdmissionTicket $ticket): string
    {
        return 'ticket_print_completed_operations.'.$ticket->id;
    }

    private function completeOperationSession(Request $request, AdmissionTicket $ticket, array $operation): void
    {
        $request->session()->put($this->completedSessionKey($ticket), $operation);
        $request->session()->forget($this->sessionKey($ticket));
    }
}
