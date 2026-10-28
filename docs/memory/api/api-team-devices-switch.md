---
name: api-team-devices-switch
description: The Team devices switch in Settings › API (includes/api/device_switch.php): which device application a save starts, widens, makes or disables, the ceiling, expiry, why off keeps the rows
---

## Where is it and what does it show?
- The Application API card of Site Settings (`includes/settings/api.php`, saved by `api.save.php`), drawn only where `api_apps` has its `kind` column. On while a device application is usable now: active and not past `expires_at` (`api_device_switch_is_on()`, the same test as `api_device_app_check()` and the discovery document's `device_signin`).
- Application Access still makes and edits device applications by hand; the switch is the one-click way.

## What does a save do?
- Planned by the pure `api_device_switch_plan($apps, $on, $ceiling, $now)`, written by `api_device_switch_save()`; a save that changes nothing writes nothing (no audit or Site Log line).
- On: the oldest usable device application gains the ceiling's scopes; with none usable, the oldest device application is set active (a past expiry is cleared, or the switch would show off again); with none at all, `api_app_create()` makes "Team devices" (kind `device`, no secret, 120/min, owner the person saving).
- Off: every active device application is disabled. Rows, scopes and devices are kept; a disabled application refuses every device call, and on lets them in again.
- Scopes the operator chose are never removed; only the ceiling is added.

## What is the ceiling?
- `workspace:write`, `tasks:write`, `orders:read`, `meta:read`, `customers:read`, `products:read` through `api_scopes_normalise()` (`api_device_switch_ceiling()`). Module scopes are valid only while the module is on, so a site that turns the Workspace on later gains them on its next save. Each person still gets only their own rights (`api_device_scopes()`).

## What is not in it yet?
- QR pairing (`POST /auth/pair`, table `api_device_pairings`, decided #2470) comes in its own PR (#1640).

Tests: `tests/api_device_switch_test.php`. Issue #1640.
