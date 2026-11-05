---
name: cache-control-shared-caches
description: Which answers may carry a Cache-Control a CDN or proxy keeps; pg_cache_control_shared_safe() and the guard test over literal header('Cache-Control: …') lines
---

## What keeps a per-visitor answer out of a CDN?
- Every script that starts a session gets PHP's nocache limiter (`no-store, no-cache, must-revalidate`); panel screens turn it into `private, no-cache` (`pg_panel_cache_headers()`). A script that sends its own `Cache-Control` replaces that and must keep `no-store` or a bare `private` in it.
- `no-cache` or `max-age=0` alone still let a shared cache store the body; `private="Set-Cookie"` leaves the body shareable. `pg_cache_control_shared_safe($value)` (`includes/http_range.php`, loaded by `functions.php` and `get_file.php`) answers this.

## What does the guard test check?
- `tests/cache_control_shared_test.php` reads every literal `header('Cache-Control: …')` under `pinegrap/` (libraries and `install/` aside) and fails on one that is not shared-safe. A line sent with `replace = false` is not judged alone.
- Allowed public answers, the same for every visitor: `get_site_icon.php`, `manifest_icon.php`, `connect_client.php`, `get_sitemap.xml.php` (its stylesheet). `get_file.php` decides per file and is left out. A computed value (page cache, API) is not seen by the scan.

## What did it fix?
- `edit_file.php` sent its screen as `no-cache, must-revalidate` (no `private`, CSRF token inside); it now goes out as every panel screen. `export_products_f.php` sent the product workbook as `max-age=0`; now `private, no-store`.

## What was checked and is fine?
- Session-less entry points (`PG_NO_SESSION`): API `no-store`, discovery `private`, OAuth, MCP, feeds, Workspace iCal and guests `private`, prototype and approval `no-store, private`; icons and manifest public by design. Downloads that add only `Pragma`/`Expires: 0` keep the session's `no-store`. Front-end pages: `page-cache-gate.md`.

Related: page-cache-gate.md, chat-attachment-access.md
