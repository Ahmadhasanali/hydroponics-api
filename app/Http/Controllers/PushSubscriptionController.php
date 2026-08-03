<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'device_info' => ['nullable', 'string', 'max:255'],
        ]);

        $subscribable = $request->user();

        $subscription = PushSubscription::where('fcm_token', $validated['fcm_token'])->first();

        if ($subscription
            && ($subscription->subscribable_type !== $subscribable::class
                || $subscription->subscribable_id !== $subscribable->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Token sudah terdaftar untuk pengguna lain.',
            ], 409);
        }

        PushSubscription::updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'subscribable_type' => $subscribable::class,
                'subscribable_id' => $subscribable->id,
                'platform' => $validated['platform'] ?? 'android',
                'device_info' => $validated['device_info'] ?? null,
            ],
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
        ]);

        $subscribable = $request->user();

        PushSubscription::where('subscribable_type', $subscribable::class)
            ->where('subscribable_id', $subscribable->id)
            ->where('fcm_token', $validated['fcm_token'])
            ->delete();

        return response()->json(['success' => true]);
    }
}
