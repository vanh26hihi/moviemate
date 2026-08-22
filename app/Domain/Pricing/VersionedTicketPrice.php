<?php

namespace App\Domain\Pricing;

final readonly class VersionedTicketPrice
{
    /**
     * @param  list<array{dimension:string,adjustment_id:int,label:string,reference:int|null,amount_vnd:int}>  $adjustments
     */
    public function __construct(
        public int $priceBookId,
        public string $priceBookCode,
        public int $priceBookVersionId,
        public int $versionNumber,
        public string $businessDate,
        public int $basePriceVnd,
        public array $adjustments,
        public int $finalUnitAmountVnd,
        public string $fingerprint,
    ) {}

    public function breakdown(): array
    {
        return [
            'price_book_id' => $this->priceBookId,
            'price_book_code' => $this->priceBookCode,
            'price_book_version_id' => $this->priceBookVersionId,
            'version_number' => $this->versionNumber,
            'business_date' => $this->businessDate,
            'base' => [
                'dimension' => 'base',
                'adjustment_id' => null,
                'label' => 'Giá cơ bản',
                'reference' => null,
                'amount_vnd' => $this->basePriceVnd,
            ],
            'adjustments' => $this->adjustments,
            'final_unit_amount_vnd' => $this->finalUnitAmountVnd,
            'fingerprint' => $this->fingerprint,
        ];
    }
}
