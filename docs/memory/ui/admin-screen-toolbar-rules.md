---
name: admin-screen-toolbar-rules
description: Button recipes, menu colour rule, shared toolbar CSS classes, card header pattern and narrow-screen behaviour for admin screen toolbars.
---

## What button recipe applies per intent?
- One recipe per intent; two buttons doing the same job look the same.
- Go to a related screen / in-place tool: `btn-sm btn-outline-secondary`. Irreversible work: `btn-sm btn-outline-warning`. Primary: `btn-primary`.
- Three weights in one list (filled + outline + `btn-lg`) is confusion.
- A button keeps its label while its request runs and is never locked for a fixed time: `button-busy-state.md`.

## When does a group of buttons become a menu?
- More than four buttons go into a menu. Side-by-side, mostly unrelated buttons become one menu.
- Reason in `view_folders`: the "transfer" menu replaces four location-dependent buttons that would sit permanently faded in the bar.

## How are icons written?
- Always `<i class="bi …"></i>`. Never put `bi bi-x` in the button's `class`; never `<span class="bi …">`.

## Is colour allowed in menu rows?
- No: this rule belongs to MENUS ONLY. In the file manager colour already tells folder access status and type, so a yellow menu row would read "related to these folders", not "caution".
- Every menu row has the same colour (`link-body-emphasis`); danger is told by icon, confirm dialog and group divider.
- Elsewhere there is no such limit: buttons (`btn-outline-warning`), badges, alerts keep colour as importance/status. Do not carry the rule out of menus (an in-page `alert-warning` has nothing to do with folders). The clash exists only in menus because menu rows share the visual language of folder lists.

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
- Why: `order: 9`/`10` (and the File Manager's own `1-8`) drew them on lower lines but Tab reached them before line 1. Removed in PR #3513; pinned by `tests/toolbar_narrow_order_test.php`.
- `editor_select_image.php`: the picker opens as a 390 px tab on a phone (zoom off), so `#esi_toolbar` wraps and the filter pill takes a full row, its fields `flex: 1 1 0`.
- A card header's right-hand button cluster (`ms-auto d-flex`) carries `flex-wrap justify-content-end`: `.btn` is nowrap, and four of them pushed `marketplace_settings.php` sideways.

## Which screens keep their own phone toolbar rules?
- `editor_select_image.php` (`#esi_toolbar`) and the prototype viewer bar (`includes/fn/prototype.php`, `.pg-proto-bar`) skip the shared classes; their filter pill / page navigation also take a full line with no `order` (PR #3483, `tests/toolbar_local_order_test.php`).

Related: docs/memory/ui/admin-screen-toolbar.md
