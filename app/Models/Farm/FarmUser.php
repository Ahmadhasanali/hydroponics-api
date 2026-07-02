<?php

namespace App\Models\Farm;

use Database\Factories\Farm\FarmUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FarmUser extends Pivot
{
    /** @use HasFactory<FarmUserFactory> */
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'user_id',
        'role',
    ];
}
