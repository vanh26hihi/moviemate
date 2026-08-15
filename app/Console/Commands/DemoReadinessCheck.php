<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingPromotion;
use App\Models\Cinema;
use App\Models\FoodItem;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\PromotionService;
use App\Services\PublicShowtimeCatalog;
use App\Services\Reports\AuthoritativePaymentQuery;
use App\Services\ShowtimeLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

final class DemoReadinessCheck extends Command
{
    protected $signature = 'moviemate:demo-check';

    protected $description = 'Kiểm tra chỉ đọc các fixture cần cho rehearsal bảo vệ';

    /** @var list<string> */
    private array $failures = [];

    public function __construct(
        private readonly PublicShowtimeCatalog $catalog,
        private readonly ShowtimeLifecycleService $lifecycle,
        private readonly PromotionService $promotions,
        private readonly AuthoritativePaymentQuery $authoritativePayments,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cinema = Cinema::query()->where('code', 'CG')->where('status', 'active')->first();
        $room = Room::query()->with(['cinema', 'roomType', 'latestPublishedLayout.cells.seat.seatType'])
            ->where('code', 'DEMO')->where('cinema_id', $cinema?->id)->first();
        $customer = $this->account('customer@moviemate.test', 'user');
        $manager = $this->account('manager.cg@moviemate.test', 'manager');
        $staff = $this->account('staff.cg@moviemate.test', 'staff');
        $admin = $this->account('admin@moviemate.test', 'admin');

        $this->expect($cinema !== null, 'Không tìm thấy chi nhánh active có code CG.');
        $this->expect($room !== null, 'Không tìm thấy Room DEMO thuộc chi nhánh CG.');
        if (! $cinema || ! $room || ! $customer || ! $manager || ! $staff || ! $admin) {
            return $this->finish();
        }

        $this->expect($this->assignedTo($manager, $cinema), 'Manager CG không được gán active vào chi nhánh CG.');
        $this->expect($this->assignedTo($staff, $cinema), 'Staff CG không được gán active vào chi nhánh CG.');

        $layout = $room->latestPublishedLayout;
        $this->expect($layout !== null && $layout->status === 'published', 'Room DEMO thiếu RoomLayout đã phát hành.');
        if (! $layout) {
            return $this->finish();
        }

        $cells = $layout->cells;
        $maintenance = $room->seats()->where('status', Seat::STATUS_MAINTENANCE)->orderBy('seat_code')->get();
        $this->expect($cells->where('cell_type', 'seat')->count() === 28, 'RoomLayout DEMO không còn đúng 28 Seat cells.');
        $this->expect($cells->where('cell_type', 'aisle')->isNotEmpty(), 'RoomLayout DEMO thiếu AISLE.');
        $this->expect($cells->where('cell_type', 'blocked')->isNotEmpty(), 'RoomLayout DEMO thiếu BLOCKED.');
        $this->expect($maintenance->isNotEmpty(), 'Room DEMO thiếu Seat bảo trì.');

        $showtimes = $this->sellableShowtimes($room);
        $primary = $showtimes->get(0);
        $fallback = $showtimes->get(1);
        $this->expect($primary instanceof Showtime, 'Không có Showtime DEMO đủ điều kiện customer booking.');
        $this->expect($fallback instanceof Showtime, 'Không có fallback Showtime DEMO đủ điều kiện customer booking.');

        $promotion = Promotion::query()->where('code', 'MOVIEMATE10')->first();
        $this->expect($promotion !== null, 'Không tìm thấy Promotion MOVIEMATE10.');
        if ($promotion) {
            try {
                $quote = $this->promotions->quote(100_000, $promotion->code, $customer->id, $cinema->id);
                $this->expect($quote->discountAmount === 10_000, 'MOVIEMATE10 không còn quote 10% cho gross 100.000 VND.');
            } catch (Throwable $exception) {
                $this->failures[] = 'MOVIEMATE10 không đủ điều kiện: '.$exception->getMessage();
            }
        }

        $booking = Booking::query()->with([
            'user:id,email', 'cinema:id,code,name', 'showtime.movie:id,title,duration',
            'showtime.room:id,code,name,room_type_id', 'showtime.room.roomType:id,code,name',
            'showtime.presentationFormat:id,code,name', 'bookingSeats.seat:id,seat_code',
            'admissionTickets', 'foodOrder.items.food:id,name', 'foodPickupVoucher', 'promotionUsage',
        ])->where('booking_code', 'like', '%-0000000000000004')->first();
        $this->expect($booking !== null, 'Không tìm thấy paid Booking fixture có suffix 0000000000000004.');

        $payment = null;
        if ($booking) {
            $paymentRow = $this->authoritativePayments->authoritative()
                ->where('booking_id', $booking->id)->first();
            $payment = $paymentRow ? Payment::query()->find($paymentRow->payment_id) : null;
            $this->expect($booking->cinema?->code === 'CG', 'Paid Booking fixture không thuộc chi nhánh CG.');
            $this->expect($booking->user?->email === $customer->email, 'Paid Booking fixture không thuộc demo Customer.');
            $this->expect($booking->booking_status === 'paid' && $booking->payment_status === 'paid', 'Paid Booking fixture chưa ở trạng thái paid.');
            $this->expect($payment !== null && $payment->hasAuthoritativeSuccessEvidence(), 'Paid Booking fixture thiếu Payment evidence authoritative.');
            $this->expect($payment !== null && $payment->amount === (int) $booking->total_amount, 'Payment amount không khớp Booking total.');
            $this->expect($booking->promotionUsage?->code_snapshot === 'MOVIEMATE10'
                && $booking->promotionUsage?->status === BookingPromotion::STATUS_REDEEMED, 'Paid Booking fixture thiếu redeemed MOVIEMATE10 snapshot.');
            $this->expect($booking->foodOrder?->items->isNotEmpty() === true, 'Paid Booking fixture thiếu Food snapshot.');
            $this->expect($booking->admissionTickets->isNotEmpty(), 'Paid Booking fixture thiếu AdmissionTicket.');
            $this->expect($booking->foodPickupVoucher !== null, 'Paid Booking fixture thiếu FoodPickupVoucher.');
            $this->expect($booking->admissionTickets->every(fn ($ticket): bool => $ticket->print_count === 0), 'AdmissionTicket fixture đã tiêu thụ first-print state.');
            $this->expect($booking->foodPickupVoucher?->print_count === 0, 'FoodPickupVoucher fixture đã tiêu thụ first-print state.');
            $this->expect($this->assignedTo($staff, $cinema), 'Staff không thể dùng paid Booking fixture theo branch scope.');
        }

        $food = FoodItem::query()->where('active', true)->orderBy('id')->first();
        $this->expect($food !== null, 'Không có Food item active cho customer demo.');

        $this->renderAccounts($admin, $manager, $staff, $customer, $cinema);
        $this->renderRoom($room, $layout, $cells, $maintenance);
        $this->renderShowtimes($primary, $fallback);
        $this->renderPromotion($promotion, $customer);
        $this->renderBooking($booking, $payment, $food, $cinema);

        return $this->finish();
    }

    private function account(string $email, string $role): ?User
    {
        $user = User::query()->with('role')->where('email', $email)->first();
        $this->expect($user !== null && $user->status === 'active' && $user->role?->slug === $role, "Tài khoản {$email} thiếu, inactive hoặc sai role.");

        return $user;
    }

    private function assignedTo(User $user, Cinema $cinema): bool
    {
        return $user->cinemaAssignments()->where('cinema_id', $cinema->id)->where('status', 'active')->exists();
    }

    /** @return Collection<int, Showtime> */
    private function sellableShowtimes(Room $room): Collection
    {
        return Showtime::query()->with([
            'movie', 'cinema.operatingHours', 'room.cinema.operatingHours', 'roomLayout.cells.seat',
            'presentationFormat', 'ticketPrices.seatType',
        ])->where('room_id', $room->id)->where('status', 'active')
            ->orderBy('show_date')->orderBy('show_time')->limit(40)->get()
            ->filter(fn (Showtime $showtime): bool => $this->catalog->isCustomerSellable($showtime))
            ->values();
    }

    private function renderAccounts(User $admin, User $manager, User $staff, User $customer, Cinema $cinema): void
    {
        $this->newLine();
        $this->info('Accounts');
        $this->table(['Role', 'Email', 'Scope'], [
            ['Global Admin', $admin->email, 'Toàn chuỗi'],
            ['Manager', $manager->email, $cinema->code],
            ['Staff', $staff->email, $cinema->code],
            ['Customer', $customer->email, 'Sở hữu Booking demo'],
        ]);
    }

    private function renderRoom(Room $room, $layout, Collection $cells, Collection $maintenance): void
    {
        $this->info('Branch / Room / Layout');
        $this->line("{$room->cinema->name} [{$room->cinema->code}] → {$room->name} [{$room->code}]");
        $this->line(sprintf(
            'Kích thước: %.2f m × %.2f m = %s m² | Layout: %s v%d | Lưới: %d × %d',
            $room->width_mm / 1000,
            $room->length_mm / 1000,
            $room->formattedAreaM2(),
            $layout->name,
            $layout->version,
            $layout->rows,
            $layout->columns,
        ));
        $pricingUnits = $room->seats()->where('type', '!=', 'couple')->count()
            + $room->seats()->where('type', 'couple')->distinct()->count('pair_code');
        $occupiedCoordinates = $cells->mapWithKeys(
            fn ($cell): array => [$cell->x_position.'x'.$cell->y_position => true],
        );
        $emptyCoordinates = collect();
        for ($y = 1; $y <= $layout->rows; $y++) {
            for ($x = 1; $x <= $layout->columns; $x++) {
                $coordinate = $x.'x'.$y;
                if (! $occupiedCoordinates->has($coordinate)) {
                    $emptyCoordinates->push($coordinate);
                }
            }
        }
        $this->line('Seat cells: '.$cells->where('cell_type', 'seat')->count()
            .' | operational active: '.$room->seats()->where('status', Seat::STATUS_ACTIVE)->count()
            .' | pricing units: '.$pricingUnits);
        $this->line('AISLE: '.$cells->where('cell_type', 'aisle')->map(fn ($cell) => $cell->x_position.'x'.$cell->y_position)->join(', '));
        $this->line('BLOCKED: '.$cells->where('cell_type', 'blocked')->map(fn ($cell) => $cell->x_position.'x'.$cell->y_position)->join(', '));
        $this->line('EMPTY: '.$emptyCoordinates->join(', '));
        $this->line('Maintenance Seat: '.$maintenance->pluck('seat_code')->join(', '));
    }

    private function renderShowtimes(?Showtime $primary, ?Showtime $fallback): void
    {
        $this->info('Showtime');
        foreach (['PRIMARY' => $primary, 'FALLBACK' => $fallback] as $label => $showtime) {
            if (! $showtime) {
                continue;
            }
            $snapshot = $this->lifecycle->snapshot($showtime);
            $prices = $showtime->ticketPrices->mapWithKeys(fn ($price) => [
                $price->seatType->code => number_format($price->final_unit_amount_vnd, 0, ',', '.').' VND',
            ])->map(fn ($amount, $type) => "{$type}: {$amount}")->join(', ');
            $this->line(sprintf(
                '%s: %s | %s | %s | START %s | END %s | ROOM_READY %s | cutoff %s | prices %s',
                $label,
                $showtime->movie->title,
                $showtime->presentationFormat->code,
                $showtime->room->code,
                $snapshot['starts_at']->format('d/m/Y H:i'),
                $snapshot['ends_at']->format('H:i'),
                $snapshot['room_ready_at']->format('H:i'),
                $snapshot['booking_closes_at']->format('d/m/Y H:i'),
                $prices,
            ));
        }
    }

    private function renderPromotion(?Promotion $promotion, User $customer): void
    {
        if (! $promotion) {
            return;
        }
        $usage = $promotion->usages()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $this->info('Promotion');
        $this->line(sprintf(
            '%s | %d%% | cap %s | reserved %d / redeemed %d / released %d | Customer %s',
            $promotion->code,
            $promotion->discount_percent,
            number_format((int) $promotion->maximum_discount_vnd, 0, ',', '.').' VND',
            (int) ($usage[BookingPromotion::STATUS_RESERVED] ?? 0),
            (int) ($usage[BookingPromotion::STATUS_REDEEMED] ?? 0),
            (int) ($usage[BookingPromotion::STATUS_RELEASED] ?? 0),
            $customer->email,
        ));
    }

    private function renderBooking(?Booking $booking, ?Payment $payment, ?FoodItem $food, Cinema $cinema): void
    {
        $this->info('Paid Booking / Print / Report');
        if (! $booking || ! $payment) {
            $this->line('Fixture chưa sẵn sàng.');

            return;
        }
        $evidenceAt = $payment->provider === Payment::PROVIDER_COUNTER_CASH ? $payment->settled_at : $payment->verified_at;
        $reportDate = CarbonImmutable::instance($evidenceAt)->setTimezone($cinema->timezone)->toDateString();
        $this->line('Booking: '.$booking->booking_code.' | Movie: '.$booking->showtime->movie->title
            .' | seats: '.$booking->bookingSeats->pluck('seat.seat_code')->join(', ')
            .' | Food: '.$booking->foodOrder->items->map(fn ($item) => $item->food->name.' × '.$item->quantity)->join(', '));
        $this->line('Gross: '.number_format((int) $booking->gross_amount, 0, ',', '.').' VND | Promotion: '
            .$booking->promotionUsage->code_snapshot.' | payable/payment: '.number_format($payment->amount, 0, ',', '.').' VND');
        $this->line('Payment: '.$payment->provider.' / '.$payment->status.' | evidence: '.$evidenceAt->toIso8601String()
            .' | report filter: cinema '.$cinema->code.', date '.$reportDate);
        $this->line('AdmissionTickets: '.$booking->admissionTickets->count().' (printed '
            .$booking->admissionTickets->where('print_count', '>', 0)->count().') | FoodPickupVoucher: '
            .($booking->foodPickupVoucher ? 'yes, printed '.$booking->foodPickupVoucher->print_count : 'missing')
            .' | active Food example: '.($food?->name ?? 'missing'));
    }

    private function expect(bool $condition, string $message): void
    {
        if (! $condition) {
            $this->failures[] = $message;
        }
    }

    private function finish(): int
    {
        if ($this->failures === []) {
            $this->newLine();
            $this->info('DEMO READY: tất cả fixture bắt buộc đạt yêu cầu; command không thay đổi dữ liệu.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('DEMO NOT READY:');
        foreach (array_unique($this->failures) as $failure) {
            $this->line('- '.$failure);
        }

        return self::FAILURE;
    }
}
