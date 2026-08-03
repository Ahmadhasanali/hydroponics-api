<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use App\Models\Reminder\ReminderOccurrence;
use App\Models\Reminder\ReminderTarget;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'farm_id',
        'created_by_type',
        'created_by_id',
        'title',
        'body',
        'starts_at',
        'recurrence',
        'advance_notify_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'recurrence' => 'array',
            'advance_notify_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function creator(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'created_by_type', 'created_by_id');
    }

    /**
     * @return HasMany<ReminderTarget,Reminder>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(ReminderTarget::class);
    }

    /**
     * @return HasMany<ReminderOccurrence,Reminder>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(ReminderOccurrence::class);
    }

    public function isRecurring(): bool
    {
        return $this->recurrenceType() !== RecurrenceType::None;
    }

    public function recurrenceType(): RecurrenceType
    {
        return RecurrenceType::tryFrom($this->recurrence['type'] ?? RecurrenceType::None->value) ?? RecurrenceType::None;
    }
}
