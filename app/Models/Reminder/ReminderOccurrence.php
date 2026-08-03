<?php

namespace App\Models\Reminder;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use Database\Factories\Reminder\ReminderOccurrenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReminderOccurrence extends Model
{
    /** @use HasFactory<ReminderOccurrenceFactory> */
    use HasFactory;

    protected $fillable = [
        'reminder_id',
        'scheduled_at',
        'advance_notify_at',
        'advance_notified_at',
        'notified_at',
        'status',
        'completed_by_type',
        'completed_by_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'advance_notify_at' => 'datetime',
            'advance_notified_at' => 'datetime',
            'notified_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => ReminderStatus::class,
        ];
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    public function completer(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'completed_by_type', 'completed_by_id');
    }

    public function markDone(string $completerType, int $completerId): void
    {
        $this->update([
            'status' => ReminderStatus::Done,
            'completed_by_type' => $completerType,
            'completed_by_id' => $completerId,
            'completed_at' => now(),
        ]);
    }

    public function markSkipped(): void
    {
        $this->update([
            'status' => ReminderStatus::Skipped,
            'completed_by_type' => null,
            'completed_by_id' => null,
            'completed_at' => now(),
        ]);
    }
}
