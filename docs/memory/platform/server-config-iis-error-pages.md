---
name: server-config-iis-error-pages
description: IIS httpErrors rule (Custom + PassThrough): why IIS replaced the site's 404 page and API JSON errors with its own page and showed the detailed page with the folder path
---

## What does the rule write?
- IIS only: `<httpErrors errorMode="Custom" existingResponse="PassThrough" />` inside `<system.webServer>`, block `errors` ("Software error pages", `recommended`) in `pg_server_config_blocks('iis')`, the default skeleton and the repository's root `web.config`.
- Apache and nginx pass PHP's answer through as it is and get nothing.

## Why was the site's 404 page replaced?
- IIS defaults to `existingResponse="Auto"`, which replaces any error answer whose handler did not set the SetStatus flag; PHP's FastCGI handler never sets it. A visitor IIS does not count as local got IIS's bare "404 - File or directory not found." instead of the site's 404 page, the firewall's 403/429 pages, the maintenance and database pages, panel error screens and the API's JSON errors.
- `PassThrough` keeps what PHP sent. A PHP error answer with an empty body may now arrive empty instead of as IIS's page.

## Why did a visitor see the folder path?
- IIS defaults to `errorMode="DetailedLocalOnly"`. A request that comes through a reverse proxy or tunnel on the same machine counts as local, so everything IIS refuses before PHP runs (a `+` in a path is 404.11, a hidden segment, a denied extension) showed the detailed page: physical path, handler, logon method.
- `Custom` gives those the server's generic page for every request. Setting only `errorMode="Custom"` without `PassThrough` would turn the site's own pages into IIS's bare pages for those proxied requests too.

## How does an installed site get it?
- An update never writes the web-root file. System Status lists "Software error pages" as missing (yellow) until an administrator presses Fix (`server-config-web-root-rules.md`).
- Any `<httpErrors>` already in the file (a hosting panel's custom pages, say) counts as present and is not touched: a second element is a 500.19 for the whole site. Such a site keeps its own behaviour.

## What was not verified?
- No IIS ran in CI or the sandbox; the behaviour follows Microsoft's `httpErrors` reference. `errorMode` and `existingResponse` are not locked at server level by default; a host that locks `<httpErrors>` answers 500.19 after Fix (the backup is in `data/temp/server_config/`).

Tests: `tests/server_config_error_pages_test.php`.
Related: docs/memory/platform/server-config-web-root-rules.md
