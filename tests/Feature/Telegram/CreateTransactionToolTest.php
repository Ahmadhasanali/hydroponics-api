<?php

namespace Tests\Feature\Telegram;

use App\ChatTools\CreateTransactionTool;
use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CreateTransactionToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    private FinancialCategory $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create();
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->cat = FinancialCategory::factory()->forFarm($this->farm->id)->expense()->create(['name' => 'Nutrisi AB Mix']);
    }

    public function test_requires_farm_when_multiple(): void
    {
        $f2 = Farm::factory()->create();
        $this->owner->farms()->attach($f2->id, ['role' => 'manager']);

        $res = (new CreateTransactionTool)->handle(['type' => 'expense', 'category_id' => $this->cat->id, 'amount' => 300000], $this->owner);

        $this->assertSame('FARM_REQUIRED', $res['error']);
    }

    public function test_category_needed_when_missing(): void
    {
        $res = (new CreateTransactionTool)->handle(['type' => 'expense', 'amount' => 300000], $this->owner);

        $this->assertSame('CATEGORY_NEEDED', $res['error']);
    }

    public function test_type_mismatch(): void
    {
        $res = (new CreateTransactionTool)->handle(['type' => 'income', 'category_id' => $this->cat->id, 'amount' => 1000], $this->owner);

        $this->assertSame('TYPE_MISMATCH', $res['error']);
    }

    public function test_future_date_rejected(): void
    {
        $res = (new CreateTransactionTool)->handle(['type' => 'expense', 'category_id' => $this->cat->id, 'amount' => 1000, 'transaction_date' => now()->addDay()->toDateString()], $this->owner);

        $this->assertSame('DATE_FUTURE', $res['error']);
    }

    public function test_happy_path(): void
    {
        $res = (new CreateTransactionTool)->handle(['type' => 'expense', 'category_id' => $this->cat->id, 'amount' => 300000], $this->owner);

        $this->assertArrayHasKey('data', $res);
        $this->assertSame(300000.0, $res['data']['amount']);
    }
}
