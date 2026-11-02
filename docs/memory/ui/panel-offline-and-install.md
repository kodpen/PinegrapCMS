---
name: panel-offline-and-install
description: The panel's offline notice (sw.js answers offline.html when a page load fails), the worker registered on every panel page, "Install Pinegrap" in the account menu; the site manifest waits on #5065
---

## What does the worker cache?
- Only `offline.html`, a fixed notice with no operator data. No panel page is cached: pages are built per request and per permission, and a cached one would show one operator another's screen.
- Install keeps it in `pg-offline-<v>` (`v` = sw.js's modification time from the registration address); activate drops older `pg-offline-*` caches. If the notice cannot be fetched, install still succeeds, because push must not depend on it.

## When is the notice answered?
- Only for a page load that reads (`mode: navigate`, GET). Other requests, form posts included, never pass through the worker.
- The page comes from the network (navigation preload when the browser has it). The notice is answered only when that fetch throws a network error, never for an error status from the server. It is sent with status 503 and `no-store`.
- Its `{{lang}}`, `{{title}}`, `{{body}}`, `{{retry}}` marks are filled from the texts in the registration address (`offline_title`, `offline_body`, `offline_retry`, `lang`, printed in `#pg-push-config` by `output_header()`, through `lang()`), escaped. English defaults are in `sw.js`. Colours are the panel tokens written out, light and dark, because the stylesheet is not reachable offline.

## Where is the worker registered?
- `pg_sw_register()` in `backend.src.js`, on every panel page with `#pg-push-config`, in a secure context. Before SF7 it was registered only where push was offered.
- Push (`pg_push_init()`) uses the same promise. There must be one address per scope: a second address would replace the worker.

## How does the install hint work?
- `#pg_install_app` in `pg_account_menu()` starts `d-none`. It reads "Install Pinegrap", or "Install as an app" under a private label.
- `beforeinstallprompt` is listened for as the script loads: the event can come before the page is ready. Its default is prevented, so the row offers it instead of the browser's bar, and the row is shown. A click calls `prompt()` once and hides the row; `appinstalled` hides it as well.
- Only Chromium browsers fire `beforeinstallprompt`. Elsewhere the row never shows; iOS keeps the bell's "Add to home screen" hint.
- The panel's manifest (`manifest.php`) and its icons were already in place.

## What about the site's manifest?
- Not built yet. The switch needs a `config` column, and that schema question is architect issue #5065.
