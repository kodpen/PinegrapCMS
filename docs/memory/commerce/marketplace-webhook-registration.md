---
name: marketplace-webhook-registration
description: How a marketplace order webhook is registered and removed from the account screen, which saves are refused while one exists, what account delete does with it, and Trendyol's webhook facts.
---

## How is it registered and removed?
- From the account screen (`mp_action=webhook_register` / `webhook_remove`), by the operator. The key is the stored one or a new `pg_short_link_new_token()`, kept with `webhook_id` in the encrypted credentials blob and never drawn. Refused while `webhook_id` is set and unless the account is on and imports orders (`mp_webhook_register_problem()`). Removing drops `webhook_id`, keeps the key.
- Paused or import off with a webhook registered: deliveries get 403 and Trendyol switches it off. Save warns, and the block with Remove stays drawn.

## Which saves are refused while a webhook is registered?
- Save merges the stored blob under the form's fields; before, the first Save wiped `webhook_key`/`webhook_id`.
- A Save that changes the provider's `webhook_owner_fields` (Trendyol: `seller_id`, `environment`) is refused and nothing is written (`mp_webhook_owner_changed()`, PR #1453): the webhook stays the old seller's or environment's and keeps delivering with an accepted key, so test packages were importable as real orders. A new API key or secret is allowed.

## What happens on account delete?
- A registered webhook is removed from the marketplace first (404 = already gone). If that fails the account is not deleted: the screen shows the reason and sends the operator back. Left behind, it would deliver to a 404 for ever, Trendyol would mail the seller, and it counts toward their 15.
- An account whose credentials no longer work could then never be deleted, so the drawer has a box (`webhook_force`, drawn only while `webhook_id` is set): ticked, a failed removal writes one activity-log line naming the webhook id left behind, the account is deleted and the screen says to remove the webhook in the seller panel (PR #1453).

## What are Trendyol's webhook facts?
- Create `POST /integration/webhook/sellers/{sellerId}/webhooks` `{url, authenticationType:"API_KEY", apiKey, subscribedStatuses:[]}` (empty = every status) → `{id}`; delete `DELETE …/webhooks/{id}`, 404 read as gone. Delivery: POST JSON, an order-package page, key in `x-api-key` (or Basic for `BASIC_AUTHENTICATION`), no signature, no stock or product events.
- https only; the URL must not contain "Trendyol", "Dolap" or "Localhost"; 15 per seller (passive ones count); retried every 5 minutes, passive after 13 errors.

Related: docs/memory/commerce/marketplace-webhook-receiver.md, docs/memory/commerce/trendyol-orders.md
