---
name: activitypub-steps
description: Steps AP1-AP4 of plans/activitypub.md: actor, WebFinger and signatures, inbox and followers, publishing through the outbox queue, replies as comments; claim as [plan:activitypub] AP<n>
---

## Which run side by side?
- Wave 1: AP1 alone (question 1 first). Wave 2: AP2 and AP3 at once (inbox, outbox: different files). Wave 3: AP4, AP5, AP6 at once. Wave 4: AP7, then AP8. AP5-AP8: `activitypub-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/activitypub_<topic>_test.php` on fixtures in `tests/fixtures/activitypub/` (Mastodon's actor, Follow, Create(Note) and a signed request), memory under `platform/`, texts through `tr_add.php`.

## Which steps are there?
- AP1 (M) Actor, WebFinger, signatures. `done` (#6378); settings and keys in `config_text` rows `ap_settings` / `ap_keys` (no room on `config`).
  - Migration sub-step for the tables of question 1 (`install_create_table()`, in `get_tables()`, rerun twice). New `includes/fn/activitypub.php`: `pg_ap_enabled()`, `pg_ap_actor()` (the JSON-LD document: id, type Person or Organization by a setting, inbox, outbox, followers, publicKey, icon, the site's name and summary), `pg_ap_webfinger($resource)`, `pg_ap_nodeinfo()`, the key pair made once with the DKIM generator and sealed, `pg_ap_sign($method, $url, $body)` (HTTP Signatures: `(request-target)`, host, date, digest) and `pg_ap_verify($request)` (the remote key fetched through `pg_curl_run()` and cached in `data/cache/ap/`), `pg_ap_json($data)`; `router.php` answers `/.well-known/webfinger`, `/.well-known/nodeinfo` and `/@<handle>` with content negotiation. Tests: the documents against the fixtures, a signature round trip, a stale date refused. Memory: `platform/activitypub-actor.md`.

- AP2 (M) Inbox and followers. `done` (#6461); likes and boosts are `ap_reactions` rows (11.0.167), not counts on the records.
  - `ap_inbox.php` (root, URL-called): verifies the signature, then handles Follow (an `ap_followers` row and a queued Accept), Undo Follow, Delete (actor gone: the row removed), Like and Announce (`ap_reactions` rows), Create(Note) with `inReplyTo` one of ours (handed to AP4 through `pg_ap_reply_receive()`), anything else answered 202 and logged; an own WAF rate bucket; a blocked actor or domain refused before the signature check. Memory: `platform/activitypub-inbox.md`.

- AP3 (M) Publishing and the outbox queue. `done` (#6465); a sweep in the general job, not per-write hooks (`platform/activitypub-outbox.md`).
  - `pg_ap_object_for_record($row)` and `pg_ap_object_for_page($page)` (Article: name, summary, content through `pg_sanitize_rich_text()` with absolute addresses, attachments from the record's pictures, url, published, attributedTo); the settings choose the forms and folders; `pg_announce()` listeners on record publish, page publish, edit and unpublish queue Create, Update or Delete into `ap_outbox`, one row per follower's shared inbox, sent by the general job with the mail queue's attempts and backoff (`pg_ap_deliver()` signs and posts); the actor's `outbox` collection lists the last 20. Tests: the object shapes, the fan-out by shared inbox, the backoff. Memory: `platform/activitypub-outbox.md`.

- AP4 (M) Replies as comments. `done` (#6586); likes and boosts counts moved to AP4b (`platform/activitypub-comments.md`).
- AP4b (S) Likes and boosts in the comments widget. After AP4. `done` (#6638)
  - Thread tokens for the `ap_reactions` counts of the drawn page or record (likes, boosts) on the widget's count line, in `widgets_comments.php` and its JS list (`tools/check_bindings.php`), texts through `tr_add.php`. Memory: `platform/activitypub-comments.md` grows.
