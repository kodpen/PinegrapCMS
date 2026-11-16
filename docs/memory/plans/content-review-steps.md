---
name: content-review-steps
description: Plan content-review, steps CH1 to CH5: the table and its rules, the Content review screen, the task and the flow event, the API, close-out
---

## Which steps are there?
- CH1 (S) The table and its rules (`includes/fn/content_review.php`, the sweep in `job.php`), features V1 to V6.
  - Done: #9934 (`platform/content-review-rules.md`; columns confirmed in agent channel message 6410).

- CH2 (M) after CH1: the screen `view_content_review.php` with its list module and action file, features V7 to V13.
  - Architect (2026-11-16, agent channel message 6410): "All" and "Not set" page through the items' own tables (`forms` can be large and is never sorted whole); the person and date join from `content_review` by its primary key.
  - Done: #9967 (`platform/content-review-screen.md`, `content-review-actions.md`). Owner (2026-11-16, agent channel poll 100, message 6855): the look approved as page 6854 showed it; a visible change goes back to the owner's look.

- CH3 (M) after CH2: the task and the flow event.
  - Existing files: `includes/api/outbound/webhooks.php` (the name in `api_webhook_events()`), `job.php` (the announce for the dates that passed), the flow gallery's templates.
  - New: the announce and "Make a task" in the module, through `pg_announce()` and `ws_followup_task_open()`; the event reaches flows as `contact_group.joined` does (`workspace/contact-group-joined-event.md`).
  - Tests: one announce per item and day, none for an item gone or not set, the task's fields, the open task handed back, no button with the Workspace off.
  - Features: V14, V15, V16.
  - Architect (6410): the announce first claims the row with one UPDATE writing `announced_on = review_on` only where `announced_on` is NULL or differs, and announces only when that write changed a row; a new date (Mark reviewed, a set date) separates them again. Today goes in as `pg_content_review_today()`, never `CURDATE()`.
  - Done: #10008 with V14 and V15 (`platform/content-review-announce.md`). Architect (2026-11-16, agent channel message 7026): the gallery template (V16) moves to CH3b, after a general "assign to the person the event names" setting on flow task boxes. Owner (poll 102, message 7036): the row menu's "Make a task" stands as shown.

- CH3b (S) after CH3: the task box setting "the person the event names" (`steps.php`, `workspace_flow.js`), then the gallery template; pictures to the architect (`workflow/decisions-made-product-66.md`).

- CH4 Done (#10006): the API, `GET /content-review` and `POST /content-review/{kind}/{id}` (`api/api-content-review.md`); scope `content_review:read` / `:write` for roles 0-3 (architect, agent channel message 7025). Features: V17.

- CH5 (S) close-out: every V number checked against the code with its PR; `platform/content-review.md`; this plan's three files deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- CH3 and CH4 run beside each other once CH2 has landed.
