---
name: visitor-push-sender
description: Visitor notifications' sender (#9957): message writer, queue batches, the pass under one lock, held services, retries, refused subscriptions removed, a day's limit, tidy-up, counts
module: site_push
---

## How is a message written?
- `pg_site_push_message_send($input, $kind, $created_by, $subscription_id)`: nothing while the switch is off (`off`). Kind 0 (news) queues every subscription, read by id in pages and written by `INSERT IGNORE` in batches of 500; kind 1 (a test) queues the one subscription named. Kinds 2 and 3 are written by VP5 and VP6.
- `pg_site_push_message_check()`: title 1 to 60 characters, text at most 200, control characters dropped. The link goes through `pg_site_push_url_path()`: a path, or an address on `HOSTNAME_SETTING` / `HOSTNAME`, stored as the path without the host; empty is `/`; `//host`, a backslash or another host is refused.
- `queued_count` is the number of rows the batches wrote.

## How is it sent?
- `pg_site_push_queue_run()` runs from `job.php` (its own block, only while the visitor switch is on: the device notifications job is opt-in and separate) and from `push_job.php`. One run at a time: `pg_cron_claim('site_push_send', 120)`, released at the end and on shutdown.
- The pass reads due rows (status 0, `next_at` passed) page after page, 100 a page (`pg_site_push_page_sql()`, after the last row of the page before), until its budget ends: 10 s from `job.php`, 20 s from `push_job.php`.
- A page goes out 20 pushes at a time (`PG_SITE_PUSH_GROUP`, `pg_site_push_groups()`), each an empty push signed with the panel's VAPID key (`pg_site_push_deliver_many()`, `api_http_request_many()` with the webhook agent, `TTL` a day). The worker then fetches the text with its secret.
- `pg_site_push_send_plan()` decides from `pg_push_outcome()`: accepted, the row is 1 at once and `sent_count` rises; 404/410, the subscription and its rows are deleted and `removed_count` rises; 429, every waiting row of that service waits for Retry-After and the later groups of the pass skip it (`pg_site_push_group_due()`; rows already on their way are judged by their own answers); another failure waits 1 min, 5 min, 30 min, 2 h, and the fifth try gives up (3, `failed_count`).

## When is a subscription its service keeps refusing removed?
- `fail_count` counts refusals since the last accepted push: a 4xx other than 404, 408, 410 and 429 (`pg_site_push_refused()`: Apple's BadDeviceToken 400, a 403 for another VAPID key, an unknown path). A 5xx or no answer is an outage and leaves it; an accepted push or a new subscribe sets it to 0.
- `pg_site_push_failing()`: at 15 refusals (`PG_SITE_PUSH_REFUSALS`, three messages' tries) and no message fetched for 7 days (`PG_SITE_PUSH_UNSEEN`, `last_seen_at`), the refusing try deletes the subscription like a gone one (`pg_site_push_subscription_drop()`: queue rows, row, waits) and counts `removed_count`. The week covers a wrong clock or a passing fault that refuses everyone. Bug #10248; before it nothing read `fail_count`.
- `pg_push_outcome()` stays: the panel's own queue shares it.

## What is tidied?
- At the start of each pass (`pg_site_push_queue_tidy()`): waiting rows of a message older than a day are given up and counted as failed; rows no longer waiting whose message is older than 7 days are deleted. The queue is no history; the counts stay on the message (architect, agent channel message 6528).

## Is the pass's size a limit on the audience?
- No. Architect (2026-11-17, agent channel message 8731): N10 sends news to every subscriber and N11's day is for a device its push service cannot reach. The page of 100 is a read size, not a cap: bug #10251 had one page of one push at a time, so news to 20,000 took over 3 hours and above about 144,000 the rest was given up after the day.
- `api_http_request_many()` (`includes/api/outbound/http.php`) builds each handle with `api_http_curl_handle()`, the one `api_http_request()` uses: same address check and pin, agent, timeout. Without `curl_multi` the requests go one after another.
