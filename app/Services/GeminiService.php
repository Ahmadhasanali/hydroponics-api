<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta';

    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    public function __construct(private readonly ChatToolsService $chatTools) {}

    /**
     * @param  array<int, array{role: string, parts: array<int, array<string, mixed>}>  $contents
     * @return array{text: ?string, function_calls: array<int, array{name: string, args: array<string, mixed>}>}
     *
     * @throws RuntimeException when API key missing or all models exhausted
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

    private function models(): array
    {
        $models = array_values(array_filter(config('gemini.models', []), fn ($m) => $m !== ''));

        return $models !== [] ? $models : array_filter([config('gemini.model')]);
    }

    private function requestModel(string $model, array $contents, string $apiKey): array
    {
        $payload = [
            'system_instruction' => ['parts' => [['text' => config('gemini.system_prompt')]]],
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => config('gemini.max_output_tokens')],
        ];

        $decl = $this->chatTools->declarations();
        if ($decl !== []) {
            $payload['tools'] = [['functionDeclarations' => $decl]];
        }

        $response = Http::timeout(config('gemini.timeout'))
            ->retry(1, 100)
            ->post(self::ENDPOINT.'/models/'.$model.':generateContent?key='.$apiKey, $payload);

        if ($response->failed()) {
            $msg = 'Gemini API error: '.$response->status().' '.$response->body();
            if (in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                throw new RateLimitedException($msg);
            }
            throw new RuntimeException($msg);
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
