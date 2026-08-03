<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'subscribable_type',
        'subscribable_id',
        'fcm_token',
        'platform',
        'device_info',
    ];

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }
}
