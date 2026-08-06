<?php

namespace App\Services;

use App\Domain\Money\VndAmount;
use App\Domain\Pricing\TicketPrice;
use App\Exceptions\PricingConfigurationException;
use App\Models\CinemaPricingRule;
use App\Models\Seat;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class TicketPricingService
{
    /** @var array<string, Collection<int, CinemaPricingRule>> */
    private array $ruleCache = [];

    public function calculate(Showtime $showtime, string $seatType, bool $allowLegacySnapshot = true): TicketPrice
    {
        $seatType = strtolower($seatType);
        if (! in_array($seatType, Seat::TYPES, true)) {
            $seatType = 'normal';
        }

        if ($allowLegacySnapshot && ! $showtime->exists && ! $showtime->relationLoaded('room')) {
            return $this->legacySnapshot($showtime, $seatType);
        }

        $showtime->loadMissing(['room', 'cinema']);
        if (! $showtime->room || ! $showtime->cinema || (int) $showtime->room->cinema_id !== (int) $showtime->cinema_id) {
            throw new PricingConfigurationException('Chi nhánh hoặc phòng chiếu của suất chiếu không hợp lệ.');
        }

        $timezone = $this->timezone($showtime);
        $localStart = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $showtime->show_date->format('Y-m-d').' '.substr((string) $showtime->show_time, 0, 8),
            $timezone,
        );
        if (! $localStart) {
            throw new PricingConfigurationException('Thời gian suất chiếu không hợp lệ.');
        }

        $rules = $this->rulesFor($showtime, $timezone);
        $base = $this->winner($rules->where('rule_type', 'base'), $showtime, $localStart, $seatType);
        if (! $base) {
            if ($allowLegacySnapshot && (! $showtime->exists || $showtime->pricing_version === null)) {
                return $this->legacySnapshot($showtime, $seatType);
            }
            throw new PricingConfigurationException;
        }

        $matched = collect();
        foreach (['seat_type', 'room_type', 'time_window', 'weekend', 'holiday', 'cinema_adjustment', 'room_adjustment'] as $type) {
            $winner = $this->winner($rules->where('rule_type', $type), $showtime, $localStart, $seatType);
            if ($winner) {
                $matched->put($type, $winner);
            }
        }
        if ($matched->has('holiday') && ! $matched->get('holiday')->stacks_with_weekend) {
            $matched->forget('weekend');
        }

        $baseAmount = (int) $base->amount_vnd;
        $surcharges = $matched->map(fn (CinemaPricingRule $rule, string $type): array => [
            'type' => $type,
            'label' => $this->label($type, $seatType, (string) $showtime->room->room_type),
            'amount' => (int) $rule->amount_vnd,
            'rule_name' => $rule->name,
        ])->values()->all();
        $surchargeTotal = array_sum(array_column($surcharges, 'amount'));
        $finalAmount = $baseAmount + $surchargeTotal;
        if ($baseAmount < 0 || $finalAmount < 0 || $finalAmount > Showtime::MAX_PRICE) {
            throw new PricingConfigurationException('Tổng giá vé theo cấu hình không hợp lệ.');
        }

        $fingerprint = hash('sha256', json_encode([
            'version' => 'cinema-pricing-v1', 'showtime' => $showtime->getKey(), 'seat_type' => $seatType,
            'rules' => collect([$base])->merge($matched->values())->map(fn ($rule) => [$rule->id, $rule->updated_at?->format('c')])->all(),
            'amount' => $finalAmount,
        ], JSON_THROW_ON_ERROR));

        return new TicketPrice($finalAmount, $baseAmount, $surchargeTotal, $seatType, $base->name, $surcharges, $fingerprint);
    }

    /** @return array<string, TicketPrice> */
    public function calculateSeatTypes(Showtime $showtime, array $seatTypes = Seat::TYPES, bool $allowLegacySnapshot = true): array
    {
        return collect($seatTypes)->unique()->mapWithKeys(fn (string $type): array => [
            $type => $this->calculate($showtime, $type, $allowLegacySnapshot),
        ])->all();
    }

    /** @return Collection<int, CinemaPricingRule> */
    private function rulesFor(Showtime $showtime, string $timezone): Collection
    {
        $key = implode(':', [$showtime->cinema_id, $showtime->room_id, $timezone]);
        if (isset($this->ruleCache[$key])) {
            return $this->ruleCache[$key];
        }

        $now = CarbonImmutable::now($timezone);

        return $this->ruleCache[$key] = CinemaPricingRule::query()
            ->active()
            ->where(function ($query) use ($showtime): void {
                $query->whereNull('cinema_id')->orWhere('cinema_id', $showtime->cinema_id);
            })
            ->where(function ($query) use ($showtime): void {
                $query->whereNull('room_id')->orWhere('room_id', $showtime->room_id);
            })
            ->with(['cinema:id,timezone', 'room:id,cinema_id'])
            ->orderBy('id')
            ->get()
            ->filter(fn (CinemaPricingRule $rule): bool => (! $rule->starts_at || $now->greaterThanOrEqualTo($rule->starts_at))
                && (! $rule->ends_at || $now->lessThan($rule->ends_at))
                && (! $rule->room_id || (int) $rule->room?->cinema_id === (int) $showtime->cinema_id)
            )->values();
    }

    private function winner(Collection $rules, Showtime $showtime, CarbonImmutable $start, string $seatType): ?CinemaPricingRule
    {
        return $rules->filter(fn (CinemaPricingRule $rule): bool => $this->matches($rule, $showtime, $start, $seatType))
            ->sort(function (CinemaPricingRule $a, CinemaPricingRule $b): int {
                $specificity = $this->specificity($b) <=> $this->specificity($a);
                if ($specificity !== 0) {
                    return $specificity;
                }
                $priority = $b->priority <=> $a->priority;

                return $priority !== 0 ? $priority : ($a->id <=> $b->id);
            })->first();
    }

    private function matches(CinemaPricingRule $rule, Showtime $showtime, CarbonImmutable $start, string $seatType): bool
    {
        if ($rule->cinema_id && (int) $rule->cinema_id !== (int) $showtime->cinema_id) {
            return false;
        }
        if ($rule->room_id && (int) $rule->room_id !== (int) $showtime->room_id) {
            return false;
        }
        if ($rule->seat_type && $rule->seat_type !== $seatType) {
            return false;
        }
        if ($rule->room_type && $rule->room_type !== $showtime->room->room_type) {
            return false;
        }

        $date = $start->toDateString();
        if ($rule->date_start && $date < $rule->date_start->toDateString()) {
            return false;
        }
        if ($rule->date_end && $date > $rule->date_end->toDateString()) {
            return false;
        }

        if ($rule->rule_type === 'seat_type' && $rule->seat_type !== $seatType) {
            return false;
        }
        if ($rule->rule_type === 'room_type' && $rule->room_type !== $showtime->room->room_type) {
            return false;
        }
        if ($rule->rule_type === 'weekend') {
            $days = $rule->days_of_week ?: [6, 7];
            if (! in_array($start->dayOfWeekIso, array_map('intval', $days), true)) {
                return false;
            }
        }
        if ($rule->rule_type === 'holiday' && ! $rule->date_start) {
            return false;
        }
        if ($rule->rule_type === 'holiday' && $rule->date_start && ! $rule->date_end && $date !== $rule->date_start->toDateString()) {
            return false;
        }
        if ($rule->rule_type === 'time_window' && ! $this->timeMatches($start->format('H:i:s'), $rule->time_start, $rule->time_end)) {
            return false;
        }

        return true;
    }

    private function timeMatches(string $time, ?string $start, ?string $end): bool
    {
        if (! $start || ! $end || $start === $end) {
            return false;
        }
        $start = strlen($start) === 5 ? $start.':00' : substr($start, 0, 8);
        $end = strlen($end) === 5 ? $end.':00' : substr($end, 0, 8);

        return $start < $end ? $time >= $start && $time < $end : $time >= $start || $time < $end;
    }

    private function specificity(CinemaPricingRule $rule): int
    {
        return $rule->room_id ? 3 : ($rule->cinema_id ? 2 : 1);
    }

    private function timezone(Showtime $showtime): string
    {
        $timezone = (string) $showtime->cinema->timezone;
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new PricingConfigurationException('Múi giờ của chi nhánh không hợp lệ.');
        }

        return $timezone;
    }

    private function legacySnapshot(Showtime $showtime, string $seatType): TicketPrice
    {
        $base = VndAmount::fromDatabase($showtime->getRawOriginal('price') ?? $showtime->price)->value();
        $final = match ($seatType) {
            'vip' => $showtime->vip_price === null ? $base : VndAmount::fromDatabase($showtime->getRawOriginal('vip_price') ?? $showtime->vip_price)->value(),
            'couple' => $base * (int) config('booking.couple_price_multiplier', 2),
            default => $base,
        };

        return new TicketPrice($final, $base, $final - $base, $seatType, 'Giá suất chiếu lịch sử', [], hash('sha256', "legacy:{$showtime->id}:{$seatType}:{$final}"));
    }

    private function label(string $type, string $seatType, string $roomType): string
    {
        return match ($type) {
            'seat_type' => $seatType === 'couple' ? 'Phụ thu ghế đôi' : 'Phụ thu '.mb_strtoupper($seatType),
            'room_type' => 'Phụ thu phòng '.$roomType,
            'time_window' => 'Phụ thu khung giờ', 'weekend' => 'Phụ thu cuối tuần',
            'holiday' => 'Phụ thu ngày đặc biệt', 'cinema_adjustment' => 'Điều chỉnh chi nhánh',
            'room_adjustment' => 'Điều chỉnh phòng', default => 'Phụ thu',
        };
    }
}
