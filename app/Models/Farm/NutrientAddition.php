<?php

namespace App\Models\Farm;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutrientAddition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'tank_id',
        'log_date',
        'ppm_before',
        'ppm_after',
        'nutrient_a_ml',
        'nutrient_b_ml',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'ppm_before' => 'decimal:2',
            'ppm_after' => 'decimal:2',
            'nutrient_a_ml' => 'decimal:2',
            'nutrient_b_ml' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }
}
