<?php

namespace App\Enums;

enum ReminderStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Skipped = 'skipped';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
