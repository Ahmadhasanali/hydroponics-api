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
            'history.*.content' => 'required|string|max:8000',
        ]);

        $messages = $this->buildMessages($validated['history'], $validated['message']);

        try {
            for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
                $response = $this->gemini->generate($messages);

                if ($response['function_calls'] === []) {
                    return response()->json([
                        'reply' => $response['text'] ?? 'Maaf, saya tidak dapat menjawab saat ini.',
                    ]);
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => array_map(
                        fn (array $call): array => [
                            'id' => $call['id'],
                            'type' => 'function',
                            'function' => [
                                'name' => $call['name'],
                                'arguments' => json_encode((object) $call['args']),
                            ],
                            ...($call['signature'] ? [
                                'extra_content' => ['google' => ['thought_signature' => $call['signature']]],
                            ] : []),
                        ],
                        $response['function_calls'],
                    ),
                ];

                foreach ($response['function_calls'] as $call) {
                    $result = $this->chatTools->handle($call['name'], $call['args'], $request->user());
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'],
                        'content' => json_encode($result),
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
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(array $history, string $message): array
    {
        $messages = [];

        foreach ($history as $item) {
            $messages[] = ['role' => $item['role'], 'content' => $item['content']];
        }

        $firstUser = array_search('user', array_column($messages, 'role'), true);
        $messages = $firstUser !== false ? array_slice($messages, $firstUser) : [];

        $messages[] = ['role' => 'user', 'content' => $message];

        return array_values($messages);
    }
}
