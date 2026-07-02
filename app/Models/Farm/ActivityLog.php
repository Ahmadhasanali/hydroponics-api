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
        'action',
        'entity_type',
        'entity_id',
        'description',
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
}
