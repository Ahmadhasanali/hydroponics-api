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
