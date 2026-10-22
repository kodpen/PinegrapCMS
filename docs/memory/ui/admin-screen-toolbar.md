---
name: admin-screen-toolbar
description: Panel screen basics (frame, notices, fields, width, icons, help) and the single-toolbar design principle for every admin screen, with view_folders.php as reference: slot order and classes.
---

## What are the basic panel UI helpers and conventions?
- Bootstrap 5. `output_header([...])` / `output_footer()` = page frame. Cards are spaced with `mb-5`.
- `$liveform->output_errors()` / `output_notices()` print messages; `add_notice(...)` / `add_error(...)` add one; `$liveform->mark_error('field', 'message')` marks a field, then redirect with `go($url)`. A screen that adds notices prints them and calls `remove_form()` after drawing (`liveform-notices-trap.md`).
- Fields are drawn with `$liveform->output_field([...])`, never a hand-written `<input>`: error marking and refill come from it.
- No `max-width` on an admin screen: screens are `container-fluid` wide; a field's width comes from the `col-*` grid.
- Icons: Bootstrap Icons `<i class="bi bi-icon-name"></i>`, `aria-hidden="true"` when decorative. Material Icons (`<span class="material-icons">`) remain only in old code. The main menu rows are the exception (`main-menu-icons.md`).
- Reference screens: `view_folders.php` (toolbar), `product_builder.php` (edit screen layout, `edit-screen-wide-layout.md`). Help link: `panel-help-links.md`.

## What is the admin screen design principle?
- Every new or reworked admin screen follows `view_folders.php` (File Manager): everything the screen can do sits in ONE toolbar directly under the title; a plain content area is below it.
- The preview/tree panel of that screen is file-manager specific, not part of the principle.
- Site Settings was moved to this principle (split into categories, then into a modal): see docs/memory/ui/settings-modal-architecture.md.

## What is the toolbar slot order?
| Slot | What | Class |
|---|---|---|
| 1 | Primary action (often opens a menu: "Yeni", "Kaydet") | `btn btn-sm btn-primary rounded-pill px-3` |
| 2 | Secondary tools, grouped into one menu if needed | `btn btn-sm btn-ghost` |
| 3 | Location / breadcrumb, flexes (`flex: 1 1 220px`, `.pg-toolbar-grow`) | - |
| 4 | Search / filter | `input-group input-group-sm rounded-pill` (`.pg-toolbar-search`) |
| 5 | View switcher (if any) | `btn-group btn-group-sm` + `btn-ghost.active` |
| 6 | Overflow menu `⋮`: rare, screen-specific things | `btn btn-sm btn-ghost` + `dropdown-menu-end` |
- The strip under the heading of older screens (`#button_bar`) is the same component: `page-button-bar.md`.

Related: docs/memory/ui/admin-screen-toolbar-rules.md
