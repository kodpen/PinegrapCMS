---
name: visitor-push-block
description: Visitor notifications on the site (VP3, #10288): the Features switch, the "Notify me" block, its script pg_site_push.js, the worker site_push_sw.php, where the secret is kept for other forms
module: site_push_block
---

## What turns it on?
- Site Settings, Features, card "Visitor notifications" (`pgset-site-push`), shown once the 11.0.227 step has run; through the switch's one saver and reader (`visitor-push-rules.md`). Off on every site.

## How does the block reach a page?
- Palette, Forms & Access, "Newsletter: Notify Me" (`newsletter-notify`, family file `style_designer_blocks_push.js`, no rules of its own). A section marked `data-pg-push` and `hidden`, holding `data-pg-push-ask` (button `data-pg-push-on`), `data-pg-push-done` (button `data-pg-push-stop`), `data-pg-push-blocked` and `data-pg-push-failed`, the last three `hidden`. Its texts are edited on the canvas.
- `get_page_content.php` loads `includes/fn/site_push_block.php` only for a page holding `data-pg-push` (not in an e-mail): `pg_site_push_block_inject()` adds `window.pgSitePushConfig` (door, worker, scope = the site's path, public VAPID key) and `assets/js/pg_site_push.js` before the last `</body>`, once, and only while `pg_site_push_enabled()` and a key exist. Switched off, the block stays hidden: it draws nothing.
- The Preview's `_SD_PAGE_SCRIPTS` row uses `pg_site_push_block_pattern()`'s expression (`tests/designer_page_scripts_test.php`). The Preview has no settings, so it shows what a browser that cannot subscribe shows: nothing.
- The canvas draws the root (`[data-pg-push][hidden]` rule in `IFRAME_CSS`) and the palette sketch draws it too; the other states stay hidden there.

## What does the script do?
- Shows the root only with a secure page, `serviceWorker`, `PushManager`, `Notification`, IndexedDB and a key: an iPhone's Safari tab has no `PushManager` (app mode is VP7).
- States (`data-pg-push-state`): off (ask), on (permission granted, a browser subscription and a stored secret), blocked (permission denied: the hint), none. A failed try shows the failed line beside ask.
- No worker before the press. The press asks the browser's question, registers `site_push_sw.php` with the site's path as scope, subscribes (`userVisibleOnly`, the VAPID key; a subscription made with another key is replaced), posts `subscribe` with the keys as base64url and `<html lang>` without its `-x-` tail. The secret is stored in IndexedDB `pg-site-push`, store `kv`, key `secret`. A door refusal unsubscribes the browser again.
- Stop posts `stop` with the secret, unsubscribes, unregisters the worker and forgets the secret. Focus moves to Stop after a yes and back to the button after Stop.

## How do other forms name this device?
- `window.pgSitePush.secret()` answers a Promise of the stored secret ('' without one), on any page the script runs on. The back-in-stock form (`push_secret`, `visitor-push-stock.md`) and the order's button (VP6b) read it there.

## What does the worker do?
- `site_push_sw.php` (no session) answers `assets/js/pg_site_push_sw.js` after `self.pgSitePushWorker` (door, `TITLE`, the fixed text "Open the site to see what is new.", the site icon's `apple-touch-icon.png` when one is set), with `Service-Worker-Allowed: PATH` and `Cache-Control: private, no-cache`. It is answered while the switch is off too.
- Handlers: install (skipWaiting), push, notificationclick; no fetch handler. A push posts `message` with the secret and shows title, body and the link; with no secret or no message it shows the fixed notice named after the site (tag `pg-site-push`).
- A click opens the link resolved on the scope; another origin or a path outside the scope opens the scope. An open tab of the site is navigated and focused, else a new window opens.

## How is it tested?
- `tests/site_push_block_test.php`: the inject, the worker's line, and under Node (`tests/fixtures/site_push/fake.js`) the states, the press, Stop, a refusal, a no, the worker's notices and click targets; the family, the card.
