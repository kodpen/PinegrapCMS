---
name: bug-hunt-areas-designer-activity
description: Bug hunt of the designer's History "Activity" tab and its audit lines (2026-11-26, PR #11762): three faults fixed, what was read clean
---

## What was the area?
- The reader `includes/fn/designer_activity.php`, the panel action `designer` / `activity_list`, the tab (`assets/js/style_designer_activity.js`, `assets/js/pg_designer_activity.js`) and the Compare row it opens. Named by the architect after AC4.

## What did it fix? (PR #11762)
- A content editor's page of fifty was cut before the events of pages they may not edit were taken out: a design busy on other pages gave them "Nothing has happened in this design yet." or a spinner that never ended, while their own events lay further back. `pg_designer_activity_fill()` reads on; the tab shows an empty state only when nothing older remains (`PgDesignerActivity.blank()`) and asks for the next page when an added one does not fill the list.
- A content editor saw the customers' answers and comments on their pages, which the prototype review keeps to the designer roles. Architect (2026-11-26): a content editor's list holds page versions and pages on and off the site only; `pg_designer_activity_visible()` passes those two kinds, and the verdict and comment stores are not read for them.
- The oldest kept version of a page published more often than the retention keeps (30 by default) opened Compare on a trimmed revision n-1: an error toast and a left side that could not be drawn. The reader names `before_no`; that row opens nothing.

## What was read clean?
- Rights: every design-level store (links, files, the design, components) is read only for the designer roles; a content editor's versions come only from the pages they may edit; a binned or deleted page by the folder its line kept. The page chooser cannot widen them.
- Lines written once: a save that changes no field writes no trail line, an identical tree writes no version, undo and library inserts are client-side until a save; no server autosave.
- What a line carries: no values before and after, no `username` column; names and sentences escaped by the tab (`esc()`); `lang()` vars are not HTML.
- "Load this revision": loads the side holding the row's revision, and only into the page open in the editor ("Open the page in its tab first."); `revision_get` asks the right to edit the page.
- The cursor (parsed, integers and known stores), paging order, a removed user, local day heads; CSRF: `validate_token()` before every sub-action, `activity_list` only reads.

## What was left?
- Nothing.
