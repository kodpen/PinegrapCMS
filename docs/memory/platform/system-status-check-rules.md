---
name: system-status-check-rules
description: System Status rules - a check row and its button share one read, the cache dropped by pg_system_status_forget() after a save, per-check detail boxes, confirm tiles on buttons.
---

## Why must a status row and its action button share one read?
- `get_system_status_checks()` is cached for ten minutes; the server-rules tile reads the file live. When they diverge, a row says "2 missing" next to a button that is never drawn (nothing to do), read as "says missing, does not fix".
- Rule: **a check row and its action button are fed by the same read.** `pg_server_config_scan()` is memoized per request; `pg_server_rules_signature()` collects everything the scan decided into one string, stored with the cache and compared on read - if different the cache is dropped.
- When adding a new "check + button" pair, build the same pattern.

## When is the cache dropped?
- A screen that changes what a tile reports calls `pg_system_status_forget()` (`includes/fn/system_status.php`) after a write without errors, so the next draw recomputes instead of showing the old state for ten minutes; nothing drops `system_status_cache.json` by its file name.
- Callers: both Site Settings save paths (`pg_settings_apply()` in `settings/fragment.php`, the POST branch of `settings/screen.php`), unconditionally on a save without form errors; the `webhook_status` and `webhook_retry` branches of `api_settings.php`; the Maintenance Mode switch (`maintenance.save.php`); `erp_efatura_threshold_forget_status()`. Translations are baked into the cache, so a Software Language change needs the drop too.
- The symptom (#PR): "Operations Alerts: Off" stayed Off up to ten minutes after Alerts on > Save; the same for CAPTCHA, Secure Mode, WAF, IndexNow, feeds, auto-backup, a failing webhook after Retry. `tests/system_status_forget_test.php` pins the callers.
- `panel/system.php` (deep table repair) still unlinks the file itself.

## How is the detail panel organised?
- Rows made with `$makeIcon(..., $detail)` write into a box `system_status_detail_<n>`, and the box starts with which check it belongs to. There used to be one `#system_status_detail`; adding a second detailed check made two chevrons open the same box.

## Why must a check's message be a single literal?
- `$makeIcon()` stores `lang($title)` and `lang($content)`, so `check_lang.php` never sees those literals: each one must be a Turkish key, and a prefix joined to a list is translated on its own (`rtrim(lang('Disabled directive(s): ')) . ' ' . implode(…)`). `tests/system_status_lang_keys_test.php` reads the tokens and pins this, the literal `$backup_message`s and the `server_config.php` rule labels (#2668).

## What about confirm tiles?
- If the confirm-asking tile is a `<button>`, read `event.isDefaultPrevented()`. The `.pg-health-tile[data-confirm-content]` handler only calls `preventDefault()`; on a link that stops navigation, on a button there is nothing to stop.

Related: docs/memory/platform/system-status-ca-bundle.md
