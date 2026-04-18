<?php

namespace Dev1\NotifyLaravel;

use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushResult;
use Dev1\NotifyCore\DTO\PushTarget;
use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Platform\ApnsOptions;
use Dev1\NotifyCore\Registry\ClientRegistry;
use Dev1\NotifyLaravel\Channels\NotifyChannel;
use Dev1\NotifyLaravel\Contracts\Notifier;
use Dev1\NotifyLaravel\Support\LaravelLogger;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;

class NotifyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/notify.php', 'notify');

        $this->app->singleton(LaravelLogger::class, function ($app) {
            $channel = $app['config']->get('notify.logging.channel');
            return new LaravelLogger($channel ? $app['log']->channel($channel) : $app['log']);
        });

        $this->app->singleton(ClientRegistry::class, function ($app) {
            $config = $app['config']->get('notify.clients', []);
            $logger = $app->make(LaravelLogger::class);

            $registry = new ClientRegistry($logger);

            foreach ($config as $name => $clientConfig) {
                $driver = isset($clientConfig['driver']) ? $clientConfig['driver'] : null;

                if ($driver === 'fcm_v1') {
                    if (empty($clientConfig['service_account_json'])) {
                        throw new \RuntimeException("You must upload and declare the Firebase JSON credentials for enabling FCM v1.");
                    }

                    if (empty($clientConfig['project_id'])) {
                        throw new \RuntimeException("You must declare the Firebase Project ID.");
                    }

                    $sa = $this->loadServiceAccount($clientConfig['service_account_json']);

                    if (empty($sa['client_email']) || empty($sa['private_key'])) {
                        throw new \RuntimeException('Service account JSON must contain client_email and private_key.');
                    }

                    $scopes  = isset($clientConfig['scopes']) ? $clientConfig['scopes'] : ['https://www.googleapis.com/auth/firebase.messaging'];
                    $project = $clientConfig['project_id'];

                    $http = new Psr18Client();
                    $psr17 = new Psr17Factory();
                    $requestFactory = $psr17;
                    $streamFactory  = $psr17;

                    $tpClass = '\\Dev1\\NotifyCore\\Auth\\GoogleServiceAccountTokenProvider';

                    if (!class_exists($tpClass)) {
                        throw new \RuntimeException('GoogleServiceAccountTokenProvider not found at core.');
                    }

                    $tokenConfig = [
                        'client_email' => $sa['client_email'],
                        'private_key' => $sa['private_key'],
                        'scope' => $scopes,
                    ];

                    foreach (['cache_leeway', 'cache_key', 'max_retries', 'retry_base_delay_ms'] as $passthrough) {
                        if (isset($clientConfig[$passthrough])) {
                            $tokenConfig[$passthrough] = $clientConfig[$passthrough];
                        }
                    }

                    $cache = null;
                    if (!empty($clientConfig['cache_store'])) {
                        $cache = $app['cache']->store($clientConfig['cache_store']);
                    }

                    $tokenProvider = new $tpClass(
                        $http,
                        $requestFactory,
                        $streamFactory,
                        $logger,
                        $tokenConfig,
                        $cache
                    );

                    $driverClass = '\\Dev1\\NotifyCore\\Drivers\\FcmHttpV1Client';
                    if (!class_exists($driverClass)) {
                        throw new \RuntimeException('FCM v1 client not found (FcmHttpV1Client)');
                    }

                    $driverCfg = ['project_id' => $project];
                    foreach (['max_retries', 'retry_base_delay_ms', 'endpoint'] as $passthrough) {
                        if (isset($clientConfig[$passthrough])) {
                            $driverCfg[$passthrough] = $clientConfig[$passthrough];
                        }
                    }

                    $client = new $driverClass(
                        $http,
                        $requestFactory,
                        $streamFactory,
                        $tokenProvider,
                        $logger,
                        $driverCfg
                    );

                    $registry->register($name, $client);
                    continue;
                }

                /**
                 * Here we can add more drivers according to the core compatibility.
                 */
            }

            return $registry;
        });

        $this->app->bind(Notifier::class, function ($app) {
            $registry = $app->make(ClientRegistry::class);
            $default = (string) $app['config']->get('notify.default', 'fcm');
            $clients = (array) $app['config']->get('notify.clients', []);

            return new class($registry, $default, $clients) implements Notifier {
                private $registry;
                private $defaultClient;
                private $clientsConfig;

                public function __construct($registry, $defaultClient, $clients)
                {
                    $this->registry = $registry;
                    $this->defaultClient = $defaultClient;
                    $this->clientsConfig = $clients;
                }

                public function send(array $target, array $payload, ?string $client = null): PushResult
                {
                    $name = $client ?: $this->defaultClient;

                    $androidOverrides = $this->toAndroidArray($payload['android'] ?? []);
                    $apnsOverrides    = $this->toApnsArray($payload['apns'] ?? []);

                    $platformDefaults = $this->clientsConfig[$name]['platform_defaults'] ?? [];
                    $androidDefaults  = $platformDefaults['android'] ?? [];
                    $apnsDefaults     = $platformDefaults['apns'] ?? [];

                    $message = new PushMessage(
                        (string) ($payload['title'] ?? ''),
                        (string) ($payload['body'] ?? ''),
                        $payload['data'] ?? null,
                        [
                            'android' => array_replace_recursive($androidDefaults, $androidOverrides),
                            'apns'    => array_replace_recursive($apnsDefaults, $apnsOverrides),
                        ]
                    );

                    return $this->registry->client($name)->send($message, $this->buildTarget($target));
                }

                private function buildTarget(array $target): PushTarget
                {
                    $token     = !empty($target['token'])     ? (string) $target['token']     : null;
                    $topic     = !empty($target['topic'])     ? (string) $target['topic']     : null;
                    $condition = !empty($target['condition']) ? (string) $target['condition'] : null;

                    return new PushTarget($token, $topic, $condition);
                }

                private function toAndroidArray($options): array
                {
                    if ($options instanceof AndroidOptions) return $options->toArray();
                    return is_array($options) ? $options : [];
                }

                private function toApnsArray($options): array
                {
                    if ($options instanceof ApnsOptions) return $options->toArray();
                    return is_array($options) ? $options : [];
                }
            };
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/notify.php' => config_path('notify.php'),
        ], 'notify-config');

        $this->app->make(ChannelManager::class)
            ->extend('dev1-notify', function ($app) {
                return $app->make(NotifyChannel::class);
            });
    }

    /**
     * Resolves the service-account source to a decoded array.
     * Accepts either a filesystem path or an inline JSON string.
     *
     * @return array<string,mixed>
     */
    private function loadServiceAccount(string $source): array
    {
        $raw = is_file($source) ? @file_get_contents($source) : $source;

        if ($raw === false || $raw === '') {
            throw new \RuntimeException('Could not read service account JSON from: ' . $source);
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            $err = function_exists('json_last_error_msg') ? json_last_error_msg() : 'invalid JSON';
            throw new \RuntimeException('Invalid service account JSON: ' . $err);
        }

        return $decoded;
    }
}
