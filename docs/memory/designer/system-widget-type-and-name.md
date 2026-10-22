---
name: system-widget-type-and-name
description: Designer system widget rules: SW_TYPES, page and design auto names, empty widget, gated type change (fork/all/cancel), walking all page tabs, pg_shared_component_usage() and usage_all rows.
---

## Why is a system widget the page's, and its type its identity?
- A design has N pages. A `shared_components` row may stand on several pages (deliberate: same login form on two pages), but the cost is that a type change from one page rewrites the other.

## Where do widget types come from?
- `SW_TYPES` (style_designer.js) is the single list: type, Turkish label, name slug, icon. The "Yeni" group, panel type select and auto name all read it. A new widget type goes into this list.
- `_swTypeDefaults` gives the type's starting keys; type change and creation use the same function (never write two lists). A type is accepted only via `_swIsType`.

## How is the widget named?
- Name is `<page>-<type>-widget` (`_swAutoName(type, page, where)`, `_swSlug`; unnamed tab = `page`). Empty widget = `<page>-widget`.
- A widget that belongs to the design takes `<design>-<type>-widget`: dropped into a shared part such as a header or footer (`where.target`, `_sdSharedOwnerOf()`), placed on more than one page of the design, or inside another shared component (`where.sid`).
- On type choice, if the name still matches the auto pattern (`_swIsAutoName`, including the server's ` [n]` suffix) it is renamed; an operator-typed name is untouched.
- In the panel the name is an input (`#sd-sw-name` -> `_swRename`). Server keeps uniqueness with `_sc_unique_name`; do not keep a counter on the client ("Sistem Widget 1 [11]" came from that).

## What does an empty widget show?
- `regionType === ''` shows the placeholder "— Choose a content type —" (`_typeUnset`); never show it as if the first type were selected.
- Legacy rows with `typeof regionType !== 'string'` are form lists, the renderer's default; that fallback exists only for them.

## What happens on a type change?
- Gated by `_swOtherPlacements(sid)`. If non-empty (`_sharedRefUsageInDesign` = other tabs of this design including unsaved; `_sharedUsage` = other designs) offer three choices: copy (`_swForkForThisPage`: new row, placements on the active page switch to it, `_cf.ids` stripped because row ids belong to the original's page), change in all, cancel (`data-sw-current` restores the old value).
- Three-button question is `sdChoose`; `pgConfirm` has two buttons, do not split the third into two questions.

## How must code walk tabs?
- Widget delete, rename and "where used" all go through `_pgAllPageTrees()` (live `tree` for the active tab, parked `p.tree` for others). Walking only `tree` leaves a "Not found" placement in other tabs. If another tab was mutated call `_pgTabsRefreshDirty()`.
- Use on saved pages comes from one place on the server: `pg_shared_component_usage()` (sub-actions `usage_all`, and `delete_many`, which keeps a used row unless the request says `force`). Every page counts, the recycle bin included (`binned`), and a placement inside another component counts on every page that places that one.
- `usage_all` rows carry `page_id, page_name, style_id, style_name, binned` (a leftover `page_id AS style_id` once broke `_pgDesignSharedIds`' `u.style_id === styleId`). Text via `_sharedUsageText`: page name, plus design in parentheses if another design.

Related: docs/memory/designer/designer-page-pickers.md
