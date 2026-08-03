<?php

namespace App\Models\Reminder;

use App\Models\Reminder;
use Database\Factories\Reminder\ReminderTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReminderTarget extends Model
{
    /** @use HasFactory<ReminderTargetFactory> */
    use HasFactory;

    protected $fillable = [
        'reminder_id',
        'targetable_type',
        'targetable_id',
    ];

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
