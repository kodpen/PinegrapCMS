---
name: admin-screen-toolbar-rules
description: Button recipes, menu colour rule, shared toolbar CSS classes, card header pattern and narrow-screen behaviour for admin screen toolbars.
---

## What button recipe applies per intent?
- One recipe per intent; two buttons doing the same job look the same.
- Go to a related screen / in-place tool: `btn-sm btn-outline-secondary`. Irreversible work: `btn-sm btn-outline-warning`. Primary: `btn-primary`.
- Three weights in one list (filled + outline + `btn-lg`) is confusion.

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
- In the shared file, not in the page: `assets/css/backend.src.css`, end of file, block *Admin screen toolbar*: `.pg-toolbar`, `.pg-toolbar-grow`, `.pg-toolbar-search`, `.btn-ghost` (+ `.btn-ghost.active`) and narrow-screen rules.
- New screens use these classes; do not write new bar CSS on your own `#id`.
- `view_folders.php` still carries its own `#explorer_toolbar` copy (works, untouched); move it to the shared classes the first time that screen is edited.
- `btn-ghost` is defined only with Bootstrap's own button variables, so light/dark theme needs no second rule.

## What is the card header pattern?
- `card-header` = `d-flex justify-content-between align-items-center`; left: `text-uppercase h5 text-primary fw-bold mb-0` title; right: the card's single link button.
- Never put a second heading with the same name as the card inside it.

## How does the toolbar behave on narrow screens?
- It splits into rows: controls take the first row; breadcrumb and search drop to full-width lower rows (`order` + `flex-basis`).
- Example: the `@media (max-width: 767.98px)` block in `view_folders.php`.

Related: docs/memory/ui/admin-screen-toolbar.md
