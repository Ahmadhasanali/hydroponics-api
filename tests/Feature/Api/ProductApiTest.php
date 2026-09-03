<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Sales\ProductController;
use App\Models\Farm;
use App\Models\Farm\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductApiTest extends TestCase
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

        Route::prefix('api/v1')->middleware(SubstituteBindings::class)->group(function (): void {
            Route::apiResource('products', ProductController::class)->only(['index', 'store', 'update', 'destroy']);
        });
    }

    public function test_store_creates_product(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', [
                'farm_id' => $this->farm->id,
                'name' => 'Selada',
                'unit' => 'kg',
                'default_price' => 21000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.product.name', 'Selada');
    }

    public function test_store_rejects_invalid_unit(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/products', [
                'farm_id' => $this->farm->id,
                'name' => 'Selada',
                'unit' => 'liter',
                'default_price' => 21000,
            ])
            ->assertStatus(422);
    }

    public function test_destroy_deactivates(): void
    {
        $product = Product::factory()->create(['farm_id' => $this->farm->id]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertOk();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }
}
