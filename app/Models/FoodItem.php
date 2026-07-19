<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'price', 'image', 'active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];
    <?php

namespace App\Services;

class MegaPaginationService
{
    public function paginate($data, $page = 1, $perPage = 20)
    {
        $total = count($data);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($data, $offset, $perPage),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ];
    }
}
}
