<?php

namespace Tests\Unit\Enums;

use App\Enums\RecurrenceType;
use App\Enums\ReminderStatus;
use PHPUnit\Framework\TestCase;

class ReminderStatusEnumTest extends TestCase
{
    public function test_reminder_status_has_expected_cases(): void
    {
        $this->assertSame(['pending', 'done', 'skipped'], ReminderStatus::values());
        $this->assertSame('pending', ReminderStatus::Pending->value);
        $this->assertSame('done', ReminderStatus::Done->value);
        $this->assertSame('skipped', ReminderStatus::Skipped->value);
    }

    public function test_recurrence_type_has_expected_cases(): void
    {
        $this->assertSame(['none', 'interval', 'weekly', 'monthly'], RecurrenceType::values());
    }
}
