<?php

namespace Tests\Feature\Commands;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResendEmailVerificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    #[Test]
    public function resends_verification_email_to_unverified_user_whose_last_send_is_older_than_interval(): void
    {
        $user = User::factory()->unverified()->create([
            'created_at' => now()->subHours(2),
            'verification_sent_at' => now()->subMinutes(10),
        ]);

        $this->artisan('email:resend-unverified')->assertExitCode(0);

        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->assertTrue($user->fresh()->verification_sent_at->gt(now()->subMinute()));
    }

    #[Test]
    public function resends_verification_email_to_unverified_user_who_never_received_one(): void
    {
        $user = User::factory()->unverified()->create([
            'created_at' => now()->subMinutes(30),
            'verification_sent_at' => null,
        ]);

        $this->artisan('email:resend-unverified')->assertExitCode(0);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    #[Test]
    public function does_not_resend_to_user_whose_last_send_is_within_interval(): void
    {
        $user = User::factory()->unverified()->create([
            'created_at' => now()->subHours(2),
            'verification_sent_at' => now()->subMinute(),
        ]);

        $this->artisan('email:resend-unverified')->assertExitCode(0);

        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }

    #[Test]
    public function does_not_resend_to_user_who_registered_less_than_interval_ago(): void
    {
        $user = User::factory()->unverified()->create([
            'created_at' => now()->subMinutes(2),
            'verification_sent_at' => null,
        ]);

        $this->artisan('email:resend-unverified')->assertExitCode(0);

        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }

    #[Test]
    public function does_not_resend_to_verified_user(): void
    {
        $user = User::factory()->create([
            'verification_sent_at' => now()->subMinutes(10),
        ]);

        $this->artisan('email:resend-unverified')->assertExitCode(0);

        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }
}