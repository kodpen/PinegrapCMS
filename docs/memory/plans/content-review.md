---
name: content-review
description: Plan (owner 2026-11-16, agent channel message 5667): a person and a "review by" date for a page or collection record, and the Content review screen of what is due; steps CH1 to CH5
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. It was item 7 of agent channel message 5657 ("content health"), narrowed in message 5709.
- A site's content gets old without anybody noticing. Each page and collection record can have a person and a date by which it is read again; one screen lists what is overdue, due soon or nobody's.

## What is there today?
- The Pages list (`view_pages.php`) already shows a page's SEO score, Impact (views of 30 days) and Last Modified with the user, and has SEO views. Search and audits is the one screen of the SEO and accessibility engines (`workflow/decisions-made-product-18.md`). Page Speed is a section of Visitors.
- No page or record has a person or a review date. Collection records have no SEO score, no accessibility findings and no speed figures.
- `page.page_timestamp` is not "last edited": bulk edits, a style preview and the Recycle Bin move it. A designer page's publishes are its `page_revisions`.
- A Workspace task for a record is opened by `ws_followup_task_open()`. Scheduled work is set up only on the flow canvas (`workflow/decisions-made-product-41.md`).

## What is built?
- The numbered features: `content-review-features.md` (V1 to V17). Steps: `content-review-steps.md`. Claim as `[plan:content-review] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: one small table keyed by item kind and id, set only from a new screen, so no page screen, record screen or delete path changes. The screen reads the scores the site already keeps and runs nothing. What happens when a date passes is a flow's, started by one event.

## What binds it?
- No column on `page` or `forms` (`forms` is on every site and can be large: the reason given in #3746, `designer/collections-decisions.md`).
- `workflow/decisions-made-product-18.md`: the engines keep their one screen; this one links it and repeats no finding.
- `workflow/decisions-made-product-41.md`: nothing is scheduled outside the flow canvas; the job only announces the event (V15).

## What does the owner decide?
- Who can be the person: any panel user who may edit the item (the proposal, V5), or Workspace members only.
- Whether managers get a bell line when dates pass (the proposal: not in the first version; the flow covers it).

## What does the architect decide?
- The table's columns, in CH1's draft PR. The scope the API routes ask for.

## What is left out?
- Scores for records; a "not edited for a year" filter (no reliable edit time exists for classic pages); the person shown on the page's own screens; a dashboard card; speed as a column (its p75 is worked out outside SQL). Each is a later yes.

## Whose look?
- The Content review screen is new: the owner's, close-up pairs from CH2.
