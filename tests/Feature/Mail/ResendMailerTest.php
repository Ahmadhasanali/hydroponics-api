<?php

namespace Tests\Feature\Mail;

use Illuminate\Mail\Transport\ResendTransport;
use Tests\TestCase;

class ResendMailerTest extends TestCase
{
    public function test_resend_transport_resolves(): void
    {
        $transport = app('mail.manager')->mailer('resend')->getSymfonyTransport();

        $this->assertInstanceOf(ResendTransport::class, $transport);
    }

    public function test_resend_api_key_reads_from_env(): void
    {
        $this->assertSame('re_test_123', config('services.resend.key'));
    }
}
