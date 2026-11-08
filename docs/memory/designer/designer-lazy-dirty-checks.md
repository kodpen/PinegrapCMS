---
name: designer-lazy-dirty-checks
description: When the designer compares a page with its baseline (the 1.5 s poll, the tab strip, save) and how the answer for a page not open is kept (_pgDirtyMemo) (D4, PR #8091)
---

## When is a page compared with its baseline?
- The 1.5 s poll (`_pgTabsPollDirty()`) compares the open page only after an edit (the edit epoch moved) and sweeps every page each tenth tick (`_pgTabsRefreshDirty()`). The tab strip (`_pgTabsRender()`), the save (`_pgPageUnchanged()`), leaving the editor (`isDirty()`) and a tab switch ask `_pgPageDirty()` too.
- No dirty check runs inside `render()`. The shared snapshots (`_sdSharedBaseline()`) are taken once per component on its first draw and compared at save (`_sdSharedCatchEdits()`) and when a tab's prefetch would replace a cached tree (`_sdSharedChanged()`).

## What is kept, and when is it dropped?
- `_pgPageDirty()` keeps the answer of a page that is not open in `_pgDirtyMemo`, by page key, beside the pages: a page object is copied and sent, and the entry holds its tree.
- It holds while `_pgDirtyMemoKey(p)` is the same (`_pgDirtyMemoSame()`): the edit epoch (`_sdEditEpoch`, moved by every `saveState()` and `render()`), the tree object, the baseline string and the settings as the baseline writes them. So an idle sweep and a redraw of the tab strip serialise no untouched page; any edit through the editor drops every kept answer.
- The open page is never kept: typing in place changes its tree without moving the epoch. Its fields are read into it first, as before.

## How is it tested?
- `tests/designer_lazy_dirty_test.php` runs `_pgPageDirty()` and the helpers under Node: one serialisation per epoch for a page not open, a new epoch, tree, baseline or name compares again, the open page every time, nothing written on the page object.

Related: docs/memory/designer/multi-page-designer-editor.md, docs/memory/designer/designer-performance.md
