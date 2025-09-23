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


