<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SyncDisposableEmailDomains extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:sync-disposable-email-domains {--path= : Path file config output (default config/disposable-email-domains.php)}';

    /**
     * The console command description.
     */
    protected $description = 'Download daftar domain email sementara terbaru dan tulis ke config.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/master/disposable_email_blocklist.conf';

        $response = Http::timeout(30)->get($url);

        if ($response->failed()) {
            $this->error('Gagal mengambil daftar domain email sementara.');

            return self::FAILURE;
        }

        $domains = collect(explode("\n", $response->body()))
            ->map(fn (string $line): string => strtolower(trim($line)))
            ->filter(fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#') && ! str_contains($line, ' '))
            ->unique()
            ->sort()
            ->values();

        $path = $this->option('path') ?: config_path('disposable-email-domains.php');
        $contents = "<?php\n\nreturn ".var_export($domains->all(), true).";\n";
        File::put($path, $contents);

        $this->info('Daftar domain email sementara diperbarui: '.$domains->count().' domain.');

        return self::SUCCESS;
    }
}
