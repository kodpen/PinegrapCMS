---
name: content-review
description: Content review start file: a person and a "review by" date for a page or collection record, the Content review screen, what binds it, which file holds what, what is left out
module: content_review, content_review_list
---

## What is it?
- A page outside the Recycle Bin or a record of a form used as a collection can keep a person (a panel user who may edit it), a review date, how often it comes round (3, 6, 12 months or not at all) and when and by whom it was last marked reviewed. The Content review screen (Pages panel) lists what is overdue, due soon, nobody's or not set.
- When a date passes the general job announces `content.review_due`; what follows is a flow's (the gallery flow "A page is due for review" gives a task to the item's person).

## Which file holds what?
- The table, which items count, date states, the writes, the person, the daily sweep: `content-review-rules.md` (#9934).
- The screen, its list branches, filters, columns: `content-review-screen.md`; its row menu, selection bar and dialogs: `content-review-actions.md` (#9967).
- The event, its claim, where it is named, "Make a task": `content-review-announce.md` (#10008); the task box's "The person the event names" and the gallery flow: `workspace/workspace-flows-task-event-person.md` (#10077).
- `GET /content-review`, `POST /content-review/{kind}/{id}`, the scopes: `api/api-content-review.md` (#10006).

## What binds it?
- No column on `page` or `forms`: one table keyed by item kind and id, set only from the screen and the API, so no page screen, record screen or delete path changes (`forms` is on every site and can be large, #3746, `designer/collections-decisions.md`).
- The SEO and accessibility engines keep their one screen, Search and audits (`workflow/decisions-made-product-18.md`): the list reads the scores the site keeps, links that screen and runs no engine.
- Nothing is scheduled outside the flow canvas (`workflow/decisions-made-product-41.md`): the job only announces the event.
- Owner (2026-11-16): the person is any panel user who may edit the item (poll 68); no bell line to managers when dates pass in the first version, the event and a flow cover it (poll 69). `workflow/decisions-made-product-59.md`.
- Owner (2026-11-16): the screen's look stands as #9967 built it (poll 100, message 6855; `decisions-made-product-65.md`) and the row menu's "Make a task" as #10008 built it (poll 102, message 7034; `decisions-made-product-66.md`). A visible change on the screen goes back to the owner's look.
- Architect (2026-11-16, agent channel message 6410): the table's columns; All and Not set page through the items' own tables, never sorting `forms` whole; the announce claims a row before it announces; today is `pg_content_review_today()`, never `CURDATE()`.

## Was every feature built?
- Checked at close-out against the code: the items and what they keep, the states, Mark reviewed, the person rule, the sweep (#9934); the screen, its columns, filters, actions and roles (#9967); the task and the event (#10008); the gallery flow (#10077); the API (#10006).
- All and Not set sort only by Review by; name and views sort only in the filters read from `content_review` (architect's paging rule above).
- Open: a row's menu links Page Speed (`visitors.php#speed`) and Search and audits as whole screens; neither has a per-page anchor yet, so the page's own line is not opened. A later step adds the anchors.

## What is left out?
- Scores for records; a "not edited for a year" filter (`page.page_timestamp` moves on bulk edits, a style preview and the Recycle Bin, so classic pages have no reliable edit time); the person shown on the page's own screens; a dashboard card; speed as a column (its p75 is worked out outside SQL). Each is a later yes from the owner.
