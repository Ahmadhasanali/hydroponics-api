<?php

namespace Tests\Feature\Telegram;

use App\ChatTools\CreateSaleTool;
use App\Models\Farm;
use App\Models\Farm\Customer;
use App\Models\Farm\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CreateSaleToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $owner;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->farm = Farm::factory()->create();
        $this->farm->users()->attach($this->owner->id, ['role' => 'owner']);
    }

    public function test_happy_path_lunas_with_existing_customer_and_product(): void
    {
        $customer = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Warung Bu Siti']);
        $product = Product::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Selada', 'unit' => 'kg']);

        $res = (new CreateSaleTool)->handle([
            'farm_id' => $this->farm->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'qty' => 3, 'price' => 21000]],
        ], $this->owner);

        $this->assertArrayHasKey('data', $res);
        $this->assertSame($this->farm->id, $res['data']['farm_id']);
        $this->assertSame('Warung Bu Siti', $res['data']['customer']['name']);
        $this->assertSame(63000.0, $res['data']['total']);
        $this->assertNull($res['data']['due_date']);
    }

    public function test_new_customer_and_product_by_name(): void
    {
        $res = (new CreateSaleTool)->handle([
            'farm_id' => $this->farm->id,
            'customer_name' => 'Warung Sari',
            'customer_phone' => '0812345',
            'items' => [['product_name' => 'Selada', 'unit' => 'kg', 'qty' => 2, 'price' => 22000]],
        ], $this->owner);

        $this->assertArrayHasKey('data', $res);
        $this->assertNull($res['data']['customer']['id']);
        $this->assertSame('Warung Sari', $res['data']['customer']['name']);
        $this->assertSame('Selada', $res['data']['items'][0]['product_name']);
        $this->assertSame(44000.0, $res['data']['total']);
    }

    public function test_credit_with_due_date(): void
    {
        $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);

        $res = (new CreateSaleTool)->handle([
            'farm_id' => $this->farm->id,
            'customer_id' => $customer->id,
            'items' => [['product_name' => 'Selada', 'qty' => 1, 'price' => 21000]],
            'due_date' => now()->addDays(7)->toDateString(),
        ], $this->owner);

        $this->assertArrayHasKey('data', $res);
        $this->assertSame(now()->addDays(7)->toDateString(), $res['data']['due_date']);
    }

    public function test_requires_customer(): void
    {
        $res = (new CreateSaleTool)->handle([
            'farm_id' => $this->farm->id,
            'items' => [['product_name' => 'Selada', 'qty' => 1, 'price' => 21000]],
        ], $this->owner);

        $this->assertSame('CUSTOMER_NEEDED', $res['error']);
    }

    public function test_farm_required_when_multiple_farms(): void
    {
        $farm2 = Farm::factory()->create();
        $this->owner->farms()->attach($farm2->id, ['role' => 'manager']);

        $res = (new CreateSaleTool)->handle([
            'items' => [['product_name' => 'Selada', 'qty' => 1, 'price' => 21000]],
            'customer_name' => 'Warung Baru',
        ], $this->owner);

        $this->assertSame('FARM_REQUIRED', $res['error']);
        $this->assertCount(2, $res['farms']);
    }

    public function test_rejects_product_from_other_farm(): void
    {
        $otherFarm = Farm::factory()->create();
        $otherProduct = Product::factory()->create(['farm_id' => $otherFarm->id]);
        $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);

        $res = (new CreateSaleTool)->handle([
            'farm_id' => $this->farm->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $otherProduct->id, 'qty' => 1, 'price' => 1000]],
        ], $this->owner);

        $this->assertSame('PRODUCT_INVALID', $res['error']);
    }

    public function test_rejects_due_date_before_sale_date(): void
    {
        $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);

        $res = (new CreateSaleTool)->handle([
            'farm_id' => $this->farm->id,
            'customer_id' => $customer->id,
            'items' => [['product_name' => 'Selada', 'qty' => 1, 'price' => 1000]],
            'sale_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ], $this->owner);

        $this->assertSame('DATE_INVALID', $res['error']);
    }
}
