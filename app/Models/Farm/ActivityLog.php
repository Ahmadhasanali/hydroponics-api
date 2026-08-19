<?php

namespace App\Models\Farm;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'farm_id',
        'user_id',
        'staff_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'before_state',
        'after_state',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'before_state' => 'array',
        'after_state' => 'array',
    ];

    public $timestamps = false;

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
