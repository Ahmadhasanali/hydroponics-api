<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncDisposableEmailDomainsTest extends TestCase
{
    public function test_command_downloads_and_writes_blocklist(): void
    {
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response("# comment\nMailinator.com\n\nyopmail.com\nguerrillamail.com\n"),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'blocklist').'.php';

        $this->artisan('app:sync-disposable-email-domains', ['--path' => $path])
            ->expectsOutputToContain('3')
            ->assertExitCode(0);

        $contents = File::get($path);
        $this->assertStringContainsString('mailinator.com', $contents);
        $this->assertStringContainsString('yopmail.com', $contents);
        $this->assertStringContainsString('guerrillamail.com', $contents);
        $this->assertStringNotContainsString('comment', $contents);

        File::delete($path);
    }

    public function test_command_fails_when_download_fails(): void
    {
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response('error', 500),
        ]);

        $this->artisan('app:sync-disposable-email-domains')
            ->assertExitCode(1);
    }
}
