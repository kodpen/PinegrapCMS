---
name: system-widget-test-pages
description: System widget test URLs (/c /d /e /b/<slug>), the two dev.pinegrap.com backends, legacy page confusion, saved-cart link, stale-output causes, drawing a widget without saving (preview_widgets).
---

## Which URLs are the system widget pages?
- `/c`: shopping_cart.
- `/d`: express_order.
- `/e`: order_view.
- `/b/<slug>`: catalog_item_view.

## Why can the same host name show different results?
- Two backends sit behind the name `dev.pinegrap.com`. On the user's machine the hosts file sends it to local IIS (`insider` DB: `/c` `/d` `/e` do NOT exist, 404). From the sandbox the same name resolves via public DNS to the remote copy behind Cloudflare (its DB has them).
- The code folder is shared via OneDrive, the databases are separate. phpMyAdmin (`localhost:8081`) shows the local DB. Know which side you test on; the internal browser = local.

## Which pages are legacy and should not be used for widget checks?
- `/cart`, `/express-order`, `/checkout-*` are LEGACY pages; system widget changes do not show there. Looking for a cart change in legacy `/cart` wastes time.

## How do I test with a filled cart?
- `/c?r=<reference_code>`: a saved-cart link restores the cart even without a session cookie.

## A change does not show on the page: what to check, in order?
1. Is a SAVED tree in use? New nodes enter only after the default layout is kept (Layout Template: Default, Try, Keep).
2. opcache: a file change can take ~1 minute to reach the server.

## How is a widget that no page places drawn on a running site?
- Without saving a design (#8393): from a signed-in panel tab POST `api.php` with `action` `designer`, `sub_action` `preview_widgets`, the token, `page_id` 0 and `widgets` `[{id: <an unused number>, tree_json, system_region_config}]`. `html[<id>]` in the answer is the widget as a signed-out visitor sees it (`designer-live-preview.md`).
- Page 0 matters: with a page, a renderer that keeps a page row writes it (the order form's `_pg_of_page_settings()`).

Related: docs/memory/designer/system-widget-change-checklist.md
