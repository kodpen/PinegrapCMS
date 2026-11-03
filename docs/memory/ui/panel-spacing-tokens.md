---
name: panel-spacing-tokens
description: B10 spacing (#5356): cards on Bootstrap 6's card padding with the header in line, menu rows inset with rounded corners, on Bootstrap 6's --bs-spacer-N scale
---

## How far apart are things in a card (B10 spacing, #5356)?
- Body: `--bs-card-spacer-x/y` = `--bs-spacer-6` (1.25rem), Bootstrap 6's own (was v5's 1rem).
- Header and footer: `--bs-card-cap-padding-y` = `--bs-spacer-4` (0.75rem), `-x` = the card's `--bs-card-spacer-x`, so header text starts where body text does.
- Set once, in the first `.card` rule of backend.src.css. A second plain `.card` rule also set the header padding (1rem, which won over 0.5rem); it now holds only radius, border width and fill.
- A rule that runs a table or list to the card's edge reads `var(--bs-card-spacer-x)` (`#ws-people`), never a literal 1rem. The dashboard widget card keeps its 0.5rem header.

## How is a menu spaced?
- `.menu`: a `--bs-spacer-1` (0.25rem) frame, rows `--bs-spacer-4` × `--bs-spacer-2` (0.75 × 0.375rem), headers `--bs-spacer-4` × `--bs-spacer-3`, row corners `--pg-radius-lg` (the surface radius 0.75rem minus the frame, so they nest). Before: v5's flush, square full-width rows.
- Menus with their own values keep them: the header user menu (`--pg-um-pad`), the sitemap menu (`.pg-smap-menu`).

## Which scale?
- Bootstrap 6's `--bs-spacer-0` … `-12` (0.25rem steps to 1.25rem, then wider). A new spacing value in a panel rule picks one of them.

## What is pinned, what is left?
- `tests/bs6_spacing_tokens_test.php`: the card values set once, header in line, menu inset and rounded rows.
- Left: literal paddings and gaps in screen rules (thousands, `0.5rem`, `1rem`, `12px`) were not moved onto the scale; form row gaps and the edit-screen layout keep theirs.
