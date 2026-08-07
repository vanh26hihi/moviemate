<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Tickets\TicketPrintService;
use App\Services\Tickets\TicketQrPayload;
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
        $booking = $tickets->authorizedBooking($booking, $request->user());
        $operation = $this->operation($request, $booking, true);
        $prints->start($booking, $request->user(), $operation['id'], $operation['token']);

        return redirect()->route('staff.tickets.print.show', $booking)
            ->with('success', 'Đã bắt đầu lần in vé.');
    }

    public function show(
        Request $request,
        Booking $booking,
        TicketResolutionService $tickets,
        TicketPrintService $prints,
        TicketQrPayload $payloads,
    ): Response|RedirectResponse {
        $booking = $tickets->authorizedBooking($booking, $request->user());
        $operation = $this->currentOperation($request, $booking);
        if ($operation === null) {
            return $this->recoverPrintNavigation($booking);
        }
        try {
            $state = $prints->active($booking, $request->user(), $operation['id'], $operation['token']);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 410) {
                throw $exception;
            }

            return $this->recoverPrintNavigation($booking);
        }
        $booking->loadMissing(['payments.settledBy:id,name', 'createdByStaff:id,name']);

        return response()->view('staff.tickets.print', [
            'booking' => $booking,
            'printState' => $state,
            'ticketQrPayload' => $payloads->url($booking),
            'failureReasons' => TicketPrintService::FAILURE_REASONS,
            'printOperationId' => $operation['id'],
        ])->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    public function succeed(Request $request, Booking $booking, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        $booking = $tickets->authorizedBooking($booking, $request->user());
        try {
            $operation = $this->operation($request, $booking, allowCompleted: true);
            $prints->succeed($booking, $request->user(), $operation['id'], $operation['token']);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 410) {
                throw $exception;
            }

            return $this->recoverPrintNavigation($booking);
        }
        $this->completeOperationSession($request, $booking, $operation);

        return redirect()->route('staff.tickets.operations', $booking)
            ->with('success', 'Đã xác nhận in vé thành công.');
    }

    public function fail(Request $request, Booking $booking, TicketResolutionService $tickets, TicketPrintService $prints): RedirectResponse
    {
        $validated = $request->validate([
            'failure_code' => ['required', 'in:'.implode(',', array_keys(TicketPrintService::FAILURE_REASONS))],
            'safe_note' => ['nullable', 'string', 'max:300', 'required_if:failure_code,other'],
        ], [
            'failure_code.required' => 'Vui lòng chọn lý do in lỗi.',
            'safe_note.required_if' => 'Vui lòng mô tả ngắn gọn khi chọn lý do khác.',
        ]);
        $booking = $tickets->authorizedBooking($booking, $request->user());
        try {
            $operation = $this->operation($request, $booking, allowCompleted: true);
            $prints->fail($booking, $request->user(), $operation['id'], $operation['token'],
                $validated['failure_code'], $validated['safe_note'] ?? null);
            $this->completeOperationSession($request, $booking, $operation);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 410) {
                throw $exception;
            }
            $prints->failExpired($booking, $request->user(), $validated['failure_code'], $validated['safe_note'] ?? null);
        }

        return redirect()->route('staff.tickets.operations', $booking)
            ->with('success', 'Đã ghi nhận lỗi in vé.');
    }

    public function recoverExpired(
        Request $request,
        Booking $booking,
        TicketResolutionService $tickets,
        TicketPrintService $prints,
    ): RedirectResponse {
        $booking = $tickets->authorizedBooking($booking, $request->user());
        $prints->failExpired($booking, $request->user());

        return redirect()->route('staff.tickets.operations', $booking)
            ->with('success', 'Đã ghi nhận phiên in bị gián đoạn. Bạn có thể in lại theo chính sách hiện tại.');
    }

    /** @return array{id:string, token:string} */
    private function operation(Request $request, Booking $booking, bool $create = false, bool $allowCompleted = false): array
    {
        $key = $this->sessionKey($booking);
        $operation = $request->session()->get($key);
        if ($allowCompleted && ! is_array($operation)) {
            $operation = $request->session()->get($this->completedSessionKey($booking));
        }
        if ($create && (! is_array($operation) || ! isset($operation['id'], $operation['token']))) {
            $operation = ['id' => (string) Str::uuid(), 'token' => Str::random(64)];
            $request->session()->put($key, $operation);
        }
        abort_unless(is_array($operation) && is_string($operation['id'] ?? null)
            && is_string($operation['token'] ?? null), 410, 'Lần in này đã hết hiệu lực.');

        return $operation;
    }

    /** @return array{id:string, token:string}|null */
    private function currentOperation(Request $request, Booking $booking): ?array
    {
        $operation = $request->session()->get($this->sessionKey($booking));

        return is_array($operation) && is_string($operation['id'] ?? null)
            && is_string($operation['token'] ?? null) ? $operation : null;
    }

    private function recoverPrintNavigation(Booking $booking): RedirectResponse
    {
        $state = $booking->ticketPrint;
        $message = match ($state?->status) {
            'printed' => 'Vé này đã được ghi nhận in thành công.',
            'printing' => $state->active_operation_expires_at?->isPast()
                ? 'Phiên in trước đã hết hiệu lực. Vui lòng xác nhận kết quả lần in trước trước khi tiếp tục.'
                : 'Phiên in đang được xử lý trong một cửa sổ khác. Vui lòng quay lại cửa sổ đó để xác nhận kết quả.',
            'retry_allowed' => 'Lần in trước đã được báo lỗi. Vé đang được phép in lại theo chính sách hiện tại.',
            'retry_requires_authorization' => 'Cần quản lý phê duyệt in lại.',
            'retry_authorized' => 'Quản lý đã phê duyệt. Vui lòng bắt đầu lần in mới từ trang vận hành vé.',
            default => 'Không có phiên in hợp lệ. Vui lòng bắt đầu in từ trang vận hành vé.',
        };

        return redirect()->route('staff.tickets.operations', $booking)
            ->with($state?->status === 'printed' ? 'success' : 'warning', $message);
    }

    private function sessionKey(Booking $booking): string
    {
        return 'ticket_print_operations.'.$booking->id;
    }

    /** @param array{id:string, token:string} $operation */
    private function completeOperationSession(Request $request, Booking $booking, array $operation): void
    {
        $request->session()->put($this->completedSessionKey($booking), $operation);
        $request->session()->forget($this->sessionKey($booking));
    }

    private function completedSessionKey(Booking $booking): string
    {
        return 'ticket_print_completed_operations.'.$booking->id;
    }
}
