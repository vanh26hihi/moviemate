<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PriceBook extends Model
{
    public const CHAIN_CODE = 'MOVIEMATE_CHAIN';

    protected $fillable = ['code', 'name'];

    public function versions(): HasMany
    {
        return $this->hasMany(PriceBookVersion::class);
    }
}
