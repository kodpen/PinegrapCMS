---
name: marketplace-webhook-receiver
description: marketplace_webhook.php: what calls it and what it imports, its checks and status codes, why it never calls out, the job's acceptance pass, the firewall entry.
---

## What calls it, and what does it do?
- `POST marketplace_webhook.php?account=<id>` from the marketplace's servers; functions in `includes/api/outbound/webhook_receiver.php`. The connector's `webhook_packages($account, $decoded)` reads the body (Trendyol: the order-feed page through `mp_trendyol_package()`, or one bare package); each package goes through `mp_import_order()`, so a pull meeting it later skips it.
- No network call inside the request: no acceptance, no lookup. `mp_sync_approve_pending()` (job, after a successful pull, auto-accept on) accepts map rows of the last day that are imported with an order, not accepted, still `Created` and without an error, 50 a pass.
- The account's `last_success`/`last_error` are not touched: they answer whether this site reaches the marketplace, and clearing the error would hide a refused key.

## What does it answer?
- 405 not POST; 404 no account; 403 account paused, import off, connector without `webhook_packages`, or no `webhook_id` in the credentials (a webhook removed here is refused whatever key it presents: Remove keeps the key, PR #1453); 401 key missing or wrong, or none stored (no activity-log line; the firewall's global rate limit covers guessing a 32-byte key); 413 over 4 MiB; 400 not JSON; else 200 `{"ok":true,"imported","skipped","failed"}`.
- A package that fails to import (a line no product matches) is still 200: the map row keeps the reason and "Try again" re-reads it. A non-2xx makes Trendyol retry every 5 minutes and switch the webhook off.
- The key (`mp_webhook_presented_key($server)`): `x-api-key`, else `PHP_AUTH_PW`, else Basic in `HTTP_AUTHORIZATION` / `REDIRECT_HTTP_AUTHORIZATION`; `hash_equals()` with `credentials['webhook_key']`.

## How is a webhook registered, removed, or left behind?
- See `marketplace-webhook-registration.md` (account screen, owner fields, delete, Trendyol's webhook facts).

## Why is it in the firewall's API list?
- `waf_is_api_request()` names it: no browser agent, key in a header. It skips the bot policy, the country gate and the sensitive limit; the global rate limit applies. A JSON body is not in `$_POST`, so signature scanning does not read it.

## What is unverified?
- No live delivery yet: whether a query string in the URL is taken, the exact create answer, how fast a passive webhook is reported.

Related: docs/memory/commerce/marketplace-webhook-registration.md, docs/memory/commerce/marketplace-order-import.md, docs/memory/waf/waf-bot-policy-credential-endpoints.md
