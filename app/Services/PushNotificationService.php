<?php

namespace App\Services;

use App\Models\Farm\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    public function __construct(private ?Messaging $messaging) {}

    public function sendToUser(User|Staff $user, string $title, string $body, ?string $url = null): void
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty() || ! $this->messaging) {
            return;
        }

        foreach ($subscriptions as $subscription) {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withToken($subscription->fcm_token);

            if ($url) {
                $message = $message->withData(['url' => $url]);
            }

            try {
                $this->messaging->send($message);
            } catch (NotFound) {
                $subscription->delete();
            } catch (MessagingException $e) {
                Log::warning("FCM gagal kirim ke token #{$subscription->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
