---
name: api-webhook-events
description: Announcing webhook events: pg_announce() from any screen, contact helpers, the event catalogue, no events from bulk imports, Workspace channel events, queue, signature and retries
---

## How does code announce something?
- `pg_announce('<event>', array(...))` (`includes/fn/events.php`, loaded everywhere): a screen or form never calls the API's queue itself. It hands the event to the Workspace (`pg_event_record()`, only for the names in `pg_event_workspace_events()`) and queues it for the subscribers (`api_webhook_enqueue($event, $data, true)`). Silent when the API is not installed: an event is never the reason a form fails.
- Calling `api_webhook_enqueue($event, $payload)` directly also records for the Workspace; its third argument `true` only says the caller already did.
- A model that writes under a lock or in a transaction announces after COMMIT and the lock (`pg_return_create()`); a status set before an outside call that may undo it (a refund before the payment service answers) is announced where the outcome stands, not by the move (`pg_return_refund()`, commerce/order-returns-events.md).
- A new contact: `pg_announce_contact_created($id)`, which reads the e-mail address back itself; a caller that already has it may pass it second (`includes/workspace/changes.php` does). Contacts put into or taken out of a contact group: `pg_announce_contact_group_members()`, one event per change, none when nobody moved.

## What must the event name be?
- A key of `api_webhook_events()` (`includes/api/outbound/webhooks.php`): an unknown name is dropped without a word (`api_webhook_event_is_valid()`). A module adds its own events through its seam; the core list wins a name it already has, so a module cannot redefine `order.created`.
- The catalogue text says honestly what is NOT announced ("not bulk imports", "through the API").

## What is never announced?
- Bulk paths (contact import, campaign upload, design or theme import): one listing tells a receiver more than five thousand queue rows. The catalogue line says so.

## How is it delivered?
- Queued, never sent in the request: `api_webhook_dispatch()` runs from `api_webhook_job.php` and the general `job.php`. Delivery goes through `api_http_request()` (https only, no redirects, no address inside the network), signed with `X-Pinegrap-Signature: t=<time>,v1=<HMAC-SHA256 of time.body>`.
- At most six attempts (`api_webhook_max_attempts()`), the waits 1 min, 5 min, 30 min, 2 h and 6 h (`api_webhook_backoff()`): about 8.5 hours in all. The comment there says "about a day and a half", but the sixth attempt ends the row before the 24 h wait is used. Only a network failure, 408, 429 or 5xx is retried (`api_http_should_retry()`); any other answer gives up at once. A given-up row marks the subscription `failing` (never deleted) and is kept a week for the panel.
- Subscriptions are managed only with an application key (`webhooks:manage`); a device or an OAuth connection never holds that scope.

## What about Workspace events?
- A new `workspace.*` event carries `channel_id` in its payload (or `task.channel_id`, or a discussion's `thread.channel_id`): a subscription narrowed to a channel group filters by the channel's group, and an event with no channel never reaches it (`workspace-webhook-channel-groups.md`). Exception: `workspace.channel_group.*`, announced with `ws_group_announce()` and matched by the group itself (`workspace-channel-group-events.md`). Test: `tests/webhook_channel_group_test.php`.
- Flow runs (`workspace.flow.run.*`): announced once per move, words only for a public channel's run, never recorded for the workspace (`workspace-flows-run-events.md`).
- An event meant for one application (`workspace.app.mentioned`) is queued only for that application's subscriptions (`api_webhook_event_app_id()`; `workspace-app-mentioned-webhook.md`).
