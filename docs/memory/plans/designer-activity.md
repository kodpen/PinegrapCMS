---
name: designer-activity
description: Plan (owner 2026-11-24, level 3): an Activity tab in the designer's History: who published, restored, imported or shared what in a design, from rows that exist; steps AC1 to AC4
priority: 5
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: a team or an agency sees what happened to a design, and who did it.

## What is there today?
- History lists one page's versions, each with the person, the day and how it came (published, restored, imported, by the assistant) (`designer/page-revisions.md`). What happened across a design is in no one list, and a shared component's save leaves no version.

## What is built?
- "Activity" as a tab of the History panel beside the page's versions: the design's events, newest first, grouped by day.
- A row: the person, the hour, one sentence ("published Home", "restored Prices to the version of 12 March", "imported 4 pages", "made a share link", "the customer approved the prototype"), and for a page's version how many elements changed.
- A row of a page's version opens its Compare. Three choosers above the list: person, page, kind.
- It is read from rows the product already keeps: page versions, share links and their answers, content changes applied. Fifty at a time, more as the list is scrolled.

## What must it keep?
- No new table or column in this plan. AC1 lists what leaves no row today (a shared component's save, a theme or variables save, a page deleted) and brings it to the architect as one question; a store for those is the owner's to decide, and the tab ships without them.
- The designer roles see the design's whole list; a content editor sees the rows of the pages that are theirs.
- A person erased from the site shows as the panel shows an erased user; the list keeps no name of its own.
- Read only when the tab is opened.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- AC1 (S) Census, no product code, written to `designer/`: every writer that changes a design and the row it leaves or does not; the question for the architect.
- AC2 (M) after AC1: the reader: events from the rows that exist, their sentences, pages of fifty, rights; tests.
- AC3 (M) after AC2, CL1 of the compare plan and DD21: the Activity tab, its choosers, the row that opens Compare.
- AC4 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- The drawing: `designer-drawings-levels.md`. Claim as `[plan:designer-activity] <step> — <topic>`.
