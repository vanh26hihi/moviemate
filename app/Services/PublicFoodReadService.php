<?php

namespace App\Services;

use App\Models\FoodItem;

final class PublicFoodReadService
{
    public const MAX_RESULTS = 12;

    /** @return array<string, mixed> */
    public function list(?string $query = null, ?string $cinemaCode = null, int $limit = self::MAX_RESULTS): array
    {
        $limit = max(1, min(self::MAX_RESULTS, $limit));

        if (filled($cinemaCode)) {
            return [
                'scope' => 'public_catalog',
                'branch_availability_confirmed' => false,
                'message' => 'MovieMate chưa có nguồn công khai đủ thẩm quyền để xác nhận món theo từng chi nhánh.',
                'items' => [],
            ];
        }

        $query = is_string($query) ? mb_substr(str_replace(['%', '_'], '', trim($query)), 0, 100) : '';
        $items = FoodItem::query()->where('active', true)
            ->when($query !== '', fn ($builder) => $builder->where('name', 'like', "%{$query}%"))
            ->orderBy('name')->orderBy('id')->limit($limit)->get()
            ->map(fn (FoodItem $food): array => [
                'id' => (int) $food->id,
                'name' => $food->name,
                'description' => $food->description,
                'price_vnd' => (int) $food->price,
                'image_url' => $food->image ? asset('storage/'.ltrim($food->image, '/')) : null,
            ])->values()->all();

        return [
            'scope' => 'public_catalog',
            'branch_availability_confirmed' => false,
            'message' => 'Danh mục này phản ánh trang món ăn công khai; khả dụng tại chi nhánh chỉ được xác nhận trong luồng đặt vé.',
            'items' => $items,
        ];
    }
}
