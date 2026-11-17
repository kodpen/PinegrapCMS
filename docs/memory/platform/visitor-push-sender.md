---
name: visitor-push-sender
description: Visitor notifications' sender (#9957): the message writer, queue batches, the pass in job.php and push_job.php under one lock, held services, retries, a day's limit, the week's tidy-up, the counts
module: site_push
---

## How is a message written?
- `pg_site_push_message_send($input, $kind, $created_by, $subscription_id)`: nothing while the switch is off (`off`). Kind 0 (news) queues every subscription, read by id in pages and written by `INSERT IGNORE` in batches of 500; kind 1 (a test) queues the one subscription named. Kinds 2 and 3 are written by VP5 and VP6.
- `pg_site_push_message_check()`: title 1 to 60 characters, text at most 200, control characters dropped. The link goes through `pg_site_push_url_path()`: a path, or an address on `HOSTNAME_SETTING` / `HOSTNAME`, stored as the path without the host; empty is `/`; `//host`, a backslash or another host is refused.
- `queued_count` is the number of rows the batches wrote.

## How is it sent?
- `pg_site_push_queue_run()` runs from `job.php` (its own block, only while the visitor switch is on: the device notifications job is opt-in and separate) and from `push_job.php`. One run at a time: `pg_cron_claim('site_push_send', 120)`, released at the end and on shutdown.
- Each due row (status 0, `next_at` passed, at most 100 a pass) gets an empty push signed with the panel's VAPID key (`pg_site_push_deliver()`, `api_http_request()` with the webhook agent, `TTL` a day). The worker then fetches the text with its secret.
- `pg_site_push_send_plan()` decides from `pg_push_outcome()`: accepted, the row is 1 at once and `sent_count` rises; 404/410, the subscription and its rows are deleted and `removed_count` rises; 429, every waiting row of that service waits for Retry-After and the pass skips the service; another failure waits 1 min, 5 min, 30 min, 2 h, and the fifth try gives up (3, `failed_count`).

## What is tidied?
- At the start of each pass (`pg_site_push_queue_tidy()`): waiting rows of a message older than a day are given up and counted as failed; rows no longer waiting whose message is older than 7 days are deleted. The queue is no history; the counts stay on the message (architect, agent channel message 6528).

## Is the pass's size a limit on the audience?
- No. Architect (2026-11-17, agent channel message 8731): N10 sends news to every subscriber and N11's day is for a device its push service cannot reach. One page of 100 rows, one request at a time, inside a budget of 10 s (`job.php`) or 20 s (`push_job.php`) is VP2's guard for the job's time.
- At 100 a minute news to 20,000 subscribers takes over 3 hours, and above about 144,000 the rest is given up after the day: bug #10251.
- The fix's shape: the pass reads page after page until its budget ends and sends 20 at a time through a new `api_http_request_many()` beside `api_http_request()` in `includes/api/outbound/http.php` (allowed beside a holder of that file). The lock, the two budgets, the order, the retry plan and the day stay; a 429 holds its service from the next group on.
