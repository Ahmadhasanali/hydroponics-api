<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';

    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    public function __construct(private readonly ChatToolsService $chatTools) {}

    /**
     * @param  array<int, array{role: string, content: ?string, tool_calls?: array<int, mixed>}>  $messages
     * @param  array<int, string>|null  $allowedTools  Filter tools for channel gating (e.g. telegram)
     * @return array{text: ?string, function_calls: array<int, array{id: string, name: string, args: array<string, mixed>, signature: ?string}>}
     *
     * @throws RuntimeException Ketika API key kosong atau API mengembalikan non-2xx
     */
    public function generate(array $messages, ?array $allowedTools = null): array
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
                return $this->requestModel($model, $messages, $apiKey, $allowedTools);
            } catch (RateLimitedException $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new RuntimeException('Gemini API tidak tersedia.');
    }

    private function models(): array
    {
        $models = array_values(array_filter(config('gemini.models', []), fn ($model): bool => $model !== ''));

        return $models !== [] ? $models : array_filter([config('gemini.model')]);
    }

    private function requestModel(string $model, array $messages, string $apiKey, ?array $allowedTools = null): array
    {
        $payload = [
            'model' => $model,
            'messages' => [['role' => 'system', 'content' => config('gemini.system_prompt')], ...$messages],
            'max_tokens' => config('gemini.max_output_tokens'),
        ];

        $declarations = $allowedTools !== null
            ? $this->chatTools->declarationsFiltered($allowedTools)
            : $this->chatTools->declarations();

        if ($declarations !== []) {
            $payload['tools'] = array_map(
                fn (array $declaration): array => ['type' => 'function', 'function' => $declaration],
                $declarations,
            );
        }

        try {
            $response = Http::timeout(config('gemini.timeout'))
                ->retry(1, 100)
                ->withToken($apiKey)
                ->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            // Timeout/koneksi gagal: anggap retryable agar failover ke model berikutnya berjalan.
            throw new RateLimitedException('Gemini API connection error: '.$e->getMessage());
        }

        if ($response->failed()) {
            $message = 'Gemini API error: '.$response->status().' '.$response->body();

            if (in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                throw new RateLimitedException($message);
            }

            throw new RuntimeException($message);
        }

        $message = $response->json('choices.0.message', []);

        $text = is_string($message['content'] ?? null) ? $message['content'] : null;

        $functionCalls = [];

        foreach ($message['tool_calls'] ?? [] as $call) {
            $functionCalls[] = [
                'id' => $call['id'] ?? '',
                'name' => $call['function']['name'] ?? '',
                'args' => json_decode($call['function']['arguments'] ?? '{}', true) ?: [],
                'signature' => $call['extra_content']['google']['thought_signature'] ?? null,
            ];
        }

        return ['text' => $text, 'function_calls' => $functionCalls];
    }
}
