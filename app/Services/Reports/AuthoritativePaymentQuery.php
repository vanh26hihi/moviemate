<?php

namespace App\Services\Reports;

use App\Models\Payment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AuthoritativePaymentQuery
{
    public function financePaidAtExpression(string $alias = 'p'): string
    {
        return "CASE WHEN {$alias}.provider = '".Payment::PROVIDER_COUNTER_CASH."' THEN {$alias}.settled_at ELSE {$alias}.verified_at END";
    }

    public function ranked(): Builder
    {
        $paidAt = $this->financePaidAtExpression();

        return DB::table('payments as p')
            ->select([
                'p.id as payment_id', 'p.booking_id', 'p.provider', 'p.amount', 'p.currency',
                'p.status', 'p.settled_by_user_id',
            ])
            ->selectRaw("{$paidAt} as finance_paid_at")
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY p.booking_id ORDER BY {$paidAt} ASC, p.id ASC) as evidence_rank")
            ->where('p.status', Payment::STATUS_SUCCESS)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $counter): void {
                    $counter->where('p.provider', Payment::PROVIDER_COUNTER_CASH)
                        ->whereNotNull('p.settled_at')
                        ->whereNotNull('p.settled_by_user_id');
                })->orWhere(function (Builder $online): void {
                    $online->whereIn('p.provider', Payment::SUPPORTED_PROVIDERS)
                        ->whereNotNull('p.verified_at');
                })->orWhere(function (Builder $internalZero): void {
                    $internalZero->where('p.provider', Payment::PROVIDER_INTERNAL_ZERO)
                        ->whereNotNull('p.verified_at');
                });
            });
    }

    public function authoritative(): Builder
    {
        return DB::query()->fromSub($this->ranked(), 'authoritative_payments')
            ->where('evidence_rank', 1);
    }
}
