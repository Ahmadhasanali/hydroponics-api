<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FarmHelperTestController extends Controller
{
    public function exposeHasFarm(Request $request): bool
    {
        return $this->hasFarm($request);
    }

    public function exposeSelectedFarm(Request $request): ?Farm
    {
        return $this->selectedFarm($request);
    }
}

class ControllerFarmHelpersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeRequest(User $user): Request
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    public function test_has_farm_returns_false_when_user_has_no_farms(): void
    {
        $user = User::factory()->create();
        $controller = new FarmHelperTestController;

        $this->assertFalse($controller->exposeHasFarm($this->makeRequest($user)));
    }

    public function test_has_farm_returns_true_when_user_has_farm(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);

        $this->assertTrue((new FarmHelperTestController)->exposeHasFarm($this->makeRequest($user)));
    }

    public function test_selected_farm_returns_null_when_no_farms(): void
    {
        $user = User::factory()->create();

        $this->assertNull((new FarmHelperTestController)->exposeSelectedFarm($this->makeRequest($user)));
    }

    public function test_selected_farm_uses_session_farm(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        app('session.store')->put('selected_farm_id', $farm->id);

        $this->assertSame($farm->id, (new FarmHelperTestController)->exposeSelectedFarm($this->makeRequest($user))->id);
    }

    public function test_selected_farm_falls_back_to_first_farm_when_session_stale(): void
    {
        $user = User::factory()->create();
        $farmA = Farm::factory()->create(['created_by' => $user->id]);
        $farmB = Farm::factory()->create(['created_by' => $user->id]);
        $farmA->users()->attach($user->id, ['role' => 'owner']);
        $farmB->users()->attach($user->id, ['role' => 'owner']);
        app('session.store')->put('selected_farm_id', 999999);

        $this->assertSame($farmA->id, (new FarmHelperTestController)->exposeSelectedFarm($this->makeRequest($user))->id);
    }
}
