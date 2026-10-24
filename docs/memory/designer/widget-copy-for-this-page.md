---
name: widget-copy-for-this-page
description: Designer "Make a copy for this page": when the widget panel offers it, the dialog's choices for unsaved edits, what the copy keeps (field row ids, name), the tab open at the answer, what Save writes.
---

## When is "Make a copy for this page" offered?
- Under "Where it is used" in the widget panel (`propsSharedRef`) when `_swForkOffer(n)` returns `{ sid, others, unsaved }`: a system widget other pages or designs also place (`_swOtherPlacements()`), full access (the server refuses `create` below role 1), placed in the page tree itself (a placement inside a header stands on every page placing the header). The click asks again before the dialog.
- `unsaved` compares the cached tree with its snapshot (`_sdSharedTreeJSON()`): a selection alone sets `_sharedDirty` without a change.

## What does the dialog ask?
- `sdChoose`. Without unsaved edits: copy / cancel. With unsaved layout edits on the original: "keep the others as saved" (`keepOriginal`), "change the others too", cancel. The copy takes the edits either way.
- `keepOriginal` runs `_sdSharedRevert(sid)`: the original's cached tree goes back to its snapshot (node ids backfilled), or is read again with `prefetchShared()` when it has none, and `_sharedDirty` is cleared, so the flush sends the original nothing. Without it the flush writes the edits to the original too (it pushes every dirty id, placed here or not).
- The undo step is taken before the revert: undoing the copy brings the edits back on the original.

## What does the copy keep?
- `_swForkForThisPage(sid, opts)`: a clone of the layout under new node ids and the settings as they are; only this page's placements move to it. The type change passes `regionType` and gets that kind's starter tree instead.
- Field row ids: `_swKeepPageFieldIds()` keeps only `_cf.ids[<this page_id>]` (none on a page never saved). The copy stands on this page only, and the page save (`pg_cf_reconcile_page_form()`) matches a control by row id before its name, so a field renamed since the last save keeps its row and its submitted values. Symptom avoided: with every id stripped, that save created a new row and deleted the old one with its `form_data`.
- Name: `_swAutoName(_swNameKind(cfg), page)`. A legacy row (no string `regionType`) and the aliases `submitted_forms_list`, `form_list`, `blog_list` are named as form lists, not as the empty widget (`<page>-widget`).

## What if another tab is opened before the server answers?
- The page is fixed at the click. The answer re-points that page's tree (the parked `p.tree` once it is no longer open), takes the undo step only while it is the open tab, repaints the tab dots (`_pgTabsRefreshDirty()`), and the toast names the page. A page closed meanwhile is left alone; the copy stays listed, unplaced.

## What does Save write?
- The row is created at once (`create` with the tree and settings). The re-pointed `shared_ref` makes `_pgPageDirty()` true, so Save sends the page with its tree instead of `unchanged` (`_pgPageUnchanged()`). Tests: `tests/designer_widget_fork_test.php` (Node).

Related: docs/memory/designer/system-widget-type-and-name.md, docs/memory/designer/custom-form-sync.md
