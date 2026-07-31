<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(private readonly ChatToolsService $chatTools)
    {
    }

    /**
     * @param  array<int, array{role: string, parts: array<int, array<string, mixed>>}>  $contents
     * @return array{text: ?string, function_calls: array<int, array{name: string, args: array<string, mixed>}>}
     *
     * @throws RuntimeException Ketika API key kosong atau API mengembalikan non-2xx
     */
    public function generate(array $contents): array
    {
        $apiKey = config('gemini.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('Gemini API key belum dikonfigurasi.');
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => config('gemini.system_prompt')]]],
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => config('gemini.max_output_tokens')],
        ];

        $declarations = $this->chatTools->declarations();

        if ($declarations !== []) {
            $payload['tools'] = [['functionDeclarations' => $declarations]];
        }

        $url = self::ENDPOINT.'/models/'.config('gemini.model').':generateContent';

        $response = Http::timeout(config('gemini.timeout'))
            ->retry(1, 100)
            ->post($url.'?key='.$apiKey, $payload);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->status().' '.$response->body());
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
