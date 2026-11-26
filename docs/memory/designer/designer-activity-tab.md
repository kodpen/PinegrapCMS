---
name: designer-activity-tab
description: History's Activity tab as built (AC3, #11715): the two tabs, the part activity, the rows and their count, the three choosers, the row that opens Compare
module: designer_activity
---

## Where does the tab live?
- History (`openSnapshotsPanel(tab)` in `style_designer.src.js`) draws its head and two tabs once: "This page" (the revisions and snapshots, redrawn by `renderList()` into `.sd-hist-page`) and "Activity" (`.sd-act-host`). It opens on "This page"; `openSnapshotsPanel('activity')` opens or switches to Activity. The two tabs are one stop; arrows, Home and End move between them.
- The tab is the designer part `activity` (`when` always): `pg_designer_activity.js` (`window.PgDesignerActivity`, the pure rules) and `style_designer_activity.js` (`show(host, { fresh, done })`). No new button: the command palette's row "Show the design's activity" (`act_activity`).

## When is the list read?
- Each time the tab is shown (grey rows meanwhile, the choosers greyed). The choosers, the people found, the counts and the kept trees live while History stays open; `fresh` (History opened anew) resets them. Nothing polls.
- Fifty at a time with the reader's cursor: near the end of the list (240 px) the next page is asked for, "Reading earlier activity…" with a spinner meanwhile, "Try again" when it fails. The end reads "This is where the design's activity begins." or, narrowed, "That is all of it."

## How do the three choosers narrow?
- `activity_list` takes `person` (`me`, a user id, `customers`, `schedule`), `page` (a page id) and `kind` (`version`, `page`, `share`, `design`, `component`, `file`); `pg_designer_activity_filter()` normalises them, junk widens.
- `pg_designer_activity_filter_sql($part, $filter)` puts them on each store's query (parts `revision`, `audit_page`, `audit_file`, `audit_style`, `audit_component`, `link`, `revoke`, `verdict`, `comment`), false skips the part. So a narrowed list is still fifty from the whole design. A share link, a design file, the design and a component have no page: a chosen page leaves them out. A trail line with source `job` is the schedule's, never a person's. Customers: every answer and a guest's comment.
- Person menu: Everyone, Me, the people in the rows read so far, Customers (designer roles only), The schedule. Page menu: a search field, Every page, the open page first with "open now", then by name (`K.pages`). Kind menu: Everything and the six; a content editor gets the first two.
- A narrowing chooser turns blue and shows its choice. Narrowed to nothing: "Nothing matches the three." with "Show everything".

## What is a row?
- Day heads: Today, Yesterday, the weekday and date, the year when not this one (local time). The kind's grey mark, the person in bold, the reader's sentence, the hour; "change by …" for an applied change.
- A page's version with a kept version before it (the reader's `before_no`, `PgDesignerActivity.before()`) is a button: Compare opens with `left: 'rev:<before_no>', right: 'rev:<n>', history: n`; closing Compare gives the tab back as it was. "Load this revision" there loads n (`designer-compare-look-tab.md`). Revision 1 ("first version") and the oldest kept version open nothing.
- An empty state only when nothing older remains (`blank()`); a page that does not fill the list asks for the next at once.
- The count: for the version rows in view, one at a time 300 ms after the list rests, both revisions read with `revision_get` (up to 24 trees kept), `PgCompareChanges.read()` and `rows()` (the part `compare` is loaded for it). "12 changes", "1 change", "settings only", empty when it cannot be made.

## Tests?
- `tests/designer_activity_test.php` (the filter and its SQL per part), `tests/designer_activity_js_test.php` (Node: days, marks, which row opens, the count, people, pages, params, near the end).

Related: docs/memory/designer/designer-activity-reader.md, docs/memory/designer/designer-activity-drawing.md, docs/memory/designer/designer-activity-drawing-choosers.md, docs/memory/designer/designer-compare-look-tab.md
