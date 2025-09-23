<?php

namespace Dev1\NotifyLaravel;

use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushResult;
use Dev1\NotifyCore\DTO\PushTarget;
use Dev1\NotifyCore\Platform\AndroidOptions;
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
                    if (!isset($clientConfig['service_account_json'])) {
                        throw new \RuntimeException("You must upload and declare the Firebase JSON credentials for enabling FCM v1.");
                    };

                    if (!isset($clientConfig['project_id'])) {
                        throw new \RuntimeException("You must declare the Firebase Project ID.");
                    }

                    $sa = json_decode(file_get_contents($clientConfig['service_account_json']), true);

                    $scopes  = isset($clientConfig['scopes']) ? $clientConfig['scopes'] : ['https://www.googleapis.com/auth/firebase.messaging'];
                    $timeout = isset($clientConfig['timeout']) ? (int) $clientConfig['timeout'] : 10;
                    $project = isset($clientConfig['project_id']) ? $clientConfig['project_id'] : null;


                    $http = new Psr18Client();
                    $psr17 = new Psr17Factory();
                    $requestFactory = $psr17;
                    $streamFactory  = $psr17;

                    $tpClass = '\\Dev1\\NotifyCore\\Auth\\GoogleServiceAccountTokenProvider';

                    if (!class_exists($tpClass)) {
                        throw new \RuntimeException('GoogleServiceAccountTokenProvider not found at core.');
                    }

                    $config = [
                        'client_email' => $sa['client_email'],
                        'private_key' => $sa['private_key'],
                        'scope' => $scopes,
                        'cache_leeway' => $timeout,
                    ];

                    $tokenProvider = new $tpClass(
                        $http,
                        $requestFactory,
                        $streamFactory,
                        $logger,
                        $config,
                    );

                    $client = null;

                    $try = [
                        '\\Dev1\\NotifyCore\\Drivers\\FcmHttpV1Client',
                    ];

                    foreach ($try as $driverClass) {
                        if (class_exists($driverClass)) {
                            $driverConfig = [
                                'project_id' => $project,
                            ];

                            $client = new $driverClass(
                                $http,
                                $requestFactory,
                                $streamFactory,
                                $tokenProvider,
                                $logger,
                                $driverConfig,
                            );
                            break;
                        }
                    }

                    if (!$client) {
                        throw new \RuntimeException('FCM v1 client not found (FcmHttpV1Client)');
                    }

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

                    $message = new PushMessage(
                        $payload['title'],
                        $payload['body'],
                        isset($payload['data']) ? $payload['data'] : null,
                    );

                    $androidOverrides = $this->toAndroidArray($payload['android'] !== null ? $payload['android'] : []);
                    $apnsOverrides    = $this->toApnsArray($payload['apns'] !== null ? $payload['apns'] : []);

                    $platformDefaults = isset($this->clientsConfig[$name]['platform_defaults']) ? $this->clientsConfig[$name]['platform_defaults'] : [];
                    $androidDefaults = isset($platformDefaults['android']) ? $platformDefaults['android'] : [];
                    $apnsDefaults = isset($platformDefaults['apns']) ? $platformDefaults['apns'] : [];

                    $androidMerged = array_replace_recursive($androidDefaults, $androidOverrides);
                    $apnsMerged    = array_replace_recursive($apnsDefaults, $apnsOverrides);

                    $message->platformOverrides = [
                        'android' => $androidMerged,
                        'apns' => $apnsMerged,
                    ];

                    $target = new PushTarget(
                        $target['token'],
                        $target['topic'],
                        $target['condition'],
                    );

                    return $this->registry->client($name)->send($message, $target);
                }

                private function toAndroidArray($options)
                {
                    if ($options instanceof AndroidOptions) return $options->toArray();
                    return is_array($options) ? $options : [];
                }

                private function toApnsArray($options)
                {
                    if (is_array($options)) return $options;
                    return [];
                }

                private function mergeApns(array $config, array $message)
                {
                    $configHeaders = (array) (isset($config['headers']) ? $config['headers'] : []);
                    $configAps = (array) (isset($config['aps']) ? $config['aps'] : []);
                    $configCustom = (array) (isset($config['custom']) ? $config['custom'] : []);

                    $messageHeaders = (array) (isset($message['headers']) ? $message['headers'] : []);
                    $payload = (array) (isset($message['payload']) ? $message['payload'] : []);
                    $messageAps = (array) (isset($payload['aps']) ? $payload['aps'] : []);
                    $messageCustom = $payload;

                    unset($messageCustom['aps']);

                    return [
                        'headers' => array_replace($configHeaders, $messageHeaders),
                        'payload' => array_replace_recursive(
                            ['aps' => $configAps],
                            ['aps' => $messageAps],
                            $configCustom,
                            $messageCustom
                        ),
                    ];
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
}
