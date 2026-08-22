<?php

namespace App\Services;

use App\Exceptions\PriceBookException;
use App\Models\PriceBook;
use App\Models\PriceBookAdjustment;
use App\Models\PriceBookVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PriceBookVersionService
{
    public function __construct(private readonly PriceBookDefinitionValidator $validator) {}

    public function chainPriceBook(): PriceBook
    {
        $books = PriceBook::query()->limit(2)->get();
        if ($books->count() !== 1 || $books->first()->code !== PriceBook::CHAIN_CODE) {
            throw new PriceBookException(
                PriceBookException::BOOK_NOT_FOUND,
                'Exactly one MOVIEMATE_CHAIN PriceBook must exist.',
            );
        }

        return $books->first();
    }

    public function createDraft(PriceBook $priceBook, array $attributes = [], ?User $actor = null): PriceBookVersion
    {
        return DB::transaction(function () use ($priceBook, $attributes, $actor): PriceBookVersion {
            $locked = PriceBook::query()->lockForUpdate()->findOrFail($priceBook->getKey());
            $this->assertSingleton($locked);
            $next = ((int) PriceBookVersion::query()
                ->where('price_book_id', $locked->id)
                ->max('version_number')) + 1;

            return PriceBookVersion::query()->create([
                'price_book_id' => $locked->id,
                'version_number' => $next,
                'status' => PriceBookVersion::STATUS_DRAFT,
                'base_price_vnd' => $attributes['base_price_vnd'] ?? null,
                'effective_from' => $attributes['effective_from'] ?? null,
                'effective_until' => $attributes['effective_until'] ?? null,
                'created_by_user_id' => $actor?->id,
                'updated_by_user_id' => $actor?->id,
            ]);
        }, 3);
    }

    public function updateDraft(PriceBookVersion $version, array $attributes, ?User $actor = null): PriceBookVersion
    {
        return DB::transaction(function () use ($version, $attributes, $actor): PriceBookVersion {
            $locked = PriceBookVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->assertDraft($locked);
            $allowed = array_intersect_key($attributes, array_flip([
                'base_price_vnd', 'effective_from', 'effective_until',
            ]));
            $locked->fill([...$allowed, 'updated_by_user_id' => $actor?->id])->save();

            return $locked->refresh();
        }, 3);
    }

    /** @param list<array<string, mixed>> $definitions */
    public function replaceAdjustments(PriceBookVersion $version, array $definitions): PriceBookVersion
    {
        return DB::transaction(function () use ($version, $definitions): PriceBookVersion {
            $locked = PriceBookVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->assertDraft($locked);

            $candidates = collect($definitions)->map(function (array $definition) use ($locked): PriceBookAdjustment {
                return new PriceBookAdjustment([
                    ...$this->validator->canonicalize($definition),
                    'price_book_version_id' => $locked->id,
                ]);
            });
            $this->validator->validateAdjustments($candidates);

            $locked->adjustments()->delete();
            foreach ($candidates as $candidate) {
                $locked->adjustments()->create($candidate->attributesToArray());
            }

            return $locked->load('adjustments');
        }, 3);
    }

    public function publish(PriceBookVersion $version, ?User $actor = null): PriceBookVersion
    {
        return DB::transaction(function () use ($version, $actor): PriceBookVersion {
            $book = PriceBook::query()->lockForUpdate()->findOrFail($version->price_book_id);
            $this->assertSingleton($book);
            $locked = PriceBookVersion::query()->lockForUpdate()->with('adjustments')->findOrFail($version->getKey());
            $this->assertDraft($locked);
            $this->validator->validateForPublish($locked, $locked->adjustments);

            $overlap = PriceBookVersion::query()
                ->where('price_book_id', $book->id)
                ->where('status', PriceBookVersion::STATUS_PUBLISHED)
                ->where('id', '!=', $locked->id)
                ->where(function ($query) use ($locked): void {
                    $query->whereNull('effective_until')
                        ->orWhere('effective_until', '>', $locked->effective_from->toDateString());
                })
                ->when($locked->effective_until, function ($query) use ($locked): void {
                    $query->where('effective_from', '<', $locked->effective_until->toDateString());
                })
                ->exists();
            if ($overlap) {
                throw new PriceBookException(
                    PriceBookException::VERSION_OVERLAP,
                    'Published PriceBookVersion periods cannot overlap.',
                );
            }

            $locked->forceFill([
                'status' => PriceBookVersion::STATUS_PUBLISHED,
                'published_at' => now(),
                'updated_by_user_id' => $actor?->id,
            ])->save();

            return $locked->refresh()->load('adjustments');
        }, 3);
    }

    public function retire(PriceBookVersion $version, ?User $actor = null): PriceBookVersion
    {
        return DB::transaction(function () use ($version, $actor): PriceBookVersion {
            PriceBook::query()->lockForUpdate()->findOrFail($version->price_book_id);
            $locked = PriceBookVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            if ($locked->status !== PriceBookVersion::STATUS_PUBLISHED) {
                throw new PriceBookException(
                    PriceBookException::INVALID_TRANSITION,
                    'Only a published PriceBookVersion may be retired.',
                );
            }
            $locked->forceFill([
                'status' => PriceBookVersion::STATUS_RETIRED,
                'retired_at' => now(),
                'updated_by_user_id' => $actor?->id,
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    public function copyToDraft(
        PriceBookVersion $source,
        ?string $effectiveFrom = null,
        ?string $effectiveUntil = null,
        ?User $actor = null,
    ): PriceBookVersion {
        if ($source->status === PriceBookVersion::STATUS_DRAFT) {
            throw new PriceBookException(
                PriceBookException::INVALID_TRANSITION,
                'Only a published or retired PriceBookVersion can be copied.',
            );
        }

        return DB::transaction(function () use ($source, $effectiveFrom, $effectiveUntil, $actor): PriceBookVersion {
            $source = PriceBookVersion::query()->with('adjustments')->findOrFail($source->id);
            $draft = $this->createDraft($source->priceBook, [
                'base_price_vnd' => $source->base_price_vnd,
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
            ], $actor);
            foreach ($source->adjustments as $adjustment) {
                $draft->adjustments()->create($adjustment->only([
                    'dimension', 'label', 'amount_vnd', 'seat_type_id', 'room_type_id',
                    'cinema_id', 'room_id', 'time_start', 'time_end',
                    'holiday_date_from', 'holiday_date_until', 'weekend_days',
                ]));
            }

            return $draft->load('adjustments');
        }, 3);
    }

    public function deleteDraft(PriceBookVersion $version): void
    {
        DB::transaction(function () use ($version): void {
            $locked = PriceBookVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->assertDraft($locked);
            $locked->delete();
        }, 3);
    }

    private function assertSingleton(PriceBook $priceBook): void
    {
        if ($priceBook->code !== PriceBook::CHAIN_CODE || PriceBook::query()->whereKeyNot($priceBook->id)->exists()) {
            throw new PriceBookException(
                PriceBookException::BOOK_NOT_FOUND,
                'Exactly one MOVIEMATE_CHAIN PriceBook may be managed.',
            );
        }
    }

    private function assertDraft(PriceBookVersion $version): void
    {
        if ($version->status !== PriceBookVersion::STATUS_DRAFT) {
            throw PriceBookException::immutable();
        }
    }
}
