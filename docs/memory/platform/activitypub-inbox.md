---
name: activitypub-inbox
description: The ActivityPub inbox: the checks a delivery passes, what each activity does to ap_followers, ap_reactions and ap_outbox, the AP4 hooks, the log and the inbox's WAF bucket.
module: activitypub_inbox
---

## Which checks does a delivery pass?
- `ap_inbox.php` (inbox and shared inbox, no session) calls `pg_ap_inbox_receive()`: 404 while off, 405 for anything but POST, 413 above `PG_AP_INBOX_MAX_BODY` (256 KB), 400 for a body that is no activity.
- Blocks first: the activity's actor and the signature's key id against `ap_blocks` (`pg_ap_blocked()`: an actor row by its address, a domain row by the host or any subdomain), 403 before any key is fetched; the verified key's owner is checked again after.
- `pg_ap_verify()` (`platform/activitypub-actor.md`), then `pg_ap_inbox_authorized()`: the key's owner (else the key id without its fragment) must be the activity's `actor`, or 401. A refusal is written to the inbox log, `waf_log_event()` (rules `ap-inbox-blocked`, `ap-inbox-signature`, `ap-inbox-actor`) and `data/firewall.log`.
- A deleted account's key is gone with it: a Delete of the actor that does not verify is honoured only when the actor's address answers 404 or 410; otherwise it is answered 202 and kept.

## What does each activity do?
- `pg_ap_inbox_plan($activity, $site)` (pure) reads it into actions; `pg_ap_inbox_apply()` runs them. Follow of our actor: the follower's document is fetched (`pg_ap_remote_actor()`, signed GET, its `id` must match), an `ap_followers` row is upserted (inbox, shared inbox, name, avatar; `followed_at` kept) and an Accept queued for the follower's own inbox with `pg_ap_queue()`. Undo Follow removes the row.
- Like / Announce of one of our objects (under the site's base address, not the actor itself): one `ap_reactions` row, `INSERT IGNORE` on `reaction_key` = sha1 of kind, actor, object (#6391). Undo removes by activity id or the recomputed key; an Undo with a bare id by activity id.
- Delete of the actor: `pg_ap_forget_actor()` removes its follower row and reactions and calls `pg_ap_reply_actor_deleted($actor)` when it exists (AP5's block uses the same path). Delete of an object on the actor's own host: `pg_ap_reply_deleted($object_id, $actor)` when it exists.
- Create(Note) whose `inReplyTo` is ours and whose `attributedTo` is the sender: `pg_ap_reply_receive($note, $actor, $site)` when it exists (AP4 defines the three hooks); until then logged as not stored. Anything else: 202 and a log line.

## Why is a replay harmless?
- Every action is idempotent: the follower upsert, the reaction's unique key, deletes. The Accept's id follows from the Follow's (`#accepts/<sha1>`), and `pg_ap_queue()` does not queue an activity already waiting for the same inbox. A captured request outside the signature window (1 hour) is refused by `pg_ap_verify()`.

## Where is it logged and counted?
- `data/cache/ap/inbox.log`, one line per delivery (status, type, actor, what was done), moved to `inbox.log.1` above 512 KB.
- WAF: `ap_inbox.php` is in `waf_is_api_request()` (no bot policy, no sensitive allowance) and in `waf_country_api_scripts()` (servers run anywhere); its own bucket `ap_inbox`, `WAF_AP_INBOX_RATE_LIMIT` (300 per minute per `waf_ip_subject()`) in `waf_handle_rate_ap_inbox()`, a crossing logged as `rate-ap-inbox` with a burst offence. The JSON body is not in `$_POST`, so the signature scan does not read the notes' HTML.

## Which table did it add?
- `ap_reactions` (2026.11.0, 11.0.167, `upgrade_2026_11_0_ap_reactions()`): kind, actor_id, object_id, activity_id, reaction_key UNIQUE, created_at; counts are `COUNT(*)` per object and kind.
