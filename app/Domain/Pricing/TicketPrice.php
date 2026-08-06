<?php

namespace App\Domain\Pricing;

final readonly class TicketPrice
{
    /** @param list<array{type: string, label: string, amount: int, rule_name: string}> $surcharges */
    public function __construct(
        public int $finalAmount,
        public int $baseAmount,
        public int $surchargeTotal,
        public string $seatType,
        public string $baseRuleName,
        public array $surcharges,
        public string $fingerprint,
    ) {}

    public function breakdown(): array
    {
        return [
            'version' => 'cinema-pricing-v1',
            'base' => ['label' => 'Giá cơ bản', 'amount' => $this->baseAmount, 'rule_name' => $this->baseRuleName],
            'surcharges' => $this->surcharges,
            'surcharge_total' => $this->surchargeTotal,
            'final_amount' => $this->finalAmount,
        ];
    }
}
