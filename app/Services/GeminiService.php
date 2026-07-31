<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';

    public function __construct(private readonly ChatToolsService $chatTools) {}

    /**
     * @param  array<int, array{role: string, content: ?string, tool_calls?: array<int, mixed>}>  $messages
     * @return array{text: ?string, function_calls: array<int, array{id: string, name: string, args: array<string, mixed>, signature: ?string}>}
     *
     * @throws RuntimeException Ketika API key kosong atau API mengembalikan non-2xx
     */
    public function generate(array $messages): array
    {
        $apiKey = config('gemini.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('Gemini API key belum dikonfigurasi.');
        }

        $payload = [
            'model' => config('gemini.model'),
            'messages' => [['role' => 'system', 'content' => config('gemini.system_prompt')], ...$messages],
            'max_tokens' => config('gemini.max_output_tokens'),
        ];

        $declarations = $this->chatTools->declarations();

        if ($declarations !== []) {
            $payload['tools'] = array_map(
                fn (array $declaration): array => ['type' => 'function', 'function' => $declaration],
                $declarations,
            );
        }

        $response = Http::timeout(config('gemini.timeout'))
            ->retry(1, 100)
            ->withToken($apiKey)
            ->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->status().' '.$response->body());
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
