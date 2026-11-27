---
name: bug-hunt-areas-legacy-to-designer
description: The bug hunt in the move to the visual designer and Back to classic (2026-11-27): what was fixed, what was read clean, what is left
---

## Which area was it?
- `includes/fn/legacy_to_designer.php`, `_plan.php`, `_run.php` and `legacy_to_classic.php`, with the move screen's Back to classic POST: regions and forms the run skips, a second move of the same page, text and links lost on the way, the classic route back.

## What was fixed?
- The dry run listed under "Page types" every treeless page of a style whose code has `<system></system>`; the run converts only a System region in the page's own tree. A frame with no page region, or a System region before the first or after the last page region, puts it in a shared part, so the page kept it. Now `pg_ltd_frame_page_system()` asks the split (`legacy-stock-types.md`).
- A page type's widget was found again by name and settings only: two pages whose names give one slug (or none, a name in another script) with the same settings shared the first page's widget and layout. `pg_ltd_widget_row_where()` compares the layout for a page type (`legacy-types-step.md`).
- Back to classic read a run's `origins` record for whatever design the page sat in: a page sent back, then moved again by the editor's classic open or a move alone, was listed under the new design and sent to the old run's style. The record now names its run's design (`legacy-to-classic.md`).

## What was read and found clean?
- The cursor, its steps, skips and the set's resume; widgets and components found again on a resumed step; the page region splice (all or none, document order); the frame split's categories and the origin by frame; refusals, revision before the tree leaves, the token and the running-move check on the screen.

## What is left?
- A style moved alone folds its body classes only onto the pages given the frame tree and the folder pages, then clears the style's column; a page that kept a tree of its own does not get them (the editor's Save folds them onto every page). Rare: a classic style's page with a tree of its own.
- Back to classic takes the tree off a page that had a tree of its own before the move, so it is drawn by the classic code instead of its earlier tree (kept in History).
- Whether a System region counts as page content in the frame split (it would then become the page type's widget in more pages) is a product question, not changed.
