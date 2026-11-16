---
name: content-review-announce
description: Content review's event and task (CH3, #10008): content.review_due once per item and date from the general job, the claim, where the event is named, Make a task through ws_followup_task_open()
module: content_review
---

## When is content.review_due announced?
- `job.php` calls `pg_content_review_announce_due()` every run: the rows whose `review_on` is before today (`pg_content_review_today()`, bound, never `CURDATE()`) and whose `announced_on` is empty or another date, oldest first, 200 a run (`PG_CONTENT_REVIEW_ANNOUNCE_LIMIT`).
- Each row is claimed first with one UPDATE writing `announced_on = review_on` only where it is empty or differs (`pg_content_review_claim_sql()`); only a claim that changed the row announces (architect, agent channel message 6410). Two runs at once announce an item once.
- A new date (Mark reviewed, Set review date) differs from `announced_on`, so the item is announced again when that date passes.
- An item gone or no longer counting (binned page, form no longer a collection) is claimed but not announced, so it is not read every run. A row without a date is never touched. Pure run: `pg_content_review_announce_run()`.

## What does it carry, and where is it named?
- Payload (`pg_content_review_due_payload()`): `id`, `kind` (page, record), `name`, `user_id` and `person` (0 and '' for nobody's), `review_on`.
- Through `pg_announce()`: listed in `api_webhook_events()` (a webhook carries it) and `pg_event_workspace_events()` (a flow starts from it).
- Flow start: `ws_events_catalog()` (with `pg_content_review_ready()`, right `member`), record page or the record as a `form` (`ws_events_record_of()`), checked against the items the flow's creator may edit by content review's own rule (`ws_events_creator_sees()`, `pg_content_review_user_may_edit()`); outputs in `ws_flow_event_outputs()`; the flows quick setup's Content area; the parity table. Not written into watching channels.

## What does Make a task do?
- The row menu's "Make a task" ("Göreve dönüştür"), for roles under 3, only with the Workspace on and the item's person a member of its team (`pg_content_review_task_offered()`, pure; the batch reads membership with `ws_rights()`).
- `content_review_action.php` (`make_task`, one row) calls `pg_content_review_task_open()`: `ws_followup_task_open()` as whoever pressed, `$once`, with `pg_content_review_task_input()`: title "Review {name}", the page or form tagged, the person assigned, no channel, due on the review date (today without one), no reminder when the date is here or passed. Pressed again while that task is open, it hands the open one back; the notice links `workspace_tasks.php?task=`.
- The Workspace's files load on demand: `pg_content_review_workspace()`.

## Tested?
- `tests/content_review_announce_test.php`: once per item and date across days, a new date announced again, none for gone or undated, the claim statement, payload, places, the task's fields, when the button shows.
- Sandbox: two passed dates announced once into `ws_events_in`, none on a second run; Make a task made the task, a second press handed it back.
