---
name: admin-screen-toolbar-rules
description: Button recipes, menu colour rule, shared toolbar CSS classes, card header pattern and narrow-screen behaviour for admin screen toolbars.
---

## What button recipe applies per intent?
- One recipe per intent; two buttons doing the same job look the same.
- In a toolbar the recipes are the slot recipes of `admin-screen-toolbar.md`: primary is slot 1 (`btn btn-sm btn-solid theme-primary rounded-pill px-5`), drawn first; a related screen or in-place tool is slot 2 (`btn btn-sm btn-text theme-secondary`), never drawn as primary. Irreversible work outside a menu: `btn-sm btn-outline theme-warning`.
- A screen-wide action (Clear Log, New department) sits in the screen toolbar, never in a card header; clearing goes into the overflow menu (`view_waf_log.php`, `view_not_found_log.php`).
- Never three weights in one list (filled + outline + `btn-lg`).
- A button keeps its label while its request runs and is never locked for a fixed time: `button-busy-state.md`.

## When does a group of buttons become a menu?
- More than four buttons go into a menu. Side-by-side, mostly unrelated buttons become one menu.
- `view_folders`' "transfer" menu replaces four location-dependent buttons that sat faded in the bar.

## How are icons written?
- Always `<i class="bi …"></i>`. Never put `bi bi-x` in the button's `class`; never `<span class="bi …">`.

## Is colour allowed in menu rows?
- No, in MENUS ONLY: in the file manager colour already tells folder access and type, so a yellow row would read "related to these folders", not "caution".
- Every menu row has the same colour (`link-body-emphasis`); danger is told by icon, confirm dialog and group divider.
- Elsewhere buttons, badges and alerts keep colour as importance/status; the clash exists only in menus, whose rows look like folder rows.

## Where do toolbar classes live?
- In the shared file, not in the page: `assets/css/backend.src.css`, block *Admin screen toolbar*: `.pg-toolbar`, `.pg-toolbar-grow`, `.pg-toolbar-search`, `.btn-text.theme-secondary` (+ `.active`) and narrow-screen rules. Toolbar buttons are `btn btn-sm btn-text theme-secondary`; `.btn-ghost` is the old class, kept only for `workspace_flow.js`, never written in new code. `#button_bar` shares them (`page-button-bar.md`).
- New screens use these classes; do not write new bar CSS on your own `#id`.
- `view_folders.php` wears them too (`#explorer_toolbar.pg-toolbar`); its own phone rule only starts the view switches' group on the right (`#explorer_view_group { margin-left: auto }`).
- `.btn-text.theme-secondary` sets only Bootstrap's own button variables, so light/dark theme needs no second rule.

## What is the card header pattern?
- `card-header` = `d-flex justify-content-between align-items-center`; left: `text-uppercase h5 text-primary fw-bold mb-0` title; right: the card's single link button.
- Never put a second heading with the same name as the card inside it.

## How does the toolbar behave on narrow screens?
- Below 768 px `.pg-toolbar-grow` and `.pg-toolbar-search` take a full line each (`flex: 1 1 100%`) at their markup position, with no `order`, so Tab follows the rows as drawn (WCAG 2.4.3). An empty grow (a spacer) is hidden there (`:empty`), else it would split the bar.
- `order` drew them lower but Tab reached them first; removed in #3513 (`tests/toolbar_narrow_order_test.php`).
- `editor_select_image.php` opens as a 390 px tab on a phone: `#esi_toolbar` wraps, the filter pill a full row.
- A card header's right-hand button cluster (`ms-auto d-flex`) carries `flex-wrap justify-content-end`: `.btn` is nowrap, and four of them pushed `marketplace_settings.php` sideways.

## Which screens keep their own phone toolbar rules?
- `editor_select_image.php` (`#esi_toolbar`) and the prototype viewer bar (`includes/fn/prototype.php`, `.pg-proto-bar`) skip the shared classes; their filter pill / page navigation also take a full line with no `order` (PR #3483, `tests/toolbar_local_order_test.php`).

Related: docs/memory/ui/admin-screen-toolbar.md
