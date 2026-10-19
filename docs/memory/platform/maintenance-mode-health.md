---
name: maintenance-mode-health
description: Maintenance mode (503 + Retry-After on page renders only, who passes, end time) and health.php for uptime monitors (statuses, no session); settings, status tile, API field
---

## What does maintenance mode do?
- While on (and before its optional end time), `get_page.php` calls `pg_maintenance_gate()` (`includes/fn/maintenance_mode.php`) right after `init.php`: a visitor gets `output_error(…, 503)` with `Retry-After` (seconds to the end, 3600 without one, held to 60–86400) and `Cache-Control: no-store`. The screen is the designed error page (error_page widget) or the legacy error page, like every site error; the message names the end time in digits (`pg_maintenance_time_text()`).
- Who passes: signed-in role < 3 (manager and above; role-3 accounts are also site members, so they do not), and addresses on the list, matched against `waf_client_ip()` with the firewall's patterns (`waf_ip_in_any()`, validated with `waf_valid_ip_pattern()`).
- Only page renders are held. Files (`get_file.php`), robots.txt, sitemap, the panel and its sign-in, `api.php`, `integration.php` and every endpoint a page posts to keep working: a payment provider's callback to `submit_order.php` must reach the shop, or a paid order is lost. A test keeps the gate out of `init.php`/`router.php`.
- An end time that has passed ends the mode by itself (`pg_maintenance_holds()`); nothing is written back.

## Where is it set and shown?
- Settings › General › Maintenance Mode (`pgset-maintenance`): switch, end time (datetime-local, site time zone), addresses. Columns `maintenance_mode`, `maintenance_until`, `maintenance_allow_ips` (2026.11.0, step 11.0.7, default off; PR #547); constants `MAINTENANCE_*` in `init.php`. A bad address, a bad time or an end already passed while switching on refuses the save. Switching logs a line and drops the System Status cache.
- The card warns when the address it shows is the server or a trusted proxy (`pg_maintenance_ip_is_shared()`; loopback means a tunnel or a local reverse proxy): every visitor has it. Saving a list that holds that address keeps it and warns (`pg_maintenance_list_opens_to_all()`). Before #NNN, `::1` on the list behind a Cloudflare tunnel let every visitor in, silently.
- System Status tile "Maintenance Mode" (actions column, not scored) only while the switch is on: warning with the end time, or "Ended" once it passed. The API's `GET /system/status` carries `maintenance: {active, until}`, read live rather than from the ten-minute cache.

## What does health.php answer?
- Anonymous, no session (`PG_NO_SESSION`), JSON `{"status", "cron_age"}` only: `maintenance` → 503 + Retry-After; `cron_stalled` (general job's last run older than its `stale_after`, 2 h) → 503; otherwise `ok` → 200. A general job that never ran is `ok` with `cron_age` null. A database that does not answer is init's own 503.
- A `cron_stalled` answer also runs `pg_ops_alerts_check_cron()`: the general job cannot report its own death, a monitor polling here can (once a day, `docs/memory/platform/ops-alerts.md`).
- The firewall leaves the bot policy and the country restriction out for it (`waf_is_health_request()`, by the running script, not the path): monitors are often generic clients with no user agent and run abroad, and with "block unknown bots" on every check was a 403 that read as "site down". The IP block list, attack tools and rate limits still apply. The firewall itself does not change behaviour in maintenance.

## How was it tested?
- `tests/maintenance_mode_test.php`; sandbox: anonymous page 503 with message and Retry-After, admin and a listed address 200, passed end 200, files and panel login 200; health 200/503 for both causes, no session files, stopped-job alert once; card refused `nope` and a past end. With "block unknown bots" and a country list, curl and empty agents got 200 from health.php and 403 from a page; sqlmap 403.
