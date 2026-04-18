# DEV1 Notify — Changelog

All notable changes for **dev1/notify-laravel** are documented in this file.  
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-09-23
### Added
- Initial release of **dev1/notify-laravel** 🎉
- Laravel 8 and PHP 7.4+ support.
- Service provider integration for [dev1/notify-core](https://packagist.org/packages/dev1/notify-core).
- PSR-3 to Laravel logger bridge (`LaravelLogger`).
- Facade `Notify` with `send()` method returning `PushResult`.
- Custom Laravel Notification channel (`dev1-notify`).
- Event `NotifySent` dispatched after each notification is sent.
- Config file (`config/notify.php`) with publish support.
- GitHub Actions workflow with PHPUnit + Testbench.
- Coverage enforcement (≥ 80%) and badge integration.
- Documentation: README, CONTRIBUTING, CODE_OF_CONDUCT, LICENSE, SECURITY.

---

## [1.1.0] — 2025-09-23

**Added**
- Homologated `config/notify.php`:
  - `default`, `clients.fcm` (`driver: fcm_v1`, `project_id`, `service_account_json` as **file path** or **JSON string**, `scopes`, `timeout`).
  - `logging` section (`enabled`, `channel`).
  - Optional per-client `platform_defaults` for `android` and `apns`.
- `NotifyServiceProvider`: registers `ClientRegistry`, resolves Service Account (path or JSON string), respects `scopes`/`timeout`/logging.
- `Notifier` binding: merges `platform_defaults` (config) **with** per-message overrides (`payload['android'|'apns']` as arrays or value objects).
- `Channels\NotifyChannel`: thin channel that calls `toDev1Notify($notifiable)` and delegates to `Notifier`.
- `Events\NotifySent`: emitted after each send for auditing/metrics.

**Changed**
- FCM HTTP v1 serialization (within the core client usage):
  - Converts `android.ttl` (int seconds) to `"Xs"` string.
  - Ensures all `message.data` values are strings.
  - Maps `android.notification.channel_id`, and APNs `headers` + `payload.aps`.

**Docs / Tests**
- README: usage via Laravel Notification channel and via `Notifier` (programmatic).
- Tests: merging of `platform_defaults` + overrides, TTL string formatting, stringified `data`, APNs headers/APS mapping.

**Backward compatibility**
- No renames; no public signature changes. Existing notifications using only `title/body/data` keep working.
- `platform_defaults` is optional; if omitted, behavior is unchanged.

---

## [1.2.0] — 2026-04-18

Bumps the floor of `dev1/notify-core` to **^1.2**, aligns the adapter with core 1.2.0's new contracts, exposes the new retry/caching knobs, and widens framework compatibility to Laravel 8 – 11.

**Added**
- **PSR-16 token cache**: set `cache_store` (any Laravel cache store name, e.g. `redis`, `file`) to share Google OAuth tokens across processes/workers. Optional `cache_key` to override the default derived key. Laravel's `Illuminate\Contracts\Cache\Repository` already implements `Psr\SimpleCache\CacheInterface`, so no adapter is needed.
- **Retry configuration**: `max_retries` and `retry_base_delay_ms` are now passed through to both the token provider and `FcmHttpV1Client` — retries apply to 5xx / 429 / PSR-18 transport errors with exponential backoff + jitter.
- **Env passthrough**: `NOTIFY_FCM_CACHE_LEEWAY`, `NOTIFY_FCM_CACHE_STORE`, `NOTIFY_FCM_CACHE_KEY`, `NOTIFY_FCM_MAX_RETRIES`, `NOTIFY_FCM_RETRY_BASE_DELAY_MS`.
- README: "Handling send results" section showing `PushResult::isUnregistered()` / `isQuotaExceeded()` / `isTransient()` / `isInvalidArgument()` usage in listeners and post-send handlers.
- Optional `endpoint` passthrough for `FcmHttpV1Client` (useful for testing / emulators).

**Changed**
- **Laravel support widened**: `illuminate/support` and `illuminate/notifications` now `^8.0|^9.0|^10.0|^11.0|^12.0|^13.0`. Unblocks PHP 8.2+ users who were stuck on Laravel 8 and adds support for the current Laravel 12 and 13 releases.
- `symfony/http-client` widened to `^5.3|^6.0|^7.0`.
- `orchestra/testbench` dev range widened to `^6.29|^7.0|^8.0|^9.0|^10.0|^11.0` (covers Laravel 8 through 13); `phpunit/phpunit` to `^9.6|^10.0`.
- `NotifyServiceProvider`: `Notifier` closure now builds the `PushTarget` with only the single non-null field among `token`/`topic`/`condition`. Core 1.2.0 now **throws** when more than one is set; previously it silently preferred `token`. Empty strings are treated as null.
- `NotifyServiceProvider`: `PushMessage` is now constructed via its 4-arg form (title, body, data, platformOverrides) instead of assigning `platformOverrides` post-construction.
- `NotifyChannel` / `Notifier`: payload keys (`title`, `body`, `data`, `android`, `apns`) are now null-safe via `??` — missing keys no longer emit PHP 8 `Undefined array key` warnings.
- `toApnsArray()` now accepts an `ApnsOptions` instance (matching the existing `AndroidOptions` handling).
- `Facades\Notify` docblock corrected: `send(array $target, array $payload, ?string $client = null): \Dev1\NotifyCore\DTO\PushResult`.

**Fixed**
- `NotifyServiceProvider`: missing error handling around the service-account JSON load. Path **or** inline JSON is still supported, but malformed input / missing file / missing `client_email`/`private_key` now throw a clear `RuntimeException` instead of silently crashing downstream.
- `NotifyServiceProvider`: `timeout` was being mis-used as `cache_leeway` (defaulted to 10s, shrinking effective token TTL). Split into its own `cache_leeway` key with a sensible 30s default matching core.
- `NotifierFacadeTest` had an incorrect namespace (`Dev1\NotifyCore\Auth`), now `Dev1\NotifyLaravel\Tests`.
- README: fixed broken `'token' => , $token,` array literal in the `OrderPaid` example.
- Dead `mergeApns()` helper removed from the `Notifier` implementation (never wired into the send path).

**Removed**
- **Breaking (config)**: `timeout` key on the FCM client config is no longer read (it was never applied anyway — core 1.2.0 removed the undocumented `timeout` key from `FcmHttpV1Client`). Replace with `cache_leeway` if you were relying on the previous (buggy) behavior.

**Backward compatibility**
- Public `Notifier::send()` signature unchanged. Existing notifications keep working.
- If you only used `title/body/data` in your payloads, no code changes required.
- If you relied on multi-field `PushTarget` (passing both `token` and `topic`), you must now send only one — the adapter narrows automatically, but upstream callers that intentionally set multiples will start getting `InvalidArgumentException`.

---


