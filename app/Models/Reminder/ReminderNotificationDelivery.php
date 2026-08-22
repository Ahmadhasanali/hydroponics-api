<?php

namespace App\Models\Reminder;

use App\Models\Reminder;
use Database\Factories\Reminder\ReminderNotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReminderNotificationDelivery extends Model
{
    /** @use HasFactory<ReminderNotificationDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'reminder_id',
        'occurrence_id',
        'notifiable_type',
        'notifiable_id',
        'kind',
        'sent_at',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ReminderOccurrence::class);
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
