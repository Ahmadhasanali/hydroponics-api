<?php

namespace App\Enums;

enum RecurrenceType: string
{
    case None = 'none';
    case Interval = 'interval';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
