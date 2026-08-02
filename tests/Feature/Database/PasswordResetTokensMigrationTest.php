<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTokensMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_password_reset_tokens_table_uses_email_primary_key(): void
    {
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));

        $columns = Schema::getColumns('password_reset_tokens');
        $names = array_column($columns, 'name');

        $this->assertContains('email', $names);
        $this->assertContains('token', $names);
        $this->assertContains('created_at', $names);
        $this->assertNotContains('user_id', $names);
    }
}
