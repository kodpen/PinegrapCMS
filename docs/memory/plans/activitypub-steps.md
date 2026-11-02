---
name: activitypub-steps
description: Steps AP1-AP4 of plans/activitypub.md: actor, WebFinger and signatures, inbox and followers, publishing through the outbox queue, replies as comments; claim as [plan:activitypub] AP<n>
---

## Which run side by side?
- Wave 1: AP1 alone (question 1 first). Wave 2: AP2 and AP3 at once (inbox, outbox: different files). Wave 3: AP4, AP5, AP6 at once. Wave 4: AP7, then AP8. AP5-AP8: `activitypub-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/activitypub_<topic>_test.php` on fixtures in `tests/fixtures/activitypub/` (Mastodon's actor, Follow, Create(Note) and a signed request), memory under `platform/`, texts through `tr_add.php`.

## Which steps are there?
- AP1 (M) Actor, WebFinger, signatures. `free` after question 1.
  - Migration sub-step for the tables of question 1 (`install_create_table()`, in `get_tables()`, rerun twice). New `includes/fn/activitypub.php`: `pg_ap_enabled()`, `pg_ap_actor()` (the JSON-LD document: id, type Person or Organization by a setting, inbox, outbox, followers, publicKey, icon, the site's name and summary), `pg_ap_webfinger($resource)`, `pg_ap_nodeinfo()`, the key pair made once with the DKIM generator and sealed, `pg_ap_sign($method, $url, $body)` (HTTP Signatures: `(request-target)`, host, date, digest) and `pg_ap_verify($request)` (the remote key fetched through `pg_curl_run()` and cached in `data/cache/ap/`), `pg_ap_json($data)`; `router.php` answers `/.well-known/webfinger`, `/.well-known/nodeinfo` and `/@<handle>` with content negotiation. Tests: the documents against the fixtures, a signature round trip, a stale date refused. Memory: `platform/activitypub-actor.md`.

- AP2 (M) Inbox and followers. After AP1.
  - `ap_inbox.php` (root, URL-called): verifies the signature, then handles Follow (an `ap_followers` row and a queued Accept), Undo Follow, Delete (actor gone: the row removed), Like and Announce (counts on the object's record), Create(Note) with `inReplyTo` one of ours (handed to AP4), anything else answered 202 and logged; an own WAF rate bucket; a blocked actor or domain (AP5) refused at the signature step. Tests: each activity from the fixtures, a replayed one. Memory: `platform/activitypub-inbox.md`.

- AP3 (M) Publishing and the outbox queue. After AP1 and question 2.
  - `pg_ap_object_for_record($row)` and `pg_ap_object_for_page($page)` (Article: name, summary, content through `pg_sanitize_rich_text()` with absolute addresses, attachments from the record's pictures, url, published, attributedTo); the settings choose the forms and folders; `pg_announce()` listeners on record publish, page publish, edit and unpublish queue Create, Update or Delete into `ap_outbox`, one row per follower's shared inbox, sent by the general job with the mail queue's attempts and backoff (`pg_ap_deliver()` signs and posts); the actor's `outbox` collection lists the last 20. Tests: the object shapes, the fan-out by shared inbox, the backoff. Memory: `platform/activitypub-outbox.md`.

- AP4 (M) Replies as comments. After AP2 and AP3.
  - A Create(Note) in reply to one of our objects becomes a `comments` row on the record or page (the item types the Comments widget knows, `designer/comments-widget-contract.md`), `remote_actor` and `remote_id` set, the sender's name and address kept, moderation as the comment settings say (`designer/comment-thread-rules.md`), notification mails as for any comment; a reply written on the site to such a comment goes out as a Note to the sender; likes and boosts shown as counts by the widget's count line. Tests: the mapping, a Delete of the remote note removing the comment. Memory: `platform/activitypub-comments.md`.
