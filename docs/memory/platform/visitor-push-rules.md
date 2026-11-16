---
name: visitor-push-rules
description: Visitor notifications (11.0.227, #9947): the site_push tables, the switch, endpoints and keys taken, the door site_push.php, the secret, consent log, export and erasure
module: site_push
---

## What do the tables hold?
- `upgrade_2026_11_0_site_push()` (11.0.227, in `get_tables()`) adds three tables.
- `site_push_subscription`: endpoint, `endpoint_hash` unique, `p256dh`, `auth`, `service`, `secret_hash` unique, `language`, `user_id` (0 when signed out), `created_at`, `last_seen_at`, `fail_count`.
- `site_push_message`: title (60), body (200), url, `is_test`, `created_by`, `created_at`, and the queued, sent, failed and removed counts (a removed subscription is deleted, so the counts cannot be rebuilt later).
- `site_push_queue`: PK message and subscription (a batch written twice sends once), `status` (0 waiting, 1 pushed, 2 read, 3 failed), `attempts`, `next_at`, `sent_at`, `fetched_at`; KEY `idx_due`, `idx_subscription`.
- Never a row in the panel's `push_subscriptions` or `push_queue`: that queue deletes the rows of an account it cannot wake (`core/push-disabled-account.md`).
- No `contact_id` column: every `contact_id` column must be a holder in `includes/fn/contacts.php`; the account's contact is reached through `user_id`.

## What turns it on?
- The settings text `site_push` ('1' on, missing off), read only by `pg_site_push_switch_on()`, saved only by `pg_site_push_switch_save()`. `pg_site_push_enabled()` is the switch and `pg_site_push_ready()` (the 11.0.227 step has run).
- With it off, `site_push.php` answers 404 `off` to every action and the module writes and reads nothing.

## What does the door take?
- `site_push.php`, POST only, no form token (a cached page has none): `pg_consent_answer_same_origin()`, then the switch, then `pg_site_push_throttled()` (10 writes or 120 reads an hour per address), all in pure `pg_site_push_door_refusal()`.
- `action=subscribe`: an endpoint is taken only as https on port 443 on a host of `pg_site_push_services()` (FCM, Mozilla, Windows, Apple), with a 65-byte p256dh point starting 0x04 and a 16-byte auth (`pg_site_push_subscription_check()`). The answer is the secret, given once; only its sha256 is kept. The same endpoint again gets new keys and a new secret.
- `action=stop` with the secret removes the row and its queue rows. `action=message` with the secret returns the newest pushed, unread message of that subscription within a day and marks it read; the query is scoped by the secret's subscription (`pg_site_push_waiting_query()`).

## What goes to the consent log, export and erasure?
- A yes and a stop: channel `push`, purpose `news`, source `notify`. `push` is, like `cookie`, a channel an anonymous visitor may answer without a person (`pg_consent_log_row()`).
- Signed in, the row carries `user_id`: the privacy export's section "Site notifications" (no endpoint, keys or secret) and the erasure (delete) find it by the account.
