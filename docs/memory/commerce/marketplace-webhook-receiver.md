---
name: marketplace-webhook-receiver
description: marketplace_webhook.php: how an order webhook is registered, checked and imported, its status codes, why it never calls out, the job's acceptance pass, the firewall entry; Trendyol webhook facts.
---

## What calls it, and what does it do?
- `POST marketplace_webhook.php?account=<id>` from the marketplace's servers; functions in `includes/api/outbound/webhook_receiver.php`. The connector's `webhook_packages($account, $decoded)` reads the body (Trendyol: the order-feed page through `mp_trendyol_package()`, or one bare package); each package goes through `mp_import_order()`, so a pull meeting it later skips it.
- No network call inside the request: no acceptance, no lookup. `mp_sync_approve_pending()` (job, after a successful pull, auto-accept on) accepts map rows of the last day that are imported with an order, not accepted, still `Created` and without an error, 50 a pass.
- The account's `last_success`/`last_error` are not touched: they answer whether this site reaches the marketplace, and clearing the error would hide a refused key.

## What does it answer?
- 405 not POST; 404 no account; 403 account paused, import off, or connector without `webhook_packages`; 401 key missing or wrong, or none stored (no activity-log line; the firewall's global rate limit covers guessing a 32-byte key); 413 over 4 MiB; 400 not JSON; else 200 `{"ok":true,"imported","skipped","failed"}`.
- A package that fails to import (a line no product matches) is still 200: the map row keeps the reason and "Try again" re-reads it. A non-2xx makes Trendyol retry every 5 minutes and switch the webhook off.
- The key (`mp_webhook_presented_key($server)`): `x-api-key`, else `PHP_AUTH_PW`, else Basic in `HTTP_AUTHORIZATION` / `REDIRECT_HTTP_AUTHORIZATION`; `hash_equals()` with `credentials['webhook_key']`.

## How is it registered and removed?
- From the account screen (`mp_action=webhook_register` / `webhook_remove`), by the operator. The key is the stored one or a new `pg_short_link_new_token()`, kept with `webhook_id` in the encrypted credentials blob and never drawn. Refused while `webhook_id` is set and unless the account is on and imports orders (`mp_webhook_register_problem()`). Removing drops `webhook_id`, keeps the key.
- Save merges the stored blob under the form's fields; before, the first Save wiped `webhook_key`/`webhook_id`.
- Paused or import off with a webhook registered: deliveries get 403 and Trendyol switches it off. Save warns, and the block with Remove stays drawn.

## What are Trendyol's webhook facts?
- Create `POST /integration/webhook/sellers/{sellerId}/webhooks` `{url, authenticationType:"API_KEY", apiKey, subscribedStatuses:[]}` (empty = every status) → `{id}`; delete `DELETE …/webhooks/{id}`, 404 read as gone. Delivery: POST JSON, an order-package page, key in `x-api-key` (or Basic for `BASIC_AUTHENTICATION`), no signature, no stock or product events.
- https only; the URL must not contain "Trendyol", "Dolap" or "Localhost"; 15 per seller (passive ones count); retried every 5 minutes, passive after 13 errors.

## Why is it in the firewall's API list?
- `waf_is_api_request()` names it: no browser agent, key in a header. It skips the bot policy, the country gate and the sensitive limit; the global rate limit applies. A JSON body is not in `$_POST`, so signature scanning does not read it.

## What is unverified?
- No live delivery yet: whether a query string in the URL is taken, the exact create answer, how fast a passive webhook is reported.

Related: docs/memory/commerce/trendyol-orders.md, docs/memory/commerce/marketplace-order-import.md, docs/memory/waf/waf-bot-policy-credential-endpoints.md
