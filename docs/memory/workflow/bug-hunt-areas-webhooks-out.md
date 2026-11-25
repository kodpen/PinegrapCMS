---
name: bug-hunt-areas-webhooks-out
description: The bug hunt in outgoing webhooks and announcements (area 41, PR #11441): what was fixed, what was read clean, what is left
---

## Which area was it?
- Area 41 "webhooks-out": `includes/api/outbound/webhooks.php` (queue, delivery, dispatcher), `includes/api/outbound/http.php` (address check, pinned address), `includes/api/resources/webhooks.php` (subscription routes), the panel's Stop / Send again (`api_settings.php`) and the chat rows of `includes/fn/notify_channels.php`. PR #11441.
- To look at: secret never logged, retries and their end, one event sent once under two cron runs, a slow receiver not blocking others, no address inside the network, payload within the key's scopes.

## What was fixed?
- One receiver that timed out used the whole dispatcher run (10 s a row, 15–20 s a run) and every other subscription waited: a subscription that failed is left for the rest of the run (`api/api-webhook-dispatch-run.md`).
- Stop pressed while a delivery was on its way was undone by that delivery's answer (`active` or `failing` written over `disabled`): the write now keeps `disabled` (same file).

## What became an issue?
- #11442: an application with `webhooks:manage` but without `orders:read` (or another read scope) subscribes to `order.created` and gets the customer data. Needs an event → scope decision, since existing subscriptions would stop.

## What was read and found clean?
- The claim of a row (`next_attempt_at` compared to the value read), held rows of a failing subscription, the backoff ladder and its end at six attempts, the week's sweep.
- Logs: given-up notes, API change, secret renewal, panel Stop/Resume all mask the address to scheme and host; the secret is answered only on create and renewal; chat errors pass through `pg_notify_mask()`.
- `api_http_check_url()`: https only, private, reserved, CGNAT, link-local and metadata blocks refused, the checked address pinned with `CURLOPT_RESOLVE`, no redirects, certificate verified. A bracketed IPv6 literal does not resolve and is refused.

## What is left?
- Send again / `POST /webhooks/{id}/retry` sets `next_attempt_at = now` on a row another run has just claimed: within those seconds a second run may send it twice. A claimed row cannot be told from a scheduled one without a column.
- Chat rows (`webhook_id = 0`) share one id, so a dead Slack or Teams target is not left out of the run.
- The comment above `api_webhook_backoff()` says "about a day and a half"; the sixth attempt ends the row after about 8.5 hours (`api/api-webhook-events.md`).
