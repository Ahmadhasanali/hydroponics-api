<?php

namespace App\Jobs;

use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyFarmActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public DailyMonitoring|NutrientAddition|PhDownLog $entity,
    ) {}

    public function handle(PushNotificationService $push): void
    {
        $entity = $this->entity->load(['tank.farm', 'user']);
        $tank = $entity->tank;
        $farm = $tank?->farm;
        $actor = $entity->user;

        if (! $farm || ! $actor) {
            return;
        }

        $title = 'Aktivitas Farm';
        $body = match (true) {
            $entity instanceof DailyMonitoring => "{$actor->name} mencatat PPM {$entity->ppm} & pH {$entity->ph} — {$tank->name}",
            $entity instanceof NutrientAddition => "{$actor->name} menambah AB Mix — {$tank->name} (PPM {$entity->ppm_before} → {$entity->ppm_after})",
            default => "{$actor->name} menurunkan pH — {$tank->name} (pH {$entity->ph_before} → {$entity->ph_after})",
        };

        $farm->users()
            ->where('users.id', '!=', $entity->user_id)
            ->get()
            ->each(fn (User $user) => $push->sendToUser($user, $title, $body, route('daily-monitoring.index')));
    }
}
