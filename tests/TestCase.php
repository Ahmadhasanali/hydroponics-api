<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Fix for git worktree where vendor is symlinked to main repo:
        // Application::inferBasePath() otherwise returns main repo path.
        $worktreeBase = dirname(__DIR__);
        $_ENV['APP_BASE_PATH'] = $worktreeBase;
        $_SERVER['APP_BASE_PATH'] = $worktreeBase;
        putenv('APP_BASE_PATH='.$worktreeBase);

        // Fix for host execution where "pgsql" hostname is not resolvable
        // (phpunit.xml expects Sail's pgsql service). Fallback to 127.0.0.1.
        $pgsqlResolved = @gethostbyname('pgsql');
        if ($pgsqlResolved === 'pgsql') {
            $_ENV['DB_HOST'] = '127.0.0.1';
            $_SERVER['DB_HOST'] = '127.0.0.1';
            putenv('DB_HOST=127.0.0.1');
        }

        return parent::createApplication();
    }
}
