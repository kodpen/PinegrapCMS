---
name: admin-screen-toolbar
description: Panel UI basics (helpers, icons) and the single-toolbar design principle for every admin screen, with view_folders.php as reference: slot order and classes.
---

## What are the basic panel UI helpers and conventions?
- Bootstrap 5.
- `output_header([...])` / `output_footer()` = page frame.
- `$liveform->output_errors()` / `output_notices()` = notices; `output_field([...])` = render a form field; `add_notice(...)` / `add_error(...)` = add a message.
- `$liveform->mark_error('field', 'mesaj')` marks a field error, then redirect with `go()`.
- `go($url)` = redirect (header location).
- Icons: prefer Bootstrap Icons `<i class="bi bi-icon-name"></i>`. Material Icons (`<span class="material-icons">icon_name</span>`) remain only in old code; new code uses Bootstrap Icons.
- Cards are spaced with `mb-5`.

## What is the admin screen design principle?
- Every new or reworked admin screen follows `view_folders.php` (File Manager): everything the screen can do sits in ONE toolbar directly under the title; a plain content area is below it.
- The preview/tree panel of that screen is file-manager specific, not part of the principle.
- Site Settings was moved to this principle (split into eight categories, then into a modal): see docs/memory/ui/settings-modal-architecture.md.

## What is the toolbar slot order?
| Slot | What | Class |
|---|---|---|
| 1 | Primary action (often opens a menu: "Yeni", "Kaydet") | `btn btn-sm btn-primary rounded-pill px-3` |
| 2 | Secondary tools, grouped into one menu if needed | `btn btn-sm btn-ghost` |
| 3 | Location / breadcrumb, flexes (`flex: 1 1 220px`) | - |
| 4 | Search / filter | `input-group input-group-sm rounded-pill` |
| 5 | View switcher (if any) | `btn-group btn-group-sm` + `btn-ghost.active` |
| 6 | Overflow menu `⋮`: rare, screen-specific things | `btn btn-sm btn-ghost` + `dropdown-menu-end` |

Related: docs/memory/ui/admin-screen-toolbar-rules.md
