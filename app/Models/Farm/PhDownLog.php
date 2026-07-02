<?php

namespace App\Models\Farm;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhDownLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'tank_id',
        'log_date',
        'ph_before',
        'ph_after',
        'ph_down_ml',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'ph_before' => 'decimal:2',
            'ph_after' => 'decimal:2',
            'ph_down_ml' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (PhDownLog $log) {
            if ($log->wasChanged('tank_id')) {
                Tank::find($log->getOriginal('tank_id'))?->recalculateCurrentState();
            }
            $log->tank->recalculateCurrentState();
        });

        static::deleted(function (PhDownLog $log) {
            $log->tank->recalculateCurrentState();
        });
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
