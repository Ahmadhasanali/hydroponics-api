<?php

namespace App\Jobs;

use App\ChatTools\CreateSaleTool;
use App\ChatTools\CreateTransactionTool;
use App\ChatTools\GetFinancialSummaryTool;
use App\ChatTools\ListCustomersTool;
use App\ChatTools\ListProductsTool;
use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\Farm\FinancialTransaction;
use App\Models\MessagingAccount;
use App\Models\MessagingLinkCode;
use App\Models\TelegramPendingSale;
use App\Models\TelegramPendingTransaction;
use App\Services\AccountBalanceService;
use App\Services\GeminiService;
use App\Services\SalesService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public array $update) {}

    public function handle(TelegramService $telegram, GeminiService $gemini): void
    {
        $callback = $this->update['callback_query'] ?? null;

        if ($callback) {
            $this->handleCallbackQuery($callback, $telegram);

            return;
        }

        $message = $this->update['message'] ?? null;

        if (! $message || ! isset($message['chat']['id'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text'] ?? '');

        if (preg_match('/^[A-Z0-9]{6}$/', $text)) {
            $this->handleLinkCode($chatId, $text, $telegram);

            return;
        }

        $account = MessagingAccount::where('channel', 'telegram')->where('external_id', $chatId)->first();

        if (! $account) {
            $telegram->sendMessage($chatId, 'Hubungkan dulu di HydroFarm → Pengaturan → Telegram.');

            return;
        }

        try {
            $result = $gemini->generate(
                [['role' => 'user', 'content' => $text]],
                ['create_financial_transaction', 'get_financial_summary', 'create_sale', 'list_customers', 'list_products'],
            );
        } catch (\Throwable $e) {
            $telegram->sendMessage($chatId, 'Maaf, AI sibuk. Coba kirim ulang dalam 1 menit.');

            return;
        }

        $calls = $result['function_calls'] ?? [];
        $args = null;

        // Prioritas: penjualan (create_sale) lebih spesifik daripada transaksi finance.
        foreach ($calls as $c) {
            if (($c['name'] ?? null) === 'create_sale') {
                $args = $c['args'];
                break;
            }
        }

        if ($args) {
            $this->handleCreateSale($telegram, $account, $chatId, $args);

            return;
        }

        // List pelanggan/produk untuk kebutuhan lihat data (tanpa create_sale).
        foreach ($calls as $c) {
            $name = $c['name'] ?? null;
            if ($name === 'list_customers' || $name === 'list_products') {
                $this->handleListForTelegram($telegram, $chatId, $name, $c['args'] ?? [], $account->user);

                return;
            }
        }

        foreach ($calls as $c) {
            if (($c['name'] ?? null) === 'create_financial_transaction') {
                $args = $c['args'];
                break;
            }
        }

        // Jika Gemini mengembalikan create_financial_transaction, proses sebagai pencatatan.
        if ($args) {
            $this->handleCreateTransaction($telegram, $account, $chatId, $args);

            return;
        }

        // Jika Gemini mengembalikan get_financial_summary, handle langsung.
        foreach ($calls as $c) {
            if (($c['name'] ?? null) === 'get_financial_summary') {
                $tool = app(GetFinancialSummaryTool::class);
                $res = $tool->handle($c['args'] ?? [], $account->user);
                if (isset($res['error'])) {
                    $telegram->sendMessage($chatId, $res['error']);

                    return;
                }
                $data = $res['data'];
                $text = is_array($data) && isset($data['farm_name']) ? "Ringkasan {$data['farm_name']}: pemasukan Rp ".number_format($data['income'], 0, ',', '.').', pengeluaran Rp '.number_format($data['expense'], 0, ',', '.').', laba Rp '.number_format($data['net'], 0, ',', '.') : json_encode($data, JSON_UNESCAPED_UNICODE);
                $telegram->sendMessage($chatId, $text);

                return;
            }
        }

        $telegram->sendMessage($chatId, 'Kirim contoh: "beli pupuk abmix 300 ribu", "jual 3kg selada ke Warung Sari 75 ribu", atau "rekap pemasukan bulan ini".');

        return;
    }

    private function handleCreateTransaction(TelegramService $telegram, MessagingAccount $account, string $chatId, array $args): void
    {
        $user = $account->user;
        $tool = app(CreateTransactionTool::class);
        $res = $tool->handle($args, $user);

        if (isset($res['error'])) {
            if ($res['error'] === 'FARM_REQUIRED') {
                $pending = TelegramPendingTransaction::create([
                    'messaging_account_id' => $account->id,
                    'chat_id' => $chatId,
                    'type' => $args['type'] ?? 'expense',
                    'category_id' => $args['category_id'] ?? null,
                    'amount' => $args['amount'] ?? 0,
                    'transaction_date' => $args['transaction_date'] ?? now()->toDateString(),
                    'note' => $args['note'] ?? null,
                    'status' => 'awaiting_farm',
                    'expires_at' => now()->addMinutes(5),
                ]);

                $farms = $res['farms'] ?? $user->farms()->get()->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all();
                $telegram->sendMessage($chatId, 'Pilih farm:', $telegram->buildFarmKeyboard($farms, $pending->id, $account->default_farm_id));

                return;
            }

            if (in_array($res['error'], ['CATEGORY_NEEDED', 'CATEGORY_INVALID', 'TYPE_MISMATCH'], true)) {
                $farmId = $args['farm_id'] ?? $user->farms()->first()?->id;
                $type = $args['type'] ?? null;

                $cats = [];

                if ($farmId !== null) {
                    $query = FinancialCategory::forFarm($farmId)->where('is_active', true);

                    if ($res['error'] === 'TYPE_MISMATCH' && $type !== null) {
                        $query->where('type', $type);
                    }

                    $cats = $query->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type])->all();
                }

                $pending = TelegramPendingTransaction::create([
                    'messaging_account_id' => $account->id,
                    'chat_id' => $chatId,
                    'farm_id' => $farmId,
                    'type' => $args['type'],
                    'amount' => $args['amount'],
                    'transaction_date' => $args['transaction_date'] ?? now()->toDateString(),
                    'note' => $args['note'] ?? null,
                    'status' => 'awaiting_category',
                    'expires_at' => now()->addMinutes(5),
                ]);

                $telegram->sendMessage($chatId, $res['error'] === 'CATEGORY_NEEDED' ? 'Kategori apa? Pilih salah satu:' : 'Kategori tidak cocok. Pilih kategori:', $telegram->buildCategoryKeyboard($cats, $pending->id));

                return;
            }

            $telegram->sendMessage($chatId, $res['message'] ?? 'Data tidak valid. Coba lagi.');

            return;
        }

        $data = $res['data'];

        $pending = TelegramPendingTransaction::create([
            'messaging_account_id' => $account->id,
            'chat_id' => $chatId,
            'farm_id' => $data['farm_id'],
            'type' => $data['type'],
            'category_id' => $data['category_id'],
            'amount' => $data['amount'],
            'transaction_date' => $data['transaction_date'],
            'note' => $data['note'],
            'status' => 'awaiting_confirm',
            'expires_at' => now()->addMinutes(5),
        ]);

        $catName = FinancialCategory::find($data['category_id'])?->name ?? '?';
        $farmName = Farm::find($data['farm_id'])?->name ?? '?';
        $text = 'Tercatat: '.ucfirst($data['type']).' – '.$catName.' – Rp '.number_format($data['amount'], 0, ',', '.').' (Farm '.$farmName.', '.$data['transaction_date'].'). Konfirmasi?';
        $telegram->sendMessage($chatId, $text, $telegram->buildConfirmKeyboard($pending->id));
    }

    private function handleCreateSale(TelegramService $telegram, MessagingAccount $account, string $chatId, array $args): void
    {
        $user = $account->user;
        $tool = app(CreateSaleTool::class);
        $res = $tool->handle($args, $user);

        if (isset($res['error'])) {
            if ($res['error'] === 'FARM_REQUIRED') {
                $pending = TelegramPendingSale::create([
                    'messaging_account_id' => $account->id,
                    'chat_id' => $chatId,
                    'sale_date' => now()->toDateString(),
                    // Simpan argumen mentah; dipakai ulang setelah user memilih farm.
                    'items' => $args,
                    'status' => 'awaiting_farm',
                    'expires_at' => now()->addMinutes(5),
                ]);

                $farms = $res['farms'] ?? $user->farms()->get()->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all();
                $telegram->sendMessage($chatId, 'Pilih farm:', $telegram->buildFarmKeyboard($farms, $pending->id, $account->default_farm_id, 'sale_farm_pick'));

                return;
            }

            $telegram->sendMessage($chatId, $res['message'] ?? 'Data penjualan tidak valid. Coba lagi.');

            return;
        }

        $data = $res['data'];

        $pending = TelegramPendingSale::create([
            'messaging_account_id' => $account->id,
            'chat_id' => $chatId,
            'farm_id' => $data['farm_id'],
            'customer_id' => $data['customer']['id'],
            'customer_name' => $data['customer']['name'],
            'customer_phone' => $data['customer']['phone'],
            'sale_date' => $data['sale_date'],
            'due_date' => $data['due_date'],
            'items' => $data['items'],
            'note' => $data['note'],
            'status' => 'awaiting_confirm',
            'expires_at' => now()->addMinutes(5),
        ]);

        $telegram->sendMessage($chatId, $this->formatSaleSummary($data), $telegram->buildSaleConfirmKeyboard($pending->id));
    }

    private function handleListForTelegram(TelegramService $telegram, string $chatId, string $toolName, array $args, $user): void
    {
        $tool = $toolName === 'list_customers' ? app(ListCustomersTool::class) : app(ListProductsTool::class);
        $res = $tool->handle($args, $user);

        if (isset($res['error'])) {
            $telegram->sendMessage($chatId, $res['error']);

            return;
        }

        $items = $res['data'] ?? [];

        if ($items === []) {
            $telegram->sendMessage($chatId, $toolName === 'list_customers' ? 'Belum ada pelanggan terdaftar.' : 'Belum ada produk terdaftar.');

            return;
        }

        if ($toolName === 'list_customers') {
            $lines = array_map(fn ($c) => "• {$c['name']}".(! empty($c['phone']) ? " ({$c['phone']})" : ''), $items);
            $telegram->sendMessage($chatId, "Daftar pelanggan:\n".implode("\n", $lines));
        } else {
            $lines = array_map(fn ($p) => "• {$p['name']} ({$p['unit']}) — Rp ".number_format($p['default_price'], 0, ',', '.'), $items);
            $telegram->sendMessage($chatId, "Daftar produk:\n".implode("\n", $lines));
        }
    }

    private function formatSaleSummary(array $data): string
    {
        $customer = $data['customer']['name'];
        $lines = ["Penjualan ke: {$customer}"];

        foreach ($data['items'] as $item) {
            $lines[] = '• '.$item['product_name'].' — '.rtrim(rtrim(number_format($item['qty'], 2, ',', '.'), '0'), ',').' '.$item['unit'].' × Rp '.number_format($item['price'], 0, ',', '.');
        }

        $lines[] = 'Total: Rp '.number_format($data['total'], 0, ',', '.');

        if ($data['due_date']) {
            $lines[] = 'Kredit, tempo '.$data['due_date'];
        } else {
            $lines[] = 'Lunas (Cash)';
        }

        $lines[] = 'Simpan penjualan?';

        return implode("\n", $lines);
    }

    /**
     * Konversi pending sale (yang sudah dikonfirmasi user) menjadi Sale sungguhan.
     */
    private function persistSale(TelegramPendingSale $pending): \App\Models\Farm\Sale
    {
        $account = $pending->account;
        $user = $account->user;
        $farm = Farm::findOrFail($pending->farm_id);

        $customer = $pending->customer_id !== null
            ? \App\Models\Farm\Customer::findOrFail($pending->customer_id)
            : \App\Models\Farm\Customer::create([
                'farm_id' => $farm->id,
                'name' => $pending->customer_name,
                'phone' => $pending->customer_phone,
                'is_active' => true,
            ]);

        $items = collect($pending->items)->map(function (array $item): array {
            return [
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $item['product_name'],
                'unit' => $item['unit'],
                'qty' => $item['qty'],
                'price' => $item['price'],
            ];
        })->all();

        $payload = [
            'customer_id' => $customer->id,
            'sale_date' => $pending->sale_date->toDateString(),
            'due_date' => $pending->due_date?->toDateString(),
            'note' => $pending->note,
            'items' => $items,
        ];

        // Lunas → catat pembayaran penuh ke akun Cash default. Kredit → tanpa pembayaran.
        if ($pending->due_date === null) {
            $accountService = app(AccountBalanceService::class);
            $cash = $accountService->ensureDefaultAccount($farm->id);
            $payload['account_id'] = $cash->id;
            $payload['amount_paid'] = (float) collect($items)->sum(fn (array $item): float => $item['qty'] * $item['price']);
        }

        return app(SalesService::class)->createSale($user, $farm, $payload);
    }

    private function handleLinkCode(string $chatId, string $code, TelegramService $telegram): void
    {
        $row = MessagingLinkCode::where('code', $code)->where('expires_at', '>', now())->whereNull('used_at')->first();

        if (! $row) {
            $telegram->sendMessage($chatId, 'Kode tidak valid atau sudah kadaluarsa. Generate ulang di HydroFarm → Pengaturan → Telegram.');

            return;
        }

        try {
            DB::transaction(function () use ($chatId, $row): void {
                if (MessagingAccount::where('channel', 'telegram')->where('external_id', $chatId)->lockForUpdate()->exists()) {
                    throw new \RuntimeException('external_linked');
                }

                if (MessagingAccount::where('user_id', $row->user_id)->lockForUpdate()->exists()) {
                    throw new \RuntimeException('user_linked');
                }

                MessagingAccount::create(['channel' => 'telegram', 'external_id' => $chatId, 'user_id' => $row->user_id, 'linked_at' => now()]);
                $row->update(['used_at' => now()]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'external_linked') {
                $telegram->sendMessage($chatId, 'Akun Telegram ini sudah tertaut ke akun lain. Putus dulu di Pengaturan → Telegram.');

                return;
            }

            if ($e->getMessage() === 'user_linked') {
                $telegram->sendMessage($chatId, 'Akun HydroFarm ini sudah tertaut ke Telegram lain.');

                return;
            }

            throw $e;
        } catch (QueryException $e) {
            Log::warning('Telegram link race unique violation', ['chat_id' => $chatId, 'code' => $code, 'error' => $e->getMessage()]);
            $telegram->sendMessage($chatId, 'Akun sudah tertaut (race). Coba lagi atau cek Pengaturan → Telegram.');

            return;
        }

        $telegram->sendMessage($chatId, '✅ Berhasil terhubung! Kirim "beli pupuk abmix 300 ribu" untuk coba.');
    }

    private function handleCallbackQuery(array $cq, TelegramService $telegram): void
    {
        $data = $cq['data'] ?? '';
        $cqId = $cq['id'] ?? '';
        $chatId = (string) ($cq['message']['chat']['id'] ?? $cq['from']['id'] ?? '');
        $msgId = $cq['message']['message_id'] ?? null;

        if (str_starts_with($data, 'sale_confirm:')) {
            $id = (int) substr($data, 13);
            $p = TelegramPendingSale::with('account.user')->find($id);

            if (! $p || $p->expires_at->isPast()) {
                $telegram->answerCallbackQuery($cqId, 'Waktu habis');

                return;
            }

            if ($p->chat_id !== $chatId && ($p->account?->external_id ?? null) !== $chatId) {
                $telegram->answerCallbackQuery($cqId, 'Tidak berhak');

                return;
            }

            if ($p->status !== 'awaiting_confirm' || empty($p->farm_id)) {
                $telegram->answerCallbackQuery($cqId, 'Data belum lengkap');

                return;
            }

            try {
                $sale = $this->persistSale($p);
            } catch (\Throwable $e) {
                Log::warning('Telegram sale persist failed', ['pending_id' => $p->id, 'error' => $e->getMessage()]);
                $telegram->answerCallbackQuery($cqId, 'Gagal menyimpan');

                return;
            }

            $telegram->answerCallbackQuery($cqId, '✅ Penjualan disimpan');

            if ($msgId !== null) {
                $customer = $sale->customer?->name ?? 'Pelanggan';
                $total = 'Rp '.number_format((float) $sale->total_amount, 0, ',', '.');
                $telegram->editMessageText($chatId, $msgId, "✅ Penjualan ke {$customer} sebesar {$total} disimpan.");
            }

            $p->delete();

            return;
        }

        if (str_starts_with($data, 'sale_cancel:')) {
            $id = (int) substr($data, 12);
            $p = TelegramPendingSale::with('account')->find($id);

            if ($p && $p->chat_id !== $chatId && ($p->account?->external_id ?? null) !== $chatId) {
                $telegram->answerCallbackQuery($cqId, 'Tidak berhak');

                return;
            }

            $p?->delete();
            $telegram->answerCallbackQuery($cqId, '❌ Dibatalkan');

            if ($msgId !== null) {
                $telegram->editMessageText($chatId, $msgId, '❌ Penjualan dibatalkan.');
            }

            return;
        }

        if (str_starts_with($data, 'confirm:')) {
            $id = (int) substr($data, 8);
            $p = TelegramPendingTransaction::with('account')->find($id);

            if (! $p || $p->expires_at->isPast()) {
                $telegram->answerCallbackQuery($cqId, 'Waktu habis');

                return;
            }

            if ($p->chat_id !== $chatId && ($p->account?->external_id ?? null) !== $chatId) {
                $telegram->answerCallbackQuery($cqId, 'Tidak berhak');

                return;
            }

            if ($p->status !== 'awaiting_confirm' || empty($p->farm_id) || empty($p->category_id)) {
                $telegram->answerCallbackQuery($cqId, 'Data belum lengkap');

                return;
            }

            FinancialTransaction::create([
                'farm_id' => $p->farm_id,
                'category_id' => $p->category_id,
                'type' => $p->type,
                'amount' => $p->amount,
                'transaction_date' => $p->transaction_date,
                'note' => $p->note,
                'source' => 'telegram',
                'status' => 'approved',
                'user_id' => $p->account->user_id,
            ]);

            $telegram->answerCallbackQuery($cqId, '✅ Disimpan');

            if ($msgId !== null) {
                $telegram->editMessageText($chatId, $msgId, '✅ Transaksi disimpan.');
            }

            $p->delete();

            return;
        }

        if (str_starts_with($data, 'cancel:')) {
            $id = (int) substr($data, 7);
            $p = TelegramPendingTransaction::with('account')->find($id);

            if ($p && $p->chat_id !== $chatId && ($p->account?->external_id ?? null) !== $chatId) {
                $telegram->answerCallbackQuery($cqId, 'Tidak berhak');

                return;
            }

            $p?->delete();
            $telegram->answerCallbackQuery($cqId, '❌ Dibatalkan');

            if ($msgId !== null) {
                $telegram->editMessageText($chatId, $msgId, '❌ Dibatalkan.');
            }

            return;
        }

        if (str_starts_with($data, 'sale_farm_pick:')) {
            $parts = explode(':', $data);
            $farmId = (int) ($parts[1] ?? 0);
            $pid = (int) ($parts[2] ?? 0);
            $p = TelegramPendingSale::with('account.user')->find($pid);

            if (! $p) {
                $telegram->answerCallbackQuery($cqId, 'Waktu habis');

                return;
            }

            if ($p->chat_id !== $chatId && ($p->account?->external_id ?? null) !== $chatId) {
                $telegram->answerCallbackQuery($cqId, 'Tidak berhak');

                return;
            }

            if ($p->expires_at->isPast()) {
                $telegram->answerCallbackQuery($cqId, 'Waktu habis');

                return;
            }

            $user = $p->account->user;

            if (! $user || ! $user->farms()->whereKey($farmId)->exists()) {
                $telegram->answerCallbackQuery($cqId, 'Farm tidak valid');

                return;
            }

            $rawArgs = is_array($p->items) ? $p->items : [];
            $rawArgs['farm_id'] = $farmId;

            $telegram->answerCallbackQuery($cqId, 'Farm dipilih');

            $p->delete();

            $this->handleCreateSale($telegram, $p->account, $chatId, $rawArgs);

            return;
        }

        if (str_starts_with($data, 'farm_pick:')) {
            $parts = explode(':', $data);
            $farmId = (int) ($parts[1] ?? 0);
            $pid = (int) ($parts[2] ?? 0);
            $p = TelegramPendingTransaction::with('account.user')->find($pid);

            if (! $p) {
                $telegram->answerCallbackQuery($cqId, 'Waktu habis');

                return;
            }

            if ($p->chat_id !== $chatId && ($p->account?->external_id ?? null) !== $chatId) {
                $telegram->answerCallbackQuery($cqId, 'Tidak berhak');

                return;
            }

            if ($p->expires_at->isPast()) {
                $telegram->answerCallbackQuery($cqId, 'Waktu habis');

                return;
            }

            $user = $p->account->user;

            if (! $user || ! $user->farms()->whereKey($farmId)->exists()) {
                $telegram->answerCallbackQuery($cqId, 'Farm tidak valid');

                return;
            }

            $p->update(['farm_id' => $farmId]);

            $fresh = $p->fresh();

            if ($fresh->farm_id && $fresh->category_id) {
                $fresh->update(['status' => 'awaiting_confirm']);
                $telegram->answerCallbackQuery($cqId, 'Farm dipilih');

                if ($msgId !== null) {
                    $telegram->editMessageText($chatId, $msgId, 'Farm dipilih. Konfirmasi?', (new TelegramService)->buildConfirmKeyboard($fresh->id));
                }
            } elseif (empty($fresh->category_id)) {
                $fresh->update(['status' => 'awaiting_category']);
                $cats = FinancialCategory::forFarm($farmId)->where('is_active', true)->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type])->all();
                $telegram->answerCallbackQuery($cqId, 'Farm dipilih, pilih kategori');

                if ($msgId !== null) {
                    $telegram->editMessageText($chatId, $msgId, 'Farm dipilih. Pilih kategori:', (new TelegramService)->buildCategoryKeyboard($cats, $fresh->id));
                }
            } else {
                $fresh->update(['status' => 'awaiting_confirm']);
                $telegram->answerCallbackQuery($cqId, 'Farm dipilih');

                if ($msgId !== null) {
                    $telegram->editMessageText($chatId, $msgId, 'Farm dipilih. Konfirmasi?', (new TelegramService)->buildConfirmKeyboard($fresh->id));
                }
            }

            return;
        }

        if (str_starts_with($data, 'category_pick:')) {
            $parts = explode(':', $data);
            $catId = (int) ($parts[1] ?? 0);
            $pid = (int) ($parts[2] ?? 0);
            $p = TelegramPendingTransaction::with('account.user')->find($pid);

            if (! $p) {
                $telegram->answerCallbackQuery($cqId, 'Waktu habis');

                return;
            }

            if ($p->chat_id !== $chatId && ($p->account?->external_id ?? null) !== $chatId) {
                $telegram->answerCallbackQuery($cqId, 'Tidak berhak');

                return;
            }

            if ($p->expires_at->isPast()) {
                $telegram->answerCallbackQuery($cqId, 'Waktu habis');

                return;
            }

            if (empty($p->farm_id)) {
                $telegram->answerCallbackQuery($cqId, 'Pilih farm dulu');

                return;
            }

            $category = FinancialCategory::forFarm($p->farm_id)->whereKey($catId)->where('is_active', true)->first();

            if (! $category) {
                $telegram->answerCallbackQuery($cqId, 'Kategori tidak valid');

                return;
            }

            if ($category->type !== $p->type) {
                $telegram->answerCallbackQuery($cqId, 'Tipe kategori tidak sesuai');

                return;
            }

            $p->update(['category_id' => $catId]);

            $fresh = $p->fresh();

            if ($fresh->farm_id && $fresh->category_id) {
                $fresh->update(['status' => 'awaiting_confirm']);
                $telegram->answerCallbackQuery($cqId, 'Kategori dipilih');

                if ($msgId !== null) {
                    $telegram->editMessageText($chatId, $msgId, 'Kategori dipilih. Konfirmasi?', (new TelegramService)->buildConfirmKeyboard($fresh->id));
                }
            } else {
                $fresh->update(['status' => 'awaiting_farm']);
                $telegram->answerCallbackQuery($cqId, 'Kategori dipilih');

                if ($msgId !== null) {
                    $telegram->editMessageText($chatId, $msgId, 'Kategori dipilih. Konfirmasi?', (new TelegramService)->buildConfirmKeyboard($fresh->id));
                }
            }

            return;
        }
    }
}
