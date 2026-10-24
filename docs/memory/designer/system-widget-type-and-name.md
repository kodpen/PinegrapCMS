---
name: system-widget-type-and-name
description: Designer system widget rules: SW_TYPES, auto names, empty widget, gated type change (fork/all/cancel), the panel's copy button, walking all page tabs, pg_shared_component_usage() and usage_all rows.
---

## Why is a system widget the page's, and its type its identity?
- A `shared_components` row may stand on several pages (deliberate: one login form on two pages); a type change or layout edit made on one page reaches the others.

## Where do widget types come from?
- `SW_TYPES` (style_designer.js) is the single list: type, label, name slug, icon. The "Yeni" group, panel type select and auto name read it; a new widget type goes into it.
- `_swTypeDefaults` gives the type's starting keys; type change and creation share it (never write two lists). A type is accepted only via `_swIsType`.

## How is the widget named?
- Name is `<page>-<type>-widget` (`_swAutoName(type, page, where)`, `_swSlug`; unnamed tab = `page`). Empty widget = `<page>-widget`.
- A widget that belongs to the design takes `<design>-<type>-widget`: dropped into a shared part such as a header (`where.target`, `_sdSharedOwnerOf()`), placed on more than one page of the design, or inside another shared component (`where.sid`).
- On type choice a name still matching the auto pattern (`_swIsAutoName`, the server's ` [n]` suffix included) is renamed; an operator-typed one is kept.
- In the panel the name is an input (`#sd-sw-name` -> `_swRename`). Server keeps uniqueness with `_sc_unique_name`; do not keep a counter on the client ("Sistem Widget 1 [11]" came from that).

## What does an empty widget show?
- `regionType === ''` shows the placeholder "— Choose a content type —" (`_typeUnset`), never the first type as if selected.
- Legacy rows with `typeof regionType !== 'string'` are form lists, the renderer's default; that fallback exists only for them.

## What happens on a type change?
- Gated by `_swOtherPlacements(sid)`. If non-empty (`_sharedRefUsageInDesign`: other tabs of this design, unsaved too; `_sharedUsage`: other designs) offer three choices: copy (`_swForkForThisPage`: new row, placements on the active page switch to it; `_cf.ids` stripped, the page save gives each control the page's row of the same name), change in all, cancel (`data-sw-current` restores the old value).
- A three-button question is `sdChoose` (`pgConfirm` has two); never split it into two questions.

## When does the panel offer "Make a copy for this page"?
- Under "Where it is used" (`propsSharedRef`) when `_swForkOffer(n)` returns an offer: a system widget with other placements, full access (no `create` below role 1), placed in the page tree itself (a header's placement stands on every page). The click re-checks, then `sdChoose` (copy / cancel) runs `_swForkForThisPage(sid, {})`, kind kept.
- The row is created at once; the re-pointed `shared_ref` makes `_pgPageDirty()` true, so Save writes the page. The original's unsaved layout edits go into the copy and are still flushed to the original, as the dialog says (`offer.unsaved` compares the snapshot: a selection alone sets `_sharedDirty`). Tests: `tests/designer_widget_fork_test.php`.

## How must code walk tabs?
- Widget delete, rename and "where used" go through `_pgAllPageTrees()` (live `tree` for the active tab, parked `p.tree` for others); walking only `tree` leaves "Not found" placements in other tabs. After changing another tab call `_pgTabsRefreshDirty()`.
- Use on saved pages comes from one place on the server: `pg_shared_component_usage()` (sub-actions `usage_all`, and `delete_many`, which keeps a used row without `force`). Every page counts, the recycle bin included (`binned`); a placement inside another component counts on every page placing that one.
- `usage_all` rows carry `page_id, page_name, style_id, style_name, binned` (a leftover `page_id AS style_id` once broke `_pgDesignSharedIds`' `u.style_id === styleId`). Text via `_sharedUsageText`: the page, plus another design's name in parentheses.

Related: docs/memory/designer/designer-page-pickers.md
