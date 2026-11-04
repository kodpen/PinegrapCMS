---
name: activitypub-outbox
description: ActivityPub publishing: what counts as published, the general job's sweep that queues Create/Update/Delete into ap_outbox, the log and delivery rows, signing, retries, the outbox collection.
module: activitypub_outbox
---

## What is published?
- `ap_settings.publish`: `forms` (custom form page ids) and `folders` (folder ids). Nothing chosen, nothing sent.
- A page: `page_type = 'standard'`, not the home page, directly in a chosen folder that is public (`get_access_control_type()`), not archived, not the drafts folder, not in the bin. Subfolders are not included.
- A record: complete, with a reference code, shown by a record page: the first standard page in a public folder with a `form_item_view` of that form that is open to everyone (`pg_sw_form_widget_public()`) and compares no field with the viewer; the view's filters apply (`pg_sw_record_filter_clause(..., true)`, closed when unreadable). No such page: the form's records are not published.

## How are changes found?
- A sweep in the general job (`pg_ap_outbox_sweep()`), not one hook per write path: pages and records change from the panel, the designer, the API, imports, scheduled moves and the bin, and none of them has to tell the outbox. Each object's change time (`page.page_timestamp`; a record's later of `submitted_timestamp` and `last_modified_timestamp`) is compared with its last logged activity (`pg_ap_sweep_plan()`): new or deleted before → Create, newer → Update, logged but gone → Delete. At most `PG_AP_SWEEP_LIMIT` (20) per sweep.
- `pg_announce()` of a `page.*` or `form.*` event calls `pg_ap_heard()` (only when `ap_settings` exists), which sets `config_text` `ap_outbox_due`: the next tick sweeps. Otherwise every `PG_AP_SWEEP_EVERY` (900 s, `ap_outbox_swept`). Panel record edits announce nothing: out within that interval.
- The first sweep (no `ap_outbox_since` row) logs everything already published as a Create with an empty body and delivers nothing: followers do not get the archive.

## What does the queue hold?
- Per activity one log row (`inbox = ''`, `state = 'sent'`, the activity as `body`) and one delivery row per inbox (`pg_ap_delivery_inboxes()`: shared inbox, else the follower's own, each once, https only). The log is the sweep's memory and the outbox collection; delivery rows are removed 30 days after they were sent or failed.
- Activity id `<object id>#<create|update|delete>-<time>`, read back by `pg_ap_activity_key()`. Object ids `<actor>/p/<page id>` and `<actor>/r/<record id>`: stable across renames. They do not dereference yet (no route answers them).
- `pg_ap_queue(..., false)`: delivery rows only (the inbox's Accept).

## What does an Article look like?
- `pg_ap_object_for_page()` / `pg_ap_object_for_record()` → `pg_ap_article()`: name, content, `mediaType` text/html, url, published, updated (when later), to Public, cc followers, Image attachments (a record's RSS media field when everyone may open it). Expected shape: `tests/fixtures/activitypub/blog_create_article.json`.
- No `summary`: Mastodon shows an Article's summary as a content warning. The description (page meta description; a record's first RSS description field, HTML through `pg_sanitize_rich_text()`) is the content's first paragraph, then a link to the page. Site addresses are made absolute (`pg_ap_content_html()`).
- Delete carries a Tombstone of the object's address.

## How is it delivered?
- `pg_ap_outbox_run()` in `job.php` after the mail queue: 25 due rows a tick, each claimed by moving `next_at`, signed by `pg_ap_sign()` and posted by `pg_ap_deliver()` through `pg_curl_run()` with `check_address`. 2xx sent; 4xx except 408/429 failed at once; anything else retried on the mail ladder (`pg_ap_backoff()` = `pg_mail_backoff()`), given up at the sixth attempt (`PG_AP_MAX_ATTEMPTS`). No key pair: retried until given up.

## Where are the tests?
- `tests/activitypub_outbox_test.php`: object shapes, activity keys, the sweep plan, fan-out, the ladder. The sweep, queue and delivery against a database were not run.
