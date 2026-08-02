<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_users_table_has_not_nullable_email_column_with_unique_index(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'email'));

        $columns = Schema::getColumns('users');
        $email = collect($columns)->firstWhere('name', 'email');
        $this->assertNotNull($email, 'email column not found');
        $this->assertFalse($email['nullable'], 'email column should not be nullable');

        $indexes = Schema::getIndexes('users');
        $this->assertContains('users_email_unique', array_column($indexes, 'name'));
    }

    public function test_user_factory_generates_unique_email(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertNotNull($first->email);
        $this->assertNotSame($first->email, $second->email);
    }
}
