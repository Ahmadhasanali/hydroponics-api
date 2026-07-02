<?php

namespace App\Models\Farm;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tank extends Model
{
    /** @use HasFactory<TankFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'farm_id',
        'created_by',
        'name',
        'capacity_liter',
        'target_ppm_min',
        'target_ppm_max',
        'target_ph_min',
        'target_ph_max',
        'notes',
        'is_active',
        'current_ppm',
        'current_ph',
        'current_water_temperature',
        'last_condition_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity_liter' => 'decimal:2',
            'target_ppm_min' => 'decimal:2',
            'target_ppm_max' => 'decimal:2',
            'target_ph_min' => 'decimal:2',
            'target_ph_max' => 'decimal:2',
            'current_ppm' => 'decimal:2',
            'current_ph' => 'decimal:2',
            'current_water_temperature' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Farm,Tank>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * @return BelongsTo<User,Tank>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DailyMonitoring,Tank>
     */
    public function dailyMonitorings(): HasMany
    {
        return $this->hasMany(DailyMonitoring::class);
    }

    /**
     * @return HasMany<NutrientAddition,Tank>
     */
    public function nutrientAdditions(): HasMany
    {
        return $this->hasMany(NutrientAddition::class);
    }

    /**
     * @return HasMany<PhDownLog,Tank>
     */
    public function phDownLogs(): HasMany
    {
        return $this->hasMany(PhDownLog::class);
    }

    public function recalculateCurrentState(): void
    {
        $records = collect();

        foreach ($this->dailyMonitorings()->get() as $m) {
            $records->push([
                'sort' => $m->log_date->format('Y-m-d').' '.($m->created_at?->format('Y-m-d H:i:s') ?? '00:00:00'),
                'ppm' => $m->ppm,
                'ph' => $m->ph,
                'water_temperature' => $m->water_temperature,
                'timestamp' => $m->log_date,
            ]);
        }

        foreach ($this->nutrientAdditions()->get() as $n) {
            $records->push([
                'sort' => $n->log_date->format('Y-m-d').' '.($n->created_at?->format('Y-m-d H:i:s') ?? '00:00:00'),
                'ppm' => $n->ppm_after,
                'timestamp' => $n->log_date,
            ]);
        }

        foreach ($this->phDownLogs()->get() as $p) {
            $records->push([
                'sort' => $p->log_date->format('Y-m-d').' '.($p->created_at?->format('Y-m-d H:i:s') ?? '00:00:00'),
                'ph' => $p->ph_after,
                'timestamp' => $p->log_date,
            ]);
        }

        if ($records->isEmpty()) {
            $this->update([
                'current_ppm' => null,
                'current_ph' => null,
                'current_water_temperature' => null,
                'last_condition_updated_at' => null,
            ]);

            return;
        }

        $sorted = $records->sortBy('sort')->values();

        $currentPpm = null;
        $currentPh = null;
        $currentWaterTemperature = null;
        $lastTimestamp = null;

        foreach ($sorted as $record) {
            if (array_key_exists('ppm', $record)) {
                $currentPpm = $record['ppm'];
            }
            if (array_key_exists('ph', $record)) {
                $currentPh = $record['ph'];
            }
            if (array_key_exists('water_temperature', $record)) {
                $currentWaterTemperature = $record['water_temperature'];
            }
            $lastTimestamp = $record['timestamp'];
        }

        $this->update([
            'current_ppm' => $currentPpm,
            'current_ph' => $currentPh,
            'current_water_temperature' => $currentWaterTemperature,
            'last_condition_updated_at' => $lastTimestamp,
        ]);
    }
}
