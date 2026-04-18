<?php

namespace Dev1\NotifyLaravel\Tests;

use Dev1\NotifyCore\Drivers\FcmHttpV1Client;
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

    public function test_logging_channel_is_applied_when_configured()
    {
        $this->app['config']->set('notify.logging.channel', 'stack');

        $logger = $this->app->make(LaravelLogger::class);
        $this->assertInstanceOf(LaravelLogger::class, $logger);
    }

    public function test_fcm_client_is_registered_with_inline_service_account_json()
    {
        $this->app['config']->set('notify.clients', [
            'fcm' => [
                'driver'               => 'fcm_v1',
                'project_id'           => 'notify-test',
                'service_account_json' => $this->dummyServiceAccountJson(),
                'scopes'               => ['https://www.googleapis.com/auth/firebase.messaging'],
                'max_retries'          => 3,
                'retry_base_delay_ms'  => 50,
                'cache_leeway'         => 15,
            ],
        ]);

        /** @var ClientRegistry $registry */
        $registry = $this->app->make(ClientRegistry::class);

        $this->assertInstanceOf(FcmHttpV1Client::class, $registry->client('fcm'));
    }

    public function test_fcm_client_is_registered_with_file_service_account()
    {
        $path = tempnam(sys_get_temp_dir(), 'notify-sa-') . '.json';
        file_put_contents($path, $this->dummyServiceAccountJson());

        try {
            $this->app['config']->set('notify.clients', [
                'fcm' => [
                    'driver'               => 'fcm_v1',
                    'project_id'           => 'notify-test',
                    'service_account_json' => $path,
                ],
            ]);

            $registry = $this->app->make(ClientRegistry::class);
            $this->assertInstanceOf(FcmHttpV1Client::class, $registry->client('fcm'));
        } finally {
            @unlink($path);
        }
    }

    public function test_fcm_client_is_registered_with_cache_store()
    {
        $this->app['config']->set('notify.clients', [
            'fcm' => [
                'driver'               => 'fcm_v1',
                'project_id'           => 'notify-test',
                'service_account_json' => $this->dummyServiceAccountJson(),
                'cache_store'          => 'array',
                'cache_key'            => 'custom-token-key',
            ],
        ]);

        $registry = $this->app->make(ClientRegistry::class);
        $this->assertInstanceOf(FcmHttpV1Client::class, $registry->client('fcm'));
    }

    public function test_fcm_registration_throws_on_missing_project_id()
    {
        $this->app['config']->set('notify.clients', [
            'fcm' => [
                'driver'               => 'fcm_v1',
                'service_account_json' => $this->dummyServiceAccountJson(),
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/project\s*id/i');

        $this->app->make(ClientRegistry::class);
    }

    public function test_fcm_registration_throws_on_missing_service_account()
    {
        $this->app['config']->set('notify.clients', [
            'fcm' => [
                'driver'     => 'fcm_v1',
                'project_id' => 'notify-test',
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/firebase json credentials/i');

        $this->app->make(ClientRegistry::class);
    }

    public function test_fcm_registration_throws_on_malformed_json_string()
    {
        $this->app['config']->set('notify.clients', [
            'fcm' => [
                'driver'               => 'fcm_v1',
                'project_id'           => 'notify-test',
                'service_account_json' => '{not valid json',
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid service account json/i');

        $this->app->make(ClientRegistry::class);
    }

    public function test_fcm_registration_throws_on_incomplete_service_account()
    {
        $this->app['config']->set('notify.clients', [
            'fcm' => [
                'driver'               => 'fcm_v1',
                'project_id'           => 'notify-test',
                'service_account_json' => json_encode(['client_email' => 'a@b.c']),
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/client_email and private_key/i');

        $this->app->make(ClientRegistry::class);
    }

    public function test_unknown_driver_is_silently_ignored()
    {
        $this->app['config']->set('notify.clients', [
            'legacy' => [
                'driver' => 'some-unsupported-driver',
            ],
        ]);

        $registry = $this->app->make(ClientRegistry::class);
        $this->assertInstanceOf(ClientRegistry::class, $registry);
    }

    private function dummyServiceAccountJson(): string
    {
        return (string) json_encode([
            'type'         => 'service_account',
            'project_id'   => 'notify-test',
            'client_email' => 'test@example.iam.gserviceaccount.com',
            'private_key'  => "-----BEGIN PRIVATE KEY-----\nABC\n-----END PRIVATE KEY-----\n",
        ]);
    }
}
