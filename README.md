# DEV1 Notify Laravel Adapter

[![CI](https://github.com/DEV1-Softworks/notify-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/DEV1-Softworks/notify-laravel/actions/workflows/tests.yml)
[![Release](https://github.com/DEV1-Softworks/notify-laravel/actions/workflows/release.yml/badge.svg)](https://github.com/DEV1-Softworks/notify-laravel/actions/workflows/release.yml)
[![Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/RZEROSTERN/deda998a340f76be1e69cf7ff07dab0c/raw/notify-coverage.json)](#)
[![Latest Stable Version](https://img.shields.io/packagist/v/dev1/notify-laravel.svg)](https://packagist.org/packages/dev1/notify-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/dev1/notify-laravel.svg)](https://packagist.org/packages/dev1/notify-laravel)
[![License](https://img.shields.io/packagist/l/dev1/notify-laravel.svg)](LICENSE)

Adapter package to integrate [DEV1 Notify Core](https://packagist.org/packages/dev1/notify-core) into **Laravel 8 – 13** (PHP 7.4 – 8.5).

Provides:
- Service provider + auto-discovered `Notify` facade.
- Config publishing (`config/notify.php`).
- Custom Laravel Notification channel (`dev1-notify`).
- Logger bridge to Laravel's PSR-3 logger.
- PSR-16 token cache wiring (share OAuth tokens across processes/workers).
- Configurable retry/backoff passthrough for the FCM HTTP v1 driver.

---

## Installation

```bash
composer require dev1/notify-laravel
php artisan vendor:publish --tag=notify-config
```

The service provider (`Dev1\NotifyLaravel\NotifyServiceProvider`) and the `Notify` facade alias are registered automatically via Laravel's package auto-discovery — no manual changes to `config/app.php` are required.

For FCM v1, create a Firebase project and generate a service account key JSON file at the [Firebase Console](https://console.firebase.google.com/). Place it somewhere **outside** `public/` — the default looks for `storage/app/firebase/service-account.json`. Add it to `.gitignore`.

## Configuration

.env example:
```env
NOTIFY_DEFAULT=fcm
NOTIFY_FCM_PROJECT_ID=your-firebase-project-id
NOTIFY_FCM_SA_PATH=app/firebase/service-account.json

# Optional
NOTIFY_FCM_CACHE_LEEWAY=30            # seconds subtracted from token expiry
NOTIFY_FCM_CACHE_STORE=redis          # Laravel cache store to share OAuth tokens (null = disabled)
NOTIFY_FCM_CACHE_KEY=                 # override cache key (defaults to a hash of client_email)
NOTIFY_FCM_MAX_RETRIES=2              # retries on 5xx / 429 / transport errors
NOTIFY_FCM_RETRY_BASE_DELAY_MS=200    # initial backoff (doubles per attempt, +jitter)
NOTIFY_LOG=true
NOTIFY_LOG_CHANNEL=
```

Config file (`config/notify.php`) — published shape:
```php
return [
    'default' => env('NOTIFY_DEFAULT', 'fcm'),

    'clients' => [
        'fcm' => [
            'driver' => 'fcm_v1',
            'project_id' => env('NOTIFY_FCM_PROJECT_ID'),

            // Path to a JSON file OR an inline JSON string.
            'service_account_json' => storage_path(env('NOTIFY_FCM_SA_PATH', 'app/firebase/service-account.json')),

            'scopes' => [
                'https://www.googleapis.com/auth/firebase.messaging',
            ],

            // Seconds subtracted from OAuth token expiry before considering it expired.
            'cache_leeway' => env('NOTIFY_FCM_CACHE_LEEWAY', 30),

            // Optional PSR-16 cache for OAuth tokens. Set to a Laravel cache
            // store name (e.g. 'redis', 'file') to share tokens across processes.
            // null disables caching (each process fetches its own token).
            'cache_store' => env('NOTIFY_FCM_CACHE_STORE'),
            'cache_key'   => env('NOTIFY_FCM_CACHE_KEY'),

            // Retry behavior on transient 5xx / 429 / transport errors.
            // Exponential backoff with jitter is applied by notify-core.
            'max_retries'         => env('NOTIFY_FCM_MAX_RETRIES', 2),
            'retry_base_delay_ms' => env('NOTIFY_FCM_RETRY_BASE_DELAY_MS', 200),

            'platform_defaults' => [
                'android' => [
                    // 'priority' => 'HIGH',
                    // 'ttl' => 3600, // seconds; transport converts to "3600s"
                    // 'notification' => ['icon' => 'ic_stat_notify'],
                ],
                'apns' => [
                    // 'headers' => ['apns-priority' => '10', 'apns-push-type' => 'alert'],
                    // 'aps'     => ['sound' => 'default', 'mutable-content' => 1],
                    // 'custom'  => [],
                ],
            ],
        ],
    ],

    'logging' => [
        'enabled' => env('NOTIFY_LOG', true),
        'channel' => env('NOTIFY_LOG_CHANNEL', null),
    ],
];
```

> **Production tip — PSR-16 token cache.** Set `NOTIFY_FCM_CACHE_STORE=redis` (or any shared Laravel cache store) so OAuth tokens are reused across web workers and queue workers. Without it, every PHP process fetches its own token from Google on first send.

---

## Usage

We have two ways to use Notify in Laravel, with a Facade or via the Notification Channel. Use the one that best fits your needs.

### Via Facade

Simplest way to send a one-off notification. The `Notify` alias is registered by auto-discovery, so you can use it directly.

```php
use Dev1\NotifyLaravel\Facades\Notify;
use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Platform\ApnsOptions;

$android = AndroidOptions::make()
    ->withChannelId('your_channel_id')
    ->withPriority('HIGH')
    ->withTtl(900);

$apns = ApnsOptions::make()
    ->withHeaders(['apns-priority' => '10', 'apns-push-type' => 'alert'])
    ->withAps(['sound' => 'default']);

$result = Notify::send(
    // Target: exactly ONE of token / topic / condition must be non-null.
    ['token' => 'AAA', 'topic' => null, 'condition' => null],
    [
        'title'   => 'Hello',
        'body'    => 'Test message',
        'data'    => ['foo' => 'bar'], // optional
        'android' => $android,          // AndroidOptions or array, optional
        'apns'    => $apns,             // ApnsOptions or array, optional
    ],
    'fcm' // client name; omit or pass null to use the default
);

// $result is an instance of Dev1\NotifyCore\DTO\PushResult.
// See "Handling send results" below.
```

> **Target rule (since core 1.2.0).** Pass exactly one of `token` / `topic` / `condition`. The adapter treats missing keys and empty strings as null; passing more than one real value throws `InvalidArgumentException`.

> **Silent / data-only pushes.** Leave `title` and `body` empty (or omit them) to send a data-only message — core will drop the `notification` block automatically.

### Via Notification channel

Intended for Laravel notifications — you get queues, fan-out, testing helpers, etc.

```php
use Illuminate\Notifications\Notification;
use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Platform\ApnsOptions;

class OrderPaid extends Notification
{
    public function via($notifiable): array
    {
        return ['dev1-notify'];
    }

    public function toDev1Notify($notifiable): array
    {
        $token = $notifiable->fcm_token; // however you store the token

        $android = AndroidOptions::make()
            ->withChannelId('your_channel_id')
            ->withPriority('HIGH')
            ->withNotification(['image' => 'https://cdn.example.com/paid.png']);

        $apns = ApnsOptions::make()
            ->withHeaders(['apns-priority' => '10', 'apns-push-type' => 'alert'])
            ->withAps(['sound' => 'default']);

        return [
            'client' => 'fcm', // optional; falls back to notify.default
            'target' => [
                'token'     => $token,
                'topic'     => null,
                'condition' => null,
            ],
            'payload' => [
                'title'   => 'Payment Received',
                'body'    => 'Your order has been paid. Enjoy!',
                'data'    => ['order_id' => 123],
                'android' => $android,
                'apns'    => $apns,
            ],
        ];
    }
}

// Trigger:
$user->notify(new OrderPaid());
```

---

## Events

Every push sent through the `dev1-notify` channel dispatches `Dev1\NotifyLaravel\Events\NotifySent`:

- `$result` — `Dev1\NotifyCore\DTO\PushResult`
- `$notifiable` — the notifiable entity
- `$notification` — the `Illuminate\Notifications\Notification` instance
- `$client` — resolved client name (or `null` for the default)
- `$target`, `$payload` — the arrays that were sent

Typical listener:

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \Dev1\NotifyLaravel\Events\NotifySent::class => [
        \App\Listeners\HandleNotifyResult::class,
    ],
];
```

### Handling send results

`PushResult` exposes helpers for the common FCM error codes — use them in a `NotifySent` listener or right after `Notify::send()`:

```php
if ($result->isUnregistered()) {
    // Token no longer valid — prune it from your DB.
    $user->tokens()->where('fcm_token', $token)->delete();
} elseif ($result->isQuotaExceeded()) {
    // FCM is rate-limiting — back off before retrying.
} elseif ($result->isTransient()) {
    // 5xx / transport blip — safe to retry later
    // (core already retried per max_retries before surfacing this).
} elseif ($result->isInvalidArgument()) {
    // Payload shape is wrong — log and investigate, don't retry blindly.
}
```

Raw fields are also available: `$result->success`, `$result->id`, `$result->errorCode`, `$result->errorMessage`, `$result->raw`.

---

## Testing

You can run the tests with:

```bash
./vendor/bin/phpunit
```
### CI & CD

CI/CD is provided with GitHub Actions.

**CI** — [`tests.yml`](.github/workflows/tests.yml) runs on every push and pull request to `master`:
- PHPUnit with Orchestra Testbench.
- Coverage badge updated via Gist.
- **Enforces ≥ 80% coverage** (the job fails and blocks deployment otherwise).

**CD** — [`release.yml`](.github/workflows/release.yml) runs after a successful `tests.yml` on a push to `master`:
- Extracts the latest `## [X.Y.Z]` version from `CHANGELOG.md`.
- If `vX.Y.Z` is not yet tagged, creates an annotated tag at the tested commit and pushes it.
- Publishes a GitHub Release with auto-generated notes.
- Notifies Packagist via its update-package API so the new version is indexed immediately.

Required repository secrets for releases:
- `PACKAGIST_USERNAME` — your packagist.org username.
- `PACKAGIST_TOKEN`    — API token from packagist.org/profile/.
- `GIST_TOKEN` + `COVERAGE_GIST_ID` — already used by the coverage badge step.

If the Packagist secrets are absent the release still tags + creates the GitHub Release; the Packagist ping is skipped with a warning (useful when Packagist is already wired via its own webhook).

# Contributing

We welcome contributions! Please follow these steps:

1. Fork the repo and create a feature branch.
2. Run tests with `./vendor/bin/phpunit`.
3. Ensure coverage ≥ 80%.
4. Submit a PR.

Issues and suggestions are welcome on GitHub.

Made with ❤️ in Mexico 🇲🇽 by [DEV1 Softworks Labs](https://labs.dev1.mx)