<?php

namespace App\Http\Controllers;

use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ChatSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = ChatSession::where('user_id', $request->user()->id)
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    public function store(Request $request): JsonResponse
    {
        $session = ChatSession::create([
            'user_id' => $request->user()->id,
            'title' => null,
        ]);

        ChatSession::enforceLimit($request->user()->id);

        return response()->json(['session' => $session->loadCount('messages')], 201);
    }

    public function update(Request $request, ChatSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        $validated = $request->validate([
            'title' => 'required|string|min:1|max:60',
        ]);

        $session->update($validated);

        return response()->json(['session' => $session->loadCount('messages')]);
    }

    public function destroy(Request $request, ChatSession $session): Response
    {
        $this->authorizeSession($request, $session);

        $session->delete();

        return response()->noContent();
    }

    public function messages(Request $request, ChatSession $session): JsonResponse
    {
        abort_if($session->user_id !== $request->user()->id, 404);

        $messages = $session->messages()->orderBy('created_at')->orderBy('id')->get();

        return response()->json(['messages' => $messages]);
    }

    public function clear(Request $request, ChatSession $session): Response
    {
        abort_if($session->user_id !== $request->user()->id, 404);

        $session->messages()->delete();

        $session->touch();

        return response()->noContent();
    }

    public function migrate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'messages' => 'required|array|max:20',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:8000',
        ]);

        $user = $request->user();

        if (ChatSession::where('user_id', $user->id)->exists()) {
            return response()->json(['migrated' => false]);
        }

        $session = ChatSession::create(['user_id' => $user->id, 'title' => null]);
        $messages = array_map(
            static fn (array $item): array => [
                'chat_session_id' => $session->id,
                'role' => $item['role'],
                'content' => $item['content'],
            ],
            $validated['messages'],
        );
        $session->messages()->createMany($messages);

        $title = $validated['messages'][0]['content'] ?? null;
        if ($title !== null) {
            $session->update(['title' => Str::limit($title, 60, '')]);
        }

        return response()->json(['migrated' => true, 'session' => $session->loadCount('messages')]);
    }

    private function authorizeSession(Request $request, ChatSession $session): void
    {
        abort_if($session->user_id !== $request->user()->id, 404);
    }
}
