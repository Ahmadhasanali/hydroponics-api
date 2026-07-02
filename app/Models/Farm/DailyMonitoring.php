<?php

namespace App\Models\Farm;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyMonitoring extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'tank_id',
        'log_date',
        'ppm',
        'ph',
        'water_temperature',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'ppm' => 'decimal:2',
            'ph' => 'decimal:2',
            'water_temperature' => 'decimal:2',
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
