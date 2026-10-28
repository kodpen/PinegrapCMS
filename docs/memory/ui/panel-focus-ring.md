---
name: panel-focus-ring
description: The panel's focus ring on Bootstrap 6's focus-ring tokens (step B9k): the --pg-focus-ring-* tokens, why the colour is not an alias, what was checked, which ring sizes wait for B10
---

## Which tokens draw the panel's focus ring?
- `--pg-focus-ring-width` aliases `--bs-focus-ring-width` (3px; Bootstrap sets it only on `:root`).
- `--pg-focus-ring-color` is the panel's own value, `color-mix(in srgb, var(--pg-primary) 35%, transparent)`: a pastel ring at Bootstrap's alpha is close to invisible on white. The unlayered bridge fills `--bs-focus-ring-color` from it, so Bootstrap's own rings (`.chip-input`, `.focus-ring`) match.
- Never the other way round (`--pg-focus-ring-color: var(--bs-focus-ring-color)`): an alias resolves on `:root` and misses a value set on one element, and with the bridge it would be a cycle.
- `--bs-focus-ring-offset` has no alias: Bootstrap sets it per component (1px, -1px, 0) and no panel ring uses it.
- Both sit in the token block of `assets/css/backend.src.css`; `designer_home.css` reads them too.

## What changed in B9k (#2561)?
- Rules in `backend.src.css` and `designer_home.css` read `--pg-focus-ring-*`, not `--bs-focus-ring-*` or `rgba(var(--pg-primary-rgb), .35)` (the global WCAG ring). The designer home's `#0d6efd` fallbacks went.
- The ring colour was declared twice: the v5 `rgba()` in the layered first `:root` block and the `color-mix()` in the unlayered bridge, which always won. The layered one went.
- Checked in Chromium with the old and new stylesheets: 46 elements (links, buttons, fields, `.form-adorn`, pagination, `.btn-close`, `.pg-sale-ig`, role card, designer home cards, template palette, skip link), light and dark plus a nested opposite theme, at rest and `:focus-visible`, transitions off. Computed styles (colours compared as 8-bit RGBA, since `color-mix()` serialises as `color(srgb …)`) and element screenshots were equal. A unit test cannot render CSS; `tests/bs6_focus_ring_test.php` guards the source.

## Which rings wait for B10?
- Their size differs from v6's 3px outline with 1px offset, so moving them changes pixels; that is B10 token tuning, which needs the owner's visual approval: the global WCAG rule (2px outline, 2px offset, 0.2rem shadow), `.no-popover` links, the skip link (3px, 2px), `.pg-role-card` (0.25rem shadow) and the designer home's `.sdh-start`, `.sdh-card`, `.sdh-page` (0.2rem shadow). Their colour is already on the token.
- Workspace rings use the Workspace accent (`--ws-accent`), not the panel ring (`workspace/workspace-ui-scale.md`).
