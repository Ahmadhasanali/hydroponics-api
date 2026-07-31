<?php

namespace App\Http\Controllers;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Services\ChatToolsService;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatController extends Controller
{
    private const MAX_TOOL_ROUNDS = 4;

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly ChatToolsService $chatTools,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'nullable|integer|exists:chat_sessions,id',
            'message' => 'required|string|max:2000',
        ]);

        [$session, $createdSession] = $this->resolveSession($request, $validated['session_id'] ?? null);
        $messages = $this->buildContext($session, $validated['message']);

        try {
            for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
                $response = $this->gemini->generate($messages);

                if ($response['function_calls'] === []) {
                    $reply = $response['text'] ?? 'Maaf, saya tidak dapat menjawab saat ini.';
                    $this->persistExchange($session, $validated['message'], $reply);

                    return response()->json([
                        'session_id' => $session->id,
                        'title' => $session->title,
                        'reply' => $reply,
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

            return response()->json([
                'session_id' => $session->id,
                'title' => $session->title,
                'reply' => 'Maaf, saya kesulitan menjawab pertanyaan Anda. Silakan coba lagi.',
            ]);
        } catch (Throwable $e) {
            Log::error('Chat gagal: '.$e->getMessage());

            if ($createdSession && $session->messages()->doesntExist()) {
                $session->forceDelete();
            }

            return response()->json(['reply' => 'Maaf, layanan AI sedang sibuk. Silakan coba lagi sebentar.'], 503);
        }
    }

    /**
     * @return array{ChatSession, bool} [session, whether created on this request]
     */
    private function resolveSession(Request $request, ?int $sessionId): array
    {
        if ($sessionId === null) {
            $session = ChatSession::create(['user_id' => $request->user()->id, 'title' => null]);

            ChatSession::enforceLimit($request->user()->id);

            return [$session, true];
        }

        $session = ChatSession::where('user_id', $request->user()->id)->findOrFail($sessionId);

        return [$session, false];
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildContext(ChatSession $session, string $message): array
    {
        $messages = $session->messages()
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (ChatMessage $item): array => ['role' => $item->role, 'content' => $item->content])
            ->values()
            ->all();

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    private function persistExchange(ChatSession $session, string $userMessage, string $reply): void
    {
        $session->messages()->createMany([
            ['role' => 'user', 'content' => $userMessage],
            ['role' => 'assistant', 'content' => $reply],
        ]);

        if ($session->title === null) {
            $session->update(['title' => Str::limit($userMessage, 60, '')]);
        }

        $session->touch();
    }
}
