<?php

namespace App\Models;

use App\Models\Farm\ActivityLog;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'created_by',
        'name',
        'address',
        'description',
    ];

    /**
     * @return BelongsTo<User,Farm>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'farm_users')
            ->using(Farm\FarmUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Tank,Farm>
     */
    public function tanks(): HasMany
    {
        return $this->hasMany(Tank::class);
    }

    public function dailyMonitorings(): HasMany
    {
        return $this->hasManyThrough(DailyMonitoring::class, Tank::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
