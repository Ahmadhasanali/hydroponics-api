<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    public function sendMessage(string|int $chatId, string $text, ?array $replyMarkup = null): array
    {
        $token = config('telegram.bot_token');

        if (empty($token)) {
            return ['ok' => false];
        }

        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $res = Http::post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

        return $res->json() ?? ['ok' => false];
    }

    public function editMessageText(string|int $chatId, int $messageId, string $text, ?array $replyMarkup = null): array
    {
        $token = config('telegram.bot_token');

        $payload = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'parse_mode' => 'HTML'];

        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return Http::post("https://api.telegram.org/bot{$token}/editMessageText", $payload)->json() ?? [];
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        $token = config('telegram.bot_token');

        Http::post("https://api.telegram.org/bot{$token}/answerCallbackQuery", ['callback_query_id' => $callbackQueryId, 'text' => $text]);
    }

    public function buildConfirmKeyboard(int $pendingId): array
    {
        return ['inline_keyboard' => [[['text' => 'Ya ✅', 'callback_data' => "confirm:{$pendingId}"], ['text' => 'Batal ❌', 'callback_data' => "cancel:{$pendingId}"]]]];
    }

    public function buildFarmKeyboard(array $farms, int $pendingId, ?int $defaultFarmId): array
    {
        $rows = [];

        usort($farms, fn ($a, $b) => ($a['id'] == $defaultFarmId ? -1 : 1));

        foreach ($farms as $f) {
            $label = $f['name'].($f['id'] == $defaultFarmId ? ' ⭐️' : '');
            $rows[] = [['text' => $label, 'callback_data' => "farm_pick:{$f['id']}:{$pendingId}"]];
        }

        return ['inline_keyboard' => $rows];
    }

    public function buildCategoryKeyboard(array $categories, int $pendingId): array
    {
        $rows = [];

        foreach ($categories as $c) {
            $rows[] = [['text' => "{$c['name']} ({$c['type']})", 'callback_data' => "category_pick:{$c['id']}:{$pendingId}"]];
        }

        return ['inline_keyboard' => $rows];
    }
}
