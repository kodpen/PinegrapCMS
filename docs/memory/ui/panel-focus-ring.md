---
name: panel-focus-ring
description: The panel's focus ring on Bootstrap 6's tokens and shape (B9k colour, B10 sizes): the --pg-focus-ring-* tokens, why colour and offset are not aliases, inset rings, what was checked
---

## Which tokens draw the panel's focus ring?
- `--pg-focus-ring-width` aliases `--bs-focus-ring-width` (3px; Bootstrap sets it only on `:root`).
- `--pg-focus-ring-color` is the panel's own value, `color-mix(in srgb, var(--pg-primary) 35%, transparent)`: a pastel ring at Bootstrap's alpha is close to invisible on white. The unlayered bridge fills `--bs-focus-ring-color` from it, so Bootstrap's own rings (`.chip-input`, `.focus-ring`) match.
- Never the other way round (`--pg-focus-ring-color: var(--bs-focus-ring-color)`): an alias resolves on `:root` and misses a value set on one element, and with the bridge it would be a cycle.
- `--pg-focus-ring-offset` is the panel's own `1px`, not an alias of `--bs-focus-ring-offset`: Bootstrap changes that per component (-1px on fields, 0), which an alias resolved on `:root` would never see.
- Both sit in the token block of `assets/css/backend.src.css`; `designer_home.css` reads them too.

## What changed in B9k (#2561)?
- Rules in `backend.src.css` and `designer_home.css` read `--pg-focus-ring-*`, not `--bs-focus-ring-*` or `rgba(var(--pg-primary-rgb), .35)`; the designer home's `#0d6efd` fallbacks and a second (layered, always beaten) ring colour declaration went.
- Checked in Chromium, old and new sheets: 46 elements, light, dark and a nested opposite theme, computed styles and element screenshots equal. A unit test cannot render CSS; `tests/bs6_focus_ring_test.php` guards the source.

## Where is the ring inset or given room, and why?
- The global ring sits 2-4px outside a control, so a parent with `overflow` set cuts it. Since #3434: scrolling strips get `padding: 4px` with a matching negative margin (section rail `.pg-settings-nav`, breadcrumb strip, toolbar chip strip `.pg-toolbar .pg-chips`; nothing moves); boxes that clip and are filled by the control draw `outline: 2px solid var(--pg-primary-ink); outline-offset: -2px` (licence row, `.pg-code-field:focus-within`, the focused input/button inside `.pg-sale-ig`, `.pg-sale-step` input, Word/Excel cell, paragraph, `.office-pane`, `.office-doc`).
- Before, those showed a cut ring, only the 35% halo (~1.3:1), CodeMirror's clipped dotted outline, or nothing (`outline:none` on office cells/paragraphs). A chip field joins the global list as `.chip-input.pg-chips:has(> .form-ghost:focus-visible)`, like `.form-adorn`.
- Office rules are prefixed `.office-view` to outrank the global `[tabindex]:not([tabindex="-1"]):focus-visible` (0,3,0). Test: `tests/panel_focus_ring_clip_test.php`.

## What changed in B10 (#4465)?
- Bootstrap 6's ring shape, one outline of `--pg-focus-ring-width` at `--pg-focus-ring-offset` with no shadow halo, on: the global WCAG rule (was 2px outline, 2px offset, 0.2rem halo), `input.btn-check:focus-visible + .btn`, `.no-popover` links and buttons, the skip link (3px, 2px), `.pg-role-card` (0.25rem shadow only) and the designer home's `.sdh-start` / `.sdh-page` (0.2rem shadow only).
- Colours did not change: primary ink where the ring was ink (3:1 on the page; Bootstrap's pale ring colour is not), `--pg-focus-ring-color` where it was the pale shadow. The outer edge stays 4px out, the room the scrolling strips leave.
- `.sdh-card` is `tabindex="0"`: the global `[tabindex]` ring (0,3,0) always outranked its own `.sdh-card:focus-visible` rule, which went. In forced colours the role card keeps its outline and takes `Highlight`.
- Fields keep their own 3px halo (`.form-control:focus` and the validation states); with the 1px offset it shows as a 1px pale line inside the ink ring. Checked in Chromium on the sandbox: 15 rings, light and dark, computed style and close-ups before and after.
- Workspace rings use the Workspace accent (`--ws-accent`), not the panel ring (`workspace/workspace-ui-scale.md`).
