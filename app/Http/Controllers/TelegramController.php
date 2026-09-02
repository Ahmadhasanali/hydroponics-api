<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Farm;
use App\Models\MessagingAccount;
use App\Models\MessagingLinkCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    public function linkCode(Request $request): JsonResponse
    {
        $user = $request->user();

        MessagingLinkCode::where('user_id', $user->id)->whereNull('used_at')->delete();

        $code = Str::upper(Str::random(6));

        $row = MessagingLinkCode::create([
            'user_id' => $user->id,
            'channel' => 'telegram',
            'code' => $code,
            'expires_at' => now()->addSeconds(60),
        ]);

        return $this->successResponse([
            'code' => $row->code,
            'expires_at' => $row->expires_at->toIso8601String(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $acc = MessagingAccount::where('user_id', $request->user()->id)->first();

        if (! $acc) {
            return $this->successResponse(['linked' => false]);
        }

        return $this->successResponse([
            'linked' => true,
            'external_id' => $acc->external_id,
            'linked_at' => $acc->linked_at,
            'default_farm_id' => $acc->default_farm_id,
        ]);
    }

    public function updateDefaultFarm(Request $request): JsonResponse
    {
        $validated = $request->validate(['farm_id' => 'required|integer|exists:farms,id']);

        $acc = MessagingAccount::where('user_id', $request->user()->id)->firstOrFail();

        $farm = Farm::findOrFail($validated['farm_id']);

        $this->authorize('viewFinance', $farm);

        $acc->update(['default_farm_id' => $farm->id]);

        return $this->successResponse(['default_farm_id' => $acc->default_farm_id]);
    }

    public function unlink(Request $request): JsonResponse
    {
        MessagingAccount::where('user_id', $request->user()->id)->delete();

        return $this->successResponse(null, 'Tautan Telegram diputus.');
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = config('telegram.webhook_secret');

        if (empty($secret)) {
            Log::warning('Telegram webhook secret empty — webhook not verified');
        } elseif ($request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            return response()->json(['ok' => false], 403);
        }

        dispatch(new ProcessTelegramUpdate($request->all()))->onQueue('default');

        return response()->json(['ok' => true]);
    }
}
