<?php

namespace App\Http\Controllers;

use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    private function authorizeSession(Request $request, ChatSession $session): void
    {
        abort_if($session->user_id !== $request->user()->id, 404);
    }
}
