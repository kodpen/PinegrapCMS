---
name: visitor-push-app-mode
description: Visitor notifications' App mode (VP7, N17): its own switch, the manifest's standalone display, the worker's offline page for a lost page load
module: site_push
---

## Why is there an App mode?
- An iPhone delivers web push only to a site added to its home screen with the manifest's `display: standalone`; #5065 had left it at `browser`. Owner (2026-11-16, agent channel poll 73, message 5759): its own switch, starting off.

## What turns it on?
- Site Settings, Features, card "Visitor notifications", second switch "App mode" (`site_push_app_on`). The settings text `site_push_app` ('1' on, missing off), read only by `pg_site_push_app_on()`, saved only by `pg_site_push_app_save()`, on the same form as the notifications switch.
- `pg_site_push_app_mode()` = its switch and `pg_site_push_enabled()`: switching notifications off takes app mode with it.
- The manifest exists only while a Site Icon is set (`site-icon.md`): without one, app mode changes only the worker.

## What does the manifest say?
- `get_site_icon.php` passes `pg_site_push_app_display()` ('standalone' or 'browser') to `pg_site_icon_manifest()`; any other word stays `browser`. The ETag gets `-app` in app mode, so a day-cached manifest is asked again after the switch.

## What does the worker do in app mode?
- `site_push_sw.php` adds `offline` to `self.pgSitePushWorker` (`pg_site_push_worker_offline()`: `skip` = the software directory, the page's language, "You are offline", the line, "Try again"). Off, there is no `offline` and the worker has no fetch handler at all (N2).
- With it, `pg_site_push_sw.js` handles only a GET page load (`mode: navigate`) on the scope outside the software directory (`offlineFor()`): it goes to the network as is, nothing is cached; only a network failure answers the offline page (`offlinePage()`, status 503, `no-store`): the site's name, the heading, the line and a link reloading the same address. No script and no outside file on it.
- The worker is registered only when a visitor presses "Notify me", so only a subscribed device gets the offline page. The worker file is `no-cache`: the browser's update check brings a switch change.

## How is it tested?
- `tests/site_push_block_test.php` (prelude, settings, the switch's wiring, the worker's fetch handler under Node) and `tests/site_icon_test.php` (display, `get_site_icon.php` wiring).
