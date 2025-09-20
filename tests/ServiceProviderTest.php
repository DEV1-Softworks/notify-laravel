<?php

namespace Dev1\NotifyLaravel\Tests;

use Dev1\NotifyCore\Registry\ClientRegistry;
use Dev1\NotifyLaravel\Support\LaravelLogger;

class ServiceProviderTest extends TestCase
{
    public function test_config_is_published()
    {
        @unlink(config_path('notify.php'));

        $this->artisan('vendor:publish', [
            '--tag'   => 'notify-config',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertFileExists(config_path('notify.php'), 'Notify config file was not published.');
    }

    public function test_container_binds_are_resolvable()
    {
        $logger = $this->app->make(LaravelLogger::class);
        $this->assertInstanceOf(LaravelLogger::class, $logger);

        $registry = $this->app->make(ClientRegistry::class);
        $this->assertInstanceOf(ClientRegistry::class, $registry);
    }
}
