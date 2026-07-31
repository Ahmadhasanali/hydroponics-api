# Chatbot AI Agrikultur (Agro Bot) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan floating chat widget "Agro Bot" berbasis Google Gemini API dengan Function Calling yang bisa diskusi agrikultur/selada dan membaca data farm milik pengguna.

**Architecture:** Frontend vanilla JS (Blade partial + `chat.js`) mengirim pesan ke `POST /api/chat`. `ChatController` menjalankan loop Gemini Function Calling: kirim pesan + history ke `GeminiService` (HTTP ke Gemini API), jika Gemini meminta tool maka `ChatToolsService` men-dispatch ke class tool yang terdaftar (auto-discovery dari `app/ChatTools/`), hasil query dikembalikan ke Gemini untuk dirangkai jadi jawaban final. Setiap tool memverifikasi kepemilikan farm via relasi `farm_users`.

**Tech Stack:** Laravel 13 (PHP 8.3+), Blade + Tailwind CSS v4 + vanilla JS, `Illuminate\Support\Facades\Http` (tanpa dependency baru), Google Gemini API `v1beta generateContent` dengan Function Calling.

**Spec:** `docs/superpowers/specs/2026-07-31-chatbot-ai-agrikultur-design.md`

## Global Constraints

- Semua perintah dijalankan lewat Sail (container `hydroponic-farm-management-system_laravel-laravel.test-1` sedang berjalan): prefix `vendor/bin/sail`.
- Jangan menambah dependency composer/npm baru.
- Test ditulis sebagai kelas PHPUnit (bukan Pest), di `tests/Feature/<Domain>/` atau `tests/Unit/<Domain>/`.
- Gunakan `LazilyRefreshDatabase` di feature test yang menyentuh DB.
- Setelah mengubah file PHP, jalankan `vendor/bin/sail bin pint --dirty --format agent`.
- Teks UI dalam Bahasa Indonesia.
- Jangan commit API key asli; hanya tambahkan variabel ke `.env.example`.
- Jangan hapus/mengubah model, migration, atau controller yang sudah ada kecuali diminta task.

---

## Struktur File

| File | Tanggung jawab |
|------|----------------|
| `config/gemini.php` | Konfigurasi API key, model, max tokens, timeout, system prompt |
| `app/ChatTools/ChatToolContract.php` | Kontrak tool: `name()`, `description()`, `parameters()`, `handle()` |
| `app/ChatTools/BaseTool.php` | Helper akses terotorisasi (farm/tank milik user) untuk semua tool |
| `app/ChatTools/FarmListTool.php` | Tool `get_farms` |
| `app/ChatTools/TankListTool.php` | Tool `get_tanks` |
| `app/ChatTools/TankStatusTool.php` | Tool `get_tank_status` |
| `app/ChatTools/MonitoringHistoryTool.php` | Tool `get_monitoring_history` |
| `app/ChatTools/NutrientHistoryTool.php` | Tool `get_nutrient_history` |
| `app/ChatTools/PhDownHistoryTool.php` | Tool `get_ph_down_history` |
| `app/Services/ChatToolsService.php` | Registry: auto-discovery tool, bangun `functionDeclarations`, dispatch `handle()` |
| `app/Services/GeminiService.php` | HTTP client Gemini: `generate(array $contents)` |
| `app/Http/Controllers/ChatController.php` | Endpoint `POST /api/chat`, loop tool-calling, error handling |
| `routes/chat.php` | Route chat (auth + throttle) |
| `resources/views/partials/chat-widget.blade.php` | Floating button + modal chat |
| `resources/js/chat.js` | Toggle modal, kirim pesan, render bubble, localStorage |
| `resources/views/layouts/app.blade.php` | Include widget partial + `@vite` chat.js |
| `routes/web.php` | Require `routes/chat.php` |
| `.env.example` | `GEMINI_API_KEY`, `GEMINI_MODEL` |

---

### Task 1: Kontrak Tool + Registry (`ChatToolContract`, `BaseTool`, `ChatToolsService`) + tool pertama `FarmListTool`

**Files:**
- Create: `app/ChatTools/ChatToolContract.php`
- Create: `app/ChatTools/BaseTool.php`
- Create: `app/ChatTools/FarmListTool.php`
- Create: `app/Services/ChatToolsService.php`
- Test: `tests/Unit/Services/ChatToolsServiceTest.php`
- Test: `tests/Unit/ChatTools/FarmListToolTest.php`

**Interfaces:**
- Produces: `ChatToolContract` (interface, dipakai semua tool task 3–4); `ChatToolsService` dengan `declarations(): array` dan `handle(string $name, array $args, User $user): array` (dipakai `GeminiService` task 2 dan `ChatController` task 5); `FarmListTool` (template tool + target unit test).

- [ ] **Step 1: Tulis test registry (failing)**

`tests/Unit/Services/ChatToolsServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\ChatToolsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatToolsServiceTest extends TestCase
{
    #[Test]
    public function declarations_contain_discovered_tool_definitions(): void
    {
        $declarations = app(ChatToolsService::class)->declarations();

        $this->assertNotEmpty($declarations);

        $farmTool = collect($declarations)->firstWhere('name', 'get_farms');
        $this->assertNotNull($farmTool, 'get_farms tidak ter-discovery');
        $this->assertArrayHasKey('description', $farmTool);
        $this->assertArrayHasKey('parameters', $farmTool);
    }

    #[Test]
    public function handle_returns_error_for_unknown_tool(): void
    {
        $result = app(ChatToolsService::class)->handle('tool_tidak_ada', [], \App\Models\User::factory()->make());

        $this->assertArrayHasKey('error', $result);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan FAIL**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Services/ChatToolsServiceTest.php`
Expected: FAIL — `Class "App\Services\ChatToolsService" not found`.

- [ ] **Step 3: Tulis test unit `FarmListTool` (failing)**

`tests/Unit/ChatTools/FarmListToolTest.php`:

```php
<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\FarmListTool;
use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FarmListToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function returns_only_farms_owned_by_the_user(): void
    {
        $user = User::factory()->create();
        $ownFarm = Farm::factory()->create(['created_by' => $user->id]);
        $ownFarm->users()->attach($user->id, ['role' => 'owner']);
        Tank::factory()->count(2)->create(['farm_id' => $ownFarm->id, 'created_by' => $user->id]);

        $otherUser = User::factory()->create();
        $foreignFarm = Farm::factory()->create(['created_by' => $otherUser->id]);
        $foreignFarm->users()->attach($otherUser->id, ['role' => 'owner']);

        $result = (new FarmListTool())->handle([], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertSame($ownFarm->id, $result['data'][0]['id']);
        $this->assertSame($ownFarm->name, $result['data'][0]['name']);
        $this->assertSame(2, $result['data'][0]['tank_count']);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan FAIL**

Run: `vendor/bin/sail artisan test --compact tests/Unit/ChatTools/FarmListToolTest.php`
Expected: FAIL — `Class "App\ChatTools\FarmListTool" not found`.

- [ ] **Step 5: Buat kontrak `ChatToolContract`**

`app/ChatTools/ChatToolContract.php`:

```php
<?php

namespace App\ChatTools;

use App\Models\User;

interface ChatToolContract
{
    public function name(): string;

    public function description(): string;

    /**
     * Skema parameter sesuai format Gemini function declaration.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Jalankan tool. Kembalikan ['data' => ...] saat sukses
     * atau ['error' => 'pesan'] saat gagal/tidak berhak.
     *
     * @param  array<string, mixed>  $args
     * @return array{data: mixed}|array{error: string}
     */
    public function handle(array $args, User $user): array;
}
```

- [ ] **Step 6: Buat `BaseTool`**

`app/ChatTools/BaseTool.php`:

```php
<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

abstract class BaseTool implements ChatToolContract
{
    /**
     * @return Collection<int, Farm> Farm yang user-nya terdaftar sebagai member
     */
    protected function accessibleFarms(User $user): Collection
    {
        return $user->farms()->withCount('tanks')->get();
    }

    /**
     * Cari tank yang farm-nya berisi user tersebut; null jika tidak berhak.
     */
    protected function accessibleTank(int $tankId, User $user): ?Tank
    {
        return Tank::whereKey($tankId)
            ->whereHas('farm', fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $query) => $query->whereKey($user->id),
            ))
            ->first();
    }

    protected function tankPayload(Tank $tank): array
    {
        return [
            'id' => $tank->id,
            'farm_id' => $tank->farm_id,
            'farm_name' => $tank->farm?->name,
            'name' => $tank->name,
            'capacity_liter' => $tank->capacity_liter,
            'is_active' => $tank->is_active,
            'target_ppm_min' => $tank->target_ppm_min,
            'target_ppm_max' => $tank->target_ppm_max,
            'target_ph_min' => $tank->target_ph_min,
            'target_ph_max' => $tank->target_ph_max,
            'current_ppm' => $tank->current_ppm,
            'current_ph' => $tank->current_ph,
            'current_water_temperature' => $tank->current_water_temperature,
            'last_condition_updated_at' => $tank->last_condition_updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 7: Buat `FarmListTool`**

`app/ChatTools/FarmListTool.php`:

```php
<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\User;

class FarmListTool extends BaseTool
{
    public function name(): string
    {
        return 'get_farms';
    }

    public function description(): string
    {
        return 'Mendapatkan daftar farm beserta jumlah tank milik pengguna yang sedang login.';
    }

    public function parameters(): array
    {
        return ['type' => 'OBJECT', 'properties' => [], 'required' => []];
    }

    public function handle(array $args, User $user): array
    {
        $farms = $this->accessibleFarms($user)->map(fn (Farm $farm): array => [
            'id' => $farm->id,
            'name' => $farm->name,
            'address' => $farm->address,
            'tank_count' => $farm->tanks_count,
        ])->all();

        return ['data' => $farms];
    }
}
```

- [ ] **Step 8: Buat `ChatToolsService` (registry auto-discovery)**

`app/Services/ChatToolsService.php`:

```php
<?php

namespace App\Services;

use App\ChatTools\ChatToolContract;
use App\Models\User;

class ChatToolsService
{
    /** @var array<string, ChatToolContract> */
    private array $tools = [];

    public function __construct()
    {
        foreach (glob(app_path('ChatTools/*.php')) ?: [] as $file) {
            $className = 'App\\ChatTools\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            if ($reflection->isAbstract() || ! $reflection->implementsInterface(ChatToolContract::class)) {
                continue;
            }

            $tool = app($className);
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Function declarations sesuai format Gemini API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function declarations(): array
    {
        return array_map(
            fn (ChatToolContract $tool): array => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
            array_values($this->tools),
        );
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{data: mixed}|array{error: string}
     */
    public function handle(string $name, array $args, User $user): array
    {
        if (! isset($this->tools[$name])) {
            return ['error' => "Tool '$name' tidak ditemukan."];
        }

        return $this->tools[$name]->handle($args, $user);
    }
}
```

- [ ] **Step 9: Jalankan kedua test, pastikan PASS**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Services/ChatToolsServiceTest.php tests/Unit/ChatTools/FarmListToolTest.php`
Expected: 3 tests PASS.

- [ ] **Step 10: Format & commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/ChatTools app/Services/ChatToolsService.php tests/Unit/Services/ChatToolsServiceTest.php tests/Unit/ChatTools/FarmListToolTest.php
git commit -m "feat: add chat tool contract, registry, and farm list tool"
```

---

### Task 2: Konfigurasi Gemini + `GeminiService` (dengan model failover)

**Files:**
- Create: `config/gemini.php`
- Modify: `.env.example`
- Create: `app/Services/GeminiService.php`
- Test: `tests/Unit/Services/GeminiServiceTest.php`

**Interfaces:**
- Consumes: `ChatToolsService::declarations()` (Task 1).
- Produces: `GeminiService::generate(array $contents): array` — signature `@param array<int, array{role: string, parts: array<int, array<string, mixed>>}> $contents`, `@return array{text: ?string, function_calls: array<int, array{name: string, args: array<string, mixed>}>}`, throws `RuntimeException` pada non-2xx / API key kosong (dipakai `ChatController` task 5).
- **Model failover:** `config('gemini.models')` adalah array berurutan (prioritas). `generate()` mencoba tiap model secara berurutan; pada status 429/500/502/503/504 lanjut ke model berikutnya. Status lain langsung throw. Semua model habis → throw `RuntimeException`.

- [ ] **Step 1: Tulis test (failing)**

`tests/Unit/Services/GeminiServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['gemini.api_key' => 'test-api-key']);
    }

    #[Test]
    public function generate_returns_text_from_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Halo, ada yang bisa dibantu?']]],
                ]],
            ], 200),
        ]);

        $result = app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'Halo']]],
        ]);

        $this->assertSame('Halo, ada yang bisa dibantu?', $result['text']);
        $this->assertSame([], $result['function_calls']);
    }

    #[Test]
    public function generate_parses_function_calls(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'functionCall' => ['name' => 'get_farms', 'args' => ['farm_id' => 1]],
                    ]]],
                ]],
            ], 200),
        ]);

        $result = app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'Farm saya apa saja?']]],
        ]);

        $this->assertNull($result['text']);
        $this->assertSame([['name' => 'get_farms', 'args' => ['farm_id' => 1]]], $result['function_calls']);
    }

    #[Test]
    public function generate_includes_tool_declarations_in_request(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'halo']]],
        ]);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return isset($body['tools'][0]['functionDeclarations'])
                && collect($body['tools'][0]['functionDeclarations'])->contains('name', 'get_farms');
        });
    }

    #[Test]
    public function generate_throws_when_api_key_missing(): void
    {
        config(['gemini.api_key' => '']);

        $this->expectException(RuntimeException::class);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'halo']]],
        ]);
    }

    #[Test]
    public function generate_throws_on_api_error(): void
    {
        config([
            'gemini.api_key' => 'test-api-key',
            'gemini.models' => ['gemini-3.6-flash'],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'halo']]],
        ]);
    }

    #[Test]
    public function generate_fails_over_to_next_model_on_rate_limit(): void
    {
        config([
            'gemini.api_key' => 'test-api-key',
            'gemini.models' => ['gemini-3.6-flash', 'gemini-3.5-flash'],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)
                ->push([
                    'candidates' => [['content' => ['parts' => [['text' => 'ok dari model kedua']]]]],
                ], 200)
                ->whenEmpty(Http::response([], 500)),
        ]);

        $result = app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'halo']]],
        ]);

        $this->assertSame('ok dari model kedua', $result['text']);

        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $this->assertStringContainsString('gemini-3.6-flash', $requests[0][0]->url());
        $this->assertStringContainsString('gemini-3.5-flash', $requests[1][0]->url());
    }

    #[Test]
    public function generate_throws_when_all_models_exhausted(): void
    {
        config([
            'gemini.api_key' => 'test-api-key',
            'gemini.models' => ['gemini-3.6-flash', 'gemini-3.5-flash'],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)
                ->push(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)
                ->whenEmpty(Http::response([], 500)),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'halo']]],
        ]);

        $this->assertCount(2, Http::recorded());
    }

    #[Test]
    public function generate_does_not_fail_over_on_client_error(): void
    {
        config([
            'gemini.api_key' => 'test-api-key',
            'gemini.models' => ['gemini-3.6-flash', 'gemini-3.5-flash'],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['status' => 'INVALID_ARGUMENT']], 400)
                ->whenEmpty(Http::response([], 500)),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiService::class)->generate([
            ['role' => 'user', 'parts' => [['text' => 'halo']]],
        ]);

        $this->assertCount(1, Http::recorded());
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan FAIL**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Services/GeminiServiceTest.php`
Expected: FAIL — `Class "App\Services\GeminiService" not found`.

- [ ] **Step 3: Buat `config/gemini.php`**

`config/gemini.php`:

```php
<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    'models' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GEMINI_MODELS', implode(',', [
            'gemini-3.6-flash',
            'gemini-3.5-flash',
            'gemini-3-flash',
            'gemini-2.5-flash',
            'gemini-2-flash',
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-2.5-flash-lite',
            'gemini-2-flash-lite',
            'gemini-3.1-pro',
            'gemini-2.5-pro',
        ])),
    ))),
    'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 1024),
    'timeout' => (int) env('GEMINI_TIMEOUT', 30),
    'system_prompt' => <<<'PROMPT'
Anda adalah Agro Bot, asisten AI untuk agrikultur dan hidroponik, khusus budidaya selada (hidroponik NFT). Tugas Anda membantu pengguna aplikasi Hydroponic Farm Management System.

Aturan:
1. Selalu jawab dalam Bahasa Indonesia dengan ramah dan jelas.
2. Pertanyaan umum tentang agrikultur/selada: jawab langsung dari pengetahuan Anda.
3. Pertanyaan tentang data farm pengguna (tank, PPM, pH, riwayat monitoring, nutrisi, pH Down): WAJIB panggil tool yang tersedia terlebih dahulu. JANGAN pernah menebak angka.
4. Jangan pernah menyebut angka yang tidak ada di hasil tool.
5. Jika tool mengembalikan error, sampaikan dengan jujur dan sopan kepada pengguna.
6. Jika pengguna bertanya di luar topik agrikultur, arahkan kembali dengan sopan.
PROMPT,
];
```

Catatan: `models` adalah daftar failover berurutan (model utama pertama). `model` tetap dipakai sebagai fallback tunggal jika `models` kosong di `GeminiService`.

- [ ] **Step 4: Tambah variabel ke `.env.example`**

Tambahkan di akhir file `.env.example`:

```
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.6-flash
GEMINI_MODELS=gemini-3.6-flash,gemini-3.5-flash,gemini-3-flash,gemini-2.5-flash,gemini-2-flash,gemini-3.5-flash-lite,gemini-3.1-flash-lite,gemini-2.5-flash-lite,gemini-2-flash-lite,gemini-3.1-pro,gemini-2.5-pro
```

- [ ] **Step 5: Buat `GeminiService` (dengan model failover)**

`app/Services/GeminiService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta';

    /** Status HTTP yang memicu failover ke model berikutnya (rate limit / overload). */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    public function __construct(private readonly ChatToolsService $chatTools)
    {
    }

    /**
     * @param  array<int, array{role: string, parts: array<int, array<string, mixed>>}>  $contents
     * @return array{text: ?string, function_calls: array<int, array{name: string, args: array<string, mixed>}>}
     *
     * @throws RuntimeException Ketika API key kosong atau semua model gagal
     */
    public function generate(array $contents): array
    {
        $apiKey = config('gemini.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('Gemini API key belum dikonfigurasi.');
        }

        $models = $this->models();

        if ($models === []) {
            throw new RuntimeException('Gemini model belum dikonfigurasi.');
        }

        $lastException = null;

        foreach ($models as $model) {
            try {
                return $this->requestModel($model, $contents, $apiKey);
            } catch (RateLimitedException $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new RuntimeException('Gemini API tidak tersedia.');
    }

    /**
     * Daftar model failover; fallback ke model tunggal jika kosong.
     *
     * @return array<int, string>
     */
    private function models(): array
    {
        $models = array_values(array_filter(config('gemini.models', []), fn ($model): bool => $model !== ''));

        return $models !== [] ? $models : array_filter([config('gemini.model')]);
    }

    /**
     * @param  array<int, array{role: string, parts: array<int, array<string, mixed>>}>  $contents
     * @return array{text: ?string, function_calls: array<int, array{name: string, args: array<string, mixed>}>}
     *
     * @throws RuntimeException Ketika API mengembalikan status non-retryable atau non-2xx
     */
    private function requestModel(string $model, array $contents, string $apiKey): array
    {
        $payload = [
            'system_instruction' => ['parts' => [['text' => config('gemini.system_prompt')]]],
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => config('gemini.max_output_tokens')],
        ];

        $declarations = $this->chatTools->declarations();

        if ($declarations !== []) {
            $payload['tools'] = [['functionDeclarations' => $declarations]];
        }

        $response = Http::timeout(config('gemini.timeout'))
            ->retry(1, 100)
            ->post(self::ENDPOINT.'/models/'.$model.':generateContent?key='.$apiKey, $payload);

        if ($response->failed()) {
            $message = 'Gemini API error: '.$response->status().' '.$response->body();

            if (in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                throw new RateLimitedException($message);
            }

            throw new RuntimeException($message);
        }

        $json = $response->json();
        $parts = $json['candidates'][0]['content']['parts'] ?? [];

        $text = null;
        $functionCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text = $part['text'];
            }

            if (isset($part['functionCall'])) {
                $functionCalls[] = [
                    'name' => $part['functionCall']['name'],
                    'args' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        return ['text' => $text, 'function_calls' => $functionCalls];
    }
}
```

Catatan:
- **Failover stateless:** tiap request mulai dari model utama lagi; tidak ada cooldown/cache.
- **429/500/502/503/504** → `RateLimitedException` → `generate()` mencoba model berikutnya.
- **Status lain (mis. 400)** → `RuntimeException` langsung, tidak failover (bug kode).
- `RateLimitedException` adalah class internal (lihat di bawah).

```php
<?php

namespace App\Services;

use RuntimeException;

final class RateLimitedException extends RuntimeException
{
}
```

- [ ] **Step 6: Jalankan test, pastikan PASS**

Run: `vendor/bin/sail artisan test --compact tests/Unit/Services/GeminiServiceTest.php`
Expected: 8 tests PASS.

- [ ] **Step 7: Format & commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add config/gemini.php .env.example app/Services/GeminiService.php tests/Unit/Services/GeminiServiceTest.php
git commit -m "feat: add Gemini API service with function calling and model failover"
```

---

### Task 3: Tool Daftar Tank & Status Tank

**Files:**
- Create: `app/ChatTools/TankListTool.php`
- Create: `app/ChatTools/TankStatusTool.php`
- Test: `tests/Unit/ChatTools/TankListToolTest.php`
- Test: `tests/Unit/ChatTools/TankStatusToolTest.php`

**Interfaces:**
- Consumes: `BaseTool` helpers `accessibleFarms()`, `accessibleTank()`, `tankPayload()` (Task 1).
- Produces: `TankListTool` (`get_tanks`, params `farm_id?`) dan `TankStatusTool` (`get_tank_status`, param wajib `tank_id`), terdeteksi otomatis oleh registry.

- [ ] **Step 1: Tulis test `TankListTool` (failing)**

`tests/Unit/ChatTools/TankListToolTest.php`:

```php
<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\TankListTool;
use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TankListToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ownerWithFarm(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $farm];
    }

    #[Test]
    public function lists_all_accessible_tanks(): void
    {
        [$user, $farm] = $this->ownerWithFarm();
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new TankListTool())->handle([], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertSame($tank->name, $result['data'][0]['name']);
        $this->assertSame($farm->id, $result['data'][0]['farm_id']);
    }

    #[Test]
    public function filters_by_farm_id(): void
    {
        [$user, $farm] = $this->ownerWithFarm();
        $farmB = Farm::factory()->create(['created_by' => $user->id]);
        $farmB->users()->attach($user->id, ['role' => 'owner']);

        Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);
        $tankB = Tank::factory()->create(['farm_id' => $farmB->id, 'created_by' => $user->id]);

        $result = (new TankListTool())->handle(['farm_id' => $farmB->id], $user);

        $this->assertCount(1, $result['data']);
        $this->assertSame($tankB->id, $result['data'][0]['id']);
    }

    #[Test]
    public function returns_error_for_foreign_farm(): void
    {
        [$user] = $this->ownerWithFarm();

        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);

        $result = (new TankListTool())->handle(['farm_id' => $otherFarm->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
```

- [ ] **Step 2: Tulis test `TankStatusTool` (failing)**

`tests/Unit/ChatTools/TankStatusToolTest.php`:

```php
<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\TankStatusTool;
use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TankStatusToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function returns_current_tank_status(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create([
            'farm_id' => $farm->id,
            'created_by' => $user->id,
            'current_ppm' => 850.5,
            'current_ph' => 6.2,
            'current_water_temperature' => 24.5,
        ]);

        $result = (new TankStatusTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertSame($tank->id, $result['data']['id']);
        $this->assertSame('850.50', (string) $result['data']['current_ppm']);
        $this->assertSame('6.20', (string) $result['data']['current_ph']);
    }

    #[Test]
    public function returns_error_for_tank_outside_users_farms(): void
    {
        $user = User::factory()->create();

        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new TankStatusTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
```

Catatan: `current_ppm` dicast `decimal:2` sehingga Eloquent mengembalikan string berformat `number_format(..., 2)` (misal `850.50`); bandingkan dengan `(string)`.

- [ ] **Step 3: Jalankan kedua test, pastikan FAIL**

Run: `vendor/bin/sail artisan test --compact tests/Unit/ChatTools/TankListToolTest.php tests/Unit/ChatTools/TankStatusToolTest.php`
Expected: FAIL — class tidak ditemukan.

- [ ] **Step 4: Buat `TankListTool`**

`app/ChatTools/TankListTool.php`:

```php
<?php

namespace App\ChatTools;

use App\Models\Farm\Tank;
use App\Models\User;

class TankListTool extends BaseTool
{
    public function name(): string
    {
        return 'get_tanks';
    }

    public function description(): string
    {
        return 'Mendapatkan daftar tank milik pengguna, opsional difilter berdasarkan farm_id.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'farm_id' => ['type' => 'INTEGER', 'description' => 'ID farm untuk memfilter tank.'],
            ],
            'required' => [],
        ];
    }

    public function handle(array $args, User $user): array
    {
        $farms = $this->accessibleFarms($user);

        if (isset($args['farm_id'])) {
            if (! $farms->contains('id', (int) $args['farm_id'])) {
                return ['error' => 'Farm tidak ditemukan atau Anda tidak memiliki akses.'];
            }

            $farms = $farms->where('id', (int) $args['farm_id']);
        }

        $tanks = Tank::query()
            ->with('farm:id,name')
            ->whereIn('farm_id', $farms->pluck('id'))
            ->orderBy('id')
            ->get();

        return ['data' => $tanks->map(fn (Tank $tank): array => $this->tankPayload($tank))->all()];
    }
}
```

- [ ] **Step 5: Buat `TankStatusTool`**

`app/ChatTools/TankStatusTool.php`:

```php
<?php

namespace App\ChatTools;

use App\Models\User;

class TankStatusTool extends BaseTool
{
    public function name(): string
    {
        return 'get_tank_status';
    }

    public function description(): string
    {
        return 'Mendapatkan kondisi terkini satu tank: PPM, pH, suhu air, dan rentang target.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'tank_id' => ['type' => 'INTEGER', 'description' => 'ID tank yang ingin dicek kondisinya.'],
            ],
            'required' => ['tank_id'],
        ];
    }

    public function handle(array $args, User $user): array
    {
        $tank = $this->accessibleTank((int) ($args['tank_id'] ?? 0), $user);

        if ($tank === null) {
            return ['error' => 'Tank tidak ditemukan atau Anda tidak memiliki akses.'];
        }

        $tank->load('farm:id,name');

        return ['data' => $this->tankPayload($tank)];
    }
}
```

- [ ] **Step 6: Jalankan kedua test, pastikan PASS**

Run: `vendor/bin/sail artisan test --compact tests/Unit/ChatTools/TankListToolTest.php tests/Unit/ChatTools/TankStatusToolTest.php`
Expected: 5 tests PASS.

- [ ] **Step 7: Format & commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/ChatTools/TankListTool.php app/ChatTools/TankStatusTool.php tests/Unit/ChatTools/
git commit -m "feat: add tank list and tank status chat tools"
```

---

### Task 4: Tool Riwayat Monitoring, Nutrisi & pH Down

**Files:**
- Create: `app/ChatTools/MonitoringHistoryTool.php`
- Create: `app/ChatTools/NutrientHistoryTool.php`
- Create: `app/ChatTools/PhDownHistoryTool.php`
- Test: `tests/Unit/ChatTools/MonitoringHistoryToolTest.php`
- Test: `tests/Unit/ChatTools/NutrientHistoryToolTest.php`
- Test: `tests/Unit/ChatTools/PhDownHistoryToolTest.php`

**Interfaces:**
- Consumes: `BaseTool::accessibleTank()` (Task 1).
- Produces: tiga tool dengan parameter `tank_id` (wajib) + `days` (opsional, default 7, clamp 1–90), masing-masing mengembalikan `['data' => [...]]` atau `['error' => string]`.

- [ ] **Step 1: Tulis test `MonitoringHistoryTool` (failing)**

`tests/Unit/ChatTools/MonitoringHistoryToolTest.php`:

```php
<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\MonitoringHistoryTool;
use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonitoringHistoryToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ownedTank(): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);

        return [$user, $tank];
    }

    #[Test]
    public function returns_monitoring_records_within_days(): void
    {
        [$user, $tank] = $this->ownedTank();
        DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->subDays(2)->toDateString(),
            'ppm' => 900,
            'ph' => 6.0,
        ]);
        DailyMonitoring::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->subDays(30)->toDateString(),
            'ppm' => 700,
            'ph' => 6.4,
        ]);

        $result = (new MonitoringHistoryTool())->handle(['tank_id' => $tank->id, 'days' => 7], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertArrayHasKey('log_date', $result['data'][0]);
        $this->assertArrayHasKey('ppm', $result['data'][0]);
        $this->assertArrayHasKey('ph', $result['data'][0]);
    }

    #[Test]
    public function returns_error_for_foreign_tank(): void
    {
        $user = User::factory()->create();

        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new MonitoringHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
```

- [ ] **Step 2: Tulis test `NutrientHistoryTool` & `PhDownHistoryTool` (failing)**

`tests/Unit/ChatTools/NutrientHistoryToolTest.php`:

```php
<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\NutrientHistoryTool;
use App\Models\Farm;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NutrientHistoryToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function returns_nutrient_addition_records(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);
        NutrientAddition::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->subDays(1)->toDateString(),
            'ppm_before' => 500,
            'ppm_after' => 900,
            'nutrient_a_ml' => 100,
            'nutrient_b_ml' => 100,
        ]);

        $result = (new NutrientHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertArrayHasKey('ppm_before', $result['data'][0]);
        $this->assertArrayHasKey('ppm_after', $result['data'][0]);
        $this->assertArrayHasKey('nutrient_a_ml', $result['data'][0]);
        $this->assertArrayHasKey('nutrient_b_ml', $result['data'][0]);
    }

    #[Test]
    public function returns_error_for_foreign_tank(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new NutrientHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
```

`tests/Unit/ChatTools/PhDownHistoryToolTest.php`:

```php
<?php

namespace Tests\Unit\ChatTools;

use App\ChatTools\PhDownHistoryTool;
use App\Models\Farm;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhDownHistoryToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function returns_ph_down_records(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $farm->id, 'created_by' => $user->id]);
        PhDownLog::factory()->create([
            'tank_id' => $tank->id,
            'user_id' => $user->id,
            'log_date' => now()->subDays(1)->toDateString(),
            'ph_before' => 6.8,
            'ph_after' => 6.2,
            'ph_down_ml' => 50,
        ]);

        $result = (new PhDownHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']);
        $this->assertArrayHasKey('ph_before', $result['data'][0]);
        $this->assertArrayHasKey('ph_after', $result['data'][0]);
        $this->assertArrayHasKey('ph_down_ml', $result['data'][0]);
    }

    #[Test]
    public function returns_error_for_foreign_tank(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherFarm = Farm::factory()->create(['created_by' => $other->id]);
        $otherFarm->users()->attach($other->id, ['role' => 'owner']);
        $tank = Tank::factory()->create(['farm_id' => $otherFarm->id, 'created_by' => $other->id]);

        $result = (new PhDownHistoryTool())->handle(['tank_id' => $tank->id], $user);

        $this->assertArrayHasKey('error', $result);
    }
}
```

- [ ] **Step 3: Jalankan ketiga test, pastikan FAIL**

Run: `vendor/bin/sail artisan test --compact tests/Unit/ChatTools/MonitoringHistoryToolTest.php tests/Unit/ChatTools/NutrientHistoryToolTest.php tests/Unit/ChatTools/PhDownHistoryToolTest.php`
Expected: FAIL — class tidak ditemukan.

- [ ] **Step 4: Buat `MonitoringHistoryTool`**

`app/ChatTools/MonitoringHistoryTool.php`:

```php
<?php

namespace App\ChatTools;

use App\Models\Farm\DailyMonitoring;
use App\Models\User;

class MonitoringHistoryTool extends BaseTool
{
    public function name(): string
    {
        return 'get_monitoring_history';
    }

    public function description(): string
    {
        return 'Mendapatkan riwayat monitoring harian (PPM, pH, suhu) satu tank dalam jumlah hari terakhir.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'tank_id' => ['type' => 'INTEGER', 'description' => 'ID tank.'],
                'days' => ['type' => 'INTEGER', 'description' => 'Jumlah hari ke belakang (1-90, default 7).'],
            ],
            'required' => ['tank_id'],
        ];
    }

    public function handle(array $args, User $user): array
    {
        $tank = $this->accessibleTank((int) ($args['tank_id'] ?? 0), $user);

        if ($tank === null) {
            return ['error' => 'Tank tidak ditemukan atau Anda tidak memiliki akses.'];
        }

        $days = max(1, min(90, (int) ($args['days'] ?? 7)));

        $records = $tank->dailyMonitorings()
            ->where('log_date', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('log_date')
            ->limit(50)
            ->get()
            ->map(fn (DailyMonitoring $monitoring): array => [
                'log_date' => $monitoring->log_date->toDateString(),
                'ppm' => $monitoring->ppm,
                'ph' => $monitoring->ph,
                'water_temperature' => $monitoring->water_temperature,
                'notes' => $monitoring->notes,
            ])
            ->all();

        return ['data' => $records];
    }
}
```

- [ ] **Step 5: Buat `NutrientHistoryTool`**

`app/ChatTools/NutrientHistoryTool.php`:

```php
<?php

namespace App\ChatTools;

use App\Models\Farm\NutrientAddition;
use App\Models\User;

class NutrientHistoryTool extends BaseTool
{
    public function name(): string
    {
        return 'get_nutrient_history';
    }

    public function description(): string
    {
        return 'Mendapatkan riwayat penambahan nutrisi AB Mix (PPM sebelum/sesudah, ml A/B) satu tank dalam jumlah hari terakhir.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'tank_id' => ['type' => 'INTEGER', 'description' => 'ID tank.'],
                'days' => ['type' => 'INTEGER', 'description' => 'Jumlah hari ke belakang (1-90, default 7).'],
            ],
            'required' => ['tank_id'],
        ];
    }

    public function handle(array $args, User $user): array
    {
        $tank = $this->accessibleTank((int) ($args['tank_id'] ?? 0), $user);

        if ($tank === null) {
            return ['error' => 'Tank tidak ditemukan atau Anda tidak memiliki akses.'];
        }

        $days = max(1, min(90, (int) ($args['days'] ?? 7)));

        $records = $tank->nutrientAdditions()
            ->where('log_date', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('log_date')
            ->limit(50)
            ->get()
            ->map(fn (NutrientAddition $addition): array => [
                'log_date' => $addition->log_date->toDateString(),
                'ppm_before' => $addition->ppm_before,
                'ppm_after' => $addition->ppm_after,
                'nutrient_a_ml' => $addition->nutrient_a_ml,
                'nutrient_b_ml' => $addition->nutrient_b_ml,
                'notes' => $addition->notes,
            ])
            ->all();

        return ['data' => $records];
    }
}
```

- [ ] **Step 6: Buat `PhDownHistoryTool`**

`app/ChatTools/PhDownHistoryTool.php`:

```php
<?php

namespace App\ChatTools;

use App\Models\Farm\PhDownLog;
use App\Models\User;

class PhDownHistoryTool extends BaseTool
{
    public function name(): string
    {
        return 'get_ph_down_history';
    }

    public function description(): string
    {
        return 'Mendapatkan riwayat penggunaan pH Down (pH sebelum/sesudah, ml) satu tank dalam jumlah hari terakhir.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'tank_id' => ['type' => 'INTEGER', 'description' => 'ID tank.'],
                'days' => ['type' => 'INTEGER', 'description' => 'Jumlah hari ke belakang (1-90, default 7).'],
            ],
            'required' => ['tank_id'],
        ];
    }

    public function handle(array $args, User $user): array
    {
        $tank = $this->accessibleTank((int) ($args['tank_id'] ?? 0), $user);

        if ($tank === null) {
            return ['error' => 'Tank tidak ditemukan atau Anda tidak memiliki akses.'];
        }

        $days = max(1, min(90, (int) ($args['days'] ?? 7)));

        $records = $tank->phDownLogs()
            ->where('log_date', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('log_date')
            ->limit(50)
            ->get()
            ->map(fn (PhDownLog $log): array => [
                'log_date' => $log->log_date->toDateString(),
                'ph_before' => $log->ph_before,
                'ph_after' => $log->ph_after,
                'ph_down_ml' => $log->ph_down_ml,
                'notes' => $log->notes,
            ])
            ->all();

        return ['data' => $records];
    }
}
```

- [ ] **Step 7: Jalankan ketiga test, pastikan PASS**

Run: `vendor/bin/sail artisan test --compact tests/Unit/ChatTools/MonitoringHistoryToolTest.php tests/Unit/ChatTools/NutrientHistoryToolTest.php tests/Unit/ChatTools/PhDownHistoryToolTest.php`
Expected: 6 tests PASS.

- [ ] **Step 8: Format & commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/ChatTools/ tests/Unit/ChatTools/
git commit -m "feat: add monitoring, nutrient, and ph down history chat tools"
```

---

### Task 5: `ChatController` + Route + Rate Limit

**Files:**
- Create: `app/Http/Controllers/ChatController.php`
- Create: `routes/chat.php`
- Modify: `routes/web.php` (tambah require)
- Test: `tests/Feature/Chat/ChatTest.php`

**Interfaces:**
- Consumes: `GeminiService::generate()` (Task 2), `ChatToolsService::handle()` (Task 1).
- Produces: route `chat.send` (`POST /api/chat`, middleware `auth` + `throttle:10,1`), response `{reply: string}` (200), `{reply: string}` dengan status 503 saat error Gemini.

- [ ] **Step 1: Tulis feature test (failing)**

`tests/Feature/Chat/ChatTest.php`:

```php
<?php

namespace Tests\Feature\Chat;

use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gemini.api_key' => 'test-api-key']);
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->postJson('/api/chat', ['message' => 'halo'])->assertUnauthorized();
    }

    #[Test]
    public function returns_reply_for_plain_text_answer(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Selada hidroponik membutuhkan PPM 560-840.']]]]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'Berapa PPM ideal selada?',
            'history' => [],
        ]);

        $response->assertOk()->assertJson([
            'reply' => 'Selada hidroponik membutuhkan PPM 560-840.',
        ]);
    }

    #[Test]
    public function executes_function_calling_loop_and_returns_final_reply(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['created_by' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        $tank = Tank::factory()->create([
            'farm_id' => $farm->id,
            'created_by' => $user->id,
            'name' => 'Tank Selada A',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'functionCall' => ['name' => 'get_farms', 'args' => []],
                        ]]],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [['content' => ['parts' => [['text' => 'Farm Anda bernama '.$farm->name.'.']]]]],
                ], 200)
                ->whenEmpty(Http::response([], 500)),
        ]);

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'Farm saya apa saja?',
            'history' => [],
        ]);

        $response->assertOk()->assertJson(['reply' => 'Farm Anda bernama '.$farm->name.'.']);
        $this->assertCount(2, Http::recorded());

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $last = $body['contents'][count($body['contents']) - 1];
            $parts = $last['parts'] ?? [];

            return isset($parts[0]['functionResponse'])
                && $parts[0]['functionResponse']['name'] === 'get_farms'
                && isset($parts[0]['functionResponse']['response']['data'][0]['name']);
        });
    }

    #[Test]
    public function returns_503_with_friendly_message_when_gemini_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'message' => 'halo',
            'history' => [],
        ]);

        $response->assertStatus(503)->assertJson([
            'reply' => 'Maaf, layanan AI sedang sibuk. Silakan coba lagi sebentar.',
        ]);
    }

    #[Test]
    public function rate_limits_after_ten_messages_per_minute(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/api/chat', ['message' => 'halo', 'history' => []])
                ->assertOk();
        }

        $this->actingAs($user)->postJson('/api/chat', ['message' => 'halo', 'history' => []])
            ->assertStatus(429);
    }
}
```

Catatan: test `executes_function_calling_loop_and_returns_final_reply` di atas sengaja memastikan loop berjalan 2x dan request kedua memuat `functionResponse` dengan data farm asli dari DB. Jika assert `Http::assertSent` terasa rapuh, cukup pertahankan `assertOk` + `assertJson` + `Http::recorded()` count 2.

- [ ] **Step 2: Jalankan test, pastikan FAIL**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Chat/ChatTest.php`
Expected: FAIL — route 404 / class tidak ditemukan.

- [ ] **Step 3: Buat `ChatController`**

`app/Http/Controllers/ChatController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\ChatToolsService;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatController extends Controller
{
    private const MAX_TOOL_ROUNDS = 4;

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly ChatToolsService $chatTools,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'present|array|max:20',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:2000',
        ]);

        $contents = $this->buildContents($validated['history'], $validated['message']);

        try {
            for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
                $response = $this->gemini->generate($contents);

                if ($response['function_calls'] === []) {
                    return response()->json([
                        'reply' => $response['text'] ?? 'Maaf, saya tidak dapat menjawab saat ini.',
                    ]);
                }

                $contents[] = [
                    'role' => 'model',
                    'parts' => array_map(
                        fn (array $call): array => [
                            'functionCall' => ['name' => $call['name'], 'args' => $call['args']],
                        ],
                        $response['function_calls'],
                    ),
                ];

                foreach ($response['function_calls'] as $call) {
                    $result = $this->chatTools->handle($call['name'], $call['args'], $request->user());
                    $contents[] = [
                        'role' => 'user',
                        'parts' => [['functionResponse' => ['name' => $call['name'], 'response' => $result]]],
                    ];
                }
            }

            return response()->json(['reply' => 'Maaf, saya kesulitan menjawab pertanyaan Anda. Silakan coba lagi.']);
        } catch (Throwable $e) {
            Log::error('Chat gagal: '.$e->getMessage());

            return response()->json(['reply' => 'Maaf, layanan AI sedang sibuk. Silakan coba lagi sebentar.'], 503);
        }
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, parts: array<int, array<string, mixed>>}>
     */
    private function buildContents(array $history, string $message): array
    {
        $contents = [];

        foreach ($history as $item) {
            $contents[] = [
                'role' => $item['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $item['content']]],
            ];
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        return $contents;
    }
}
```

- [ ] **Step 4: Buat route & daftarkan**

`routes/chat.php`:

```php
<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:10,1'])->group(function () {
    Route::post('/api/chat', ChatController::class)->name('chat.send');
});
```

`routes/web.php` — tambahkan baris `require __DIR__.'/chat.php';` di akhir file (setelah require `reports.php`).

- [ ] **Step 5: Jalankan test, pastikan PASS**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Chat/ChatTest.php`
Expected: 5 tests PASS.

- [ ] **Step 6: Format & commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/ChatController.php routes/chat.php routes/web.php tests/Feature/Chat/
git commit -m "feat: add chat endpoint with Gemini function calling loop and rate limit"
```

---

### Task 6: Frontend Chat Widget

**Files:**
- Create: `resources/views/partials/chat-widget.blade.php`
- Create: `resources/js/chat.js`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/Frontend/ChatWidgetTest.php`

**Interfaces:**
- Consumes: route `chat.send` (Task 5), CSRF token dari `@csrf`/`csrf_token()`.
- Produces: widget `#agroBot` (data attrs `data-user-id`, `data-chat-url`, `data-csrf`), fungsi global `AgroBot` tidak diperlukan — semua logic di module `chat.js`.

- [ ] **Step 1: Tulis feature test (failing)**

`tests/Feature/Frontend/ChatWidgetTest.php`:

```php
<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chat_widget_renders_on_authenticated_pages(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Agro Bot');
        $response->assertSee('agroBotToggle');
        $response->assertSee(route('chat.send'));
    }

    #[Test]
    public function chat_widget_is_absent_on_login_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Agro Bot');
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan FAIL**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Frontend/ChatWidgetTest.php`
Expected: FAIL — `Agro Bot` tidak muncul.

- [ ] **Step 3: Buat partial widget**

`resources/views/partials/chat-widget.blade.php`:

```blade
<div id="agroBot" data-user-id="{{ auth()->id() }}"
     data-chat-url="{{ route('chat.send') }}"
     data-csrf="{{ csrf_token() }}">

    {{-- Floating button --}}
    <button id="agroBotToggle" type="button" aria-label="Buka chat Agro Bot"
        class="fixed bottom-6 right-6 z-50 inline-flex h-14 w-14 items-center justify-center rounded-full bg-[#ffce54] text-[#1a1c1e] shadow-lg shadow-[#ffce54]/30 transition hover:bg-[#f0b830]">
        <i class="bi bi-chat-dots text-2xl"></i>
    </button>

    {{-- Chat panel --}}
    <div id="agroBotPanel"
        class="fixed bottom-24 right-6 z-50 hidden w-[380px] max-w-[calc(100vw-3rem)] flex-col overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-2xl">

        {{-- Header --}}
        <div class="flex items-center gap-3 bg-[#1a1c1e] px-5 py-4 text-white">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e]">
                <i class="bi bi-flower1"></i>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold">Agro Bot</p>
                <p class="text-xs text-slate-400">Asisten Agrikultur &amp; Hidroponik</p>
            </div>
            <button id="agroBotClear" type="button" title="Bersihkan chat"
                class="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white">
                <i class="bi bi-trash3 text-sm"></i>
            </button>
            <button id="agroBotClose" type="button" title="Tutup"
                class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white/10 hover:text-white">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- Messages --}}
        <div id="agroBotMessages" class="flex h-80 flex-col gap-3 overflow-y-auto px-4 py-4"></div>

        {{-- Input --}}
        <form id="agroBotForm" class="border-t border-slate-100 p-3">
            <div class="flex items-end gap-2">
                <textarea id="agroBotInput" rows="1" maxlength="2000" placeholder="Tanya tentang selada atau data farm..."
                    class="flex-1 resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-[#ffce54] focus:outline-none focus:ring-2 focus:ring-[#ffce54]/20"></textarea>
                <button id="agroBotSend" type="submit"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#ffce54] text-[#1a1c1e] shadow-sm transition hover:bg-[#f0b830] disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="bi bi-send-fill text-sm"></i>
                </button>
            </div>
        </form>
    </div>
</div>
```

- [ ] **Step 4: Buat `chat.js`**

`resources/js/chat.js`:

```js
const initChatWidget = () => {
    const root = document.getElementById('agroBot');
    if (!root) return;

    const toggleBtn = document.getElementById('agroBotToggle');
    const closeBtn = document.getElementById('agroBotClose');
    const clearBtn = document.getElementById('agroBotClear');
    const panel = document.getElementById('agroBotPanel');
    const messages = document.getElementById('agroBotMessages');
    const form = document.getElementById('agroBotForm');
    const input = document.getElementById('agroBotInput');
    const sendBtn = document.getElementById('agroBotSend');

    const userId = root.dataset.userId;
    const chatUrl = root.dataset.chatUrl;
    const csrf = root.dataset.csrf;
    const STORAGE_KEY = `agrobot_chats_${userId}`;

    const loadMessages = () => {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            saved.forEach(({ role, content }) => appendBubble(role, content, false));
        } catch {
            localStorage.removeItem(STORAGE_KEY);
        }
    };

    const saveMessages = () => {
        const history = [];
        messages.querySelectorAll('.agro-bubble').forEach((el) => {
            history.push({ role: el.dataset.role, content: el.dataset.content });
        });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(-20)));
    };

    const appendBubble = (role, content, persist = true) => {
        const wrap = document.createElement('div');
        wrap.className = 'agro-bubble flex ' + (role === 'user' ? 'justify-end' : 'justify-start');
        wrap.dataset.role = role;
        wrap.dataset.content = content;

        const bubble = document.createElement('div');
        bubble.className =
            role === 'user'
                ? 'max-w-[80%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-[#ffce54] px-4 py-2.5 text-sm font-medium text-[#1a1c1e]'
                : 'max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-bl-md border border-slate-200/80 bg-white px-4 py-2.5 text-sm text-slate-700';
        bubble.textContent = content;
        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;

        if (persist) saveMessages();
    };

    const showTyping = () => {
        const wrap = document.createElement('div');
        wrap.id = 'agroTyping';
        wrap.className = 'flex justify-start';
        wrap.innerHTML =
            '<div class="flex items-center gap-1 rounded-2xl rounded-bl-md border border-slate-200/80 bg-white px-4 py-3">' +
            '<span class="h-2 w-2 animate-pulse rounded-full bg-slate-400"></span>' +
            '<span class="h-2 w-2 animate-pulse rounded-full bg-slate-400" style="animation-delay:150ms"></span>' +
            '<span class="h-2 w-2 animate-pulse rounded-full bg-slate-400" style="animation-delay:300ms"></span></div>';
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
    };

    const hideTyping = () => document.getElementById('agroTyping')?.remove();

    const history = () => {
        const items = [];
        messages.querySelectorAll('.agro-bubble').forEach((el) => {
            items.push({ role: el.dataset.role, content: el.dataset.content });
        });
        return items.slice(-20);
    };

    const send = async () => {
        const message = input.value.trim();
        if (!message || sendBtn.disabled) return;

        input.value = '';
        input.style.height = 'auto';
        appendBubble('user', message);
        showTyping();
        sendBtn.disabled = true;

        try {
            const res = await fetch(chatUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ message, history: history() }),
            });

            const data = await res.json();
            hideTyping();
            appendBubble('assistant', data.reply || 'Maaf, terjadi kesalahan. Silakan coba lagi.');
        } catch {
            hideTyping();
            appendBubble('assistant', 'Maaf, terjadi kesalahan koneksi. Silakan coba lagi.');
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    };

    toggleBtn.addEventListener('click', () => {
        panel.classList.toggle('hidden');
        panel.classList.toggle('flex');
        if (!panel.classList.contains('hidden') && messages.children.length === 0) {
            appendBubble(
                'assistant',
                'Halo! Saya Agro Bot. Tanyakan apa saja tentang budidaya selada hidroponik, atau data farm Anda seperti PPM, pH, dan riwayat nutrisi.'
            );
        }
        input.focus();
    });

    closeBtn.addEventListener('click', () => {
        panel.classList.add('hidden');
        panel.classList.remove('flex');
    });

    clearBtn.addEventListener('click', () => {
        messages.innerHTML = '';
        localStorage.removeItem(STORAGE_KEY);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        send();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });

    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });
};

document.addEventListener('DOMContentLoaded', initChatWidget);
```

- [ ] **Step 5: Wire ke layout**

`resources/views/layouts/app.blade.php`:
1. Ubah baris `@vite(['resources/css/app.css', 'resources/js/app.js'])` menjadi:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/chat.js'])
```

2. Di `<body>`, setelah `@yield('content')` tambahkan:

```blade
@yield('content')
@include('partials.chat-widget')
```

- [ ] **Step 6: Jalankan test, pastikan PASS**

Run: `vendor/bin/sail artisan test --compact tests/Feature/Frontend/ChatWidgetTest.php`
Expected: 2 tests PASS.

- [ ] **Step 7: Verifikasi visual (manual)**

Jalankan `vendor/bin/sail npm run build`, buka dashboard via browser (login dulu), klik tombol chat di kanan bawah, kirim pesan. Karena `GEMINI_API_KEY` belum diisi, seharusnya muncul pesan fallback "Maaf, layanan AI sedang sibuk..." (503) — ini membuktikan frontend berfungsi.

- [ ] **Step 8: Format & commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add resources/views/partials/chat-widget.blade.php resources/js/chat.js resources/views/layouts/app.blade.php tests/Feature/Frontend/ChatWidgetTest.php
git commit -m "feat: add Agro Bot floating chat widget UI"
```

---

### Task 7: Verifikasi Akhir

- [ ] **Step 1: Jalankan seluruh test suite**

Run: `vendor/bin/sail artisan test --compact`
Expected: seluruh test PASS (existing + baru).

- [ ] **Step 2: Pint full check**

Run: `vendor/bin/sail bin pint --dirty --format agent`
Expected: tidak ada perubahan format tersisa (atau otomatis diformat).

- [ ] **Step 3: Update dokumentasi**

Tambah bagian "Chatbot Agro Bot" di README.md:

```markdown
## Chatbot AI (Agro Bot)

Floating chat widget (login-only) berbasis Google Gemini API untuk diskusi agrikultur/selada dan membaca data farm pengguna via Function Calling.

Konfigurasi (`.env`):
- `GEMINI_API_KEY` — API key dari https://aistudio.google.com/apikey
- `GEMINI_MODEL` — default `gemini-1.5-flash`

Menambah tool baru: buat class di `app/ChatTools/` yang mengimplementasikan `ChatToolContract` — otomatis terdeteksi.
```

- [ ] **Step 4: Commit akhir**

```bash
git add README.md
git commit -m "docs: document Agro Bot chatbot configuration"
```
