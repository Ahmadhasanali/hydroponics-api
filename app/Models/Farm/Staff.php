<?php

namespace App\Models\Farm;

use App\Models\Farm;
use App\Models\PushSubscription;
use Database\Factories\Farm\StaffFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model implements Authenticatable
{
    use AuthenticatableTrait, HasFactory, SoftDeletes;

    /** @use HasFactory<StaffFactory> */
    protected $fillable = [
        'farm_id',
        'name',
        'username',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function pushSubscriptions(): MorphMany
    {
        return $this->morphMany(PushSubscription::class, 'subscribable');
    }
}
