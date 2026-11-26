---
name: bug-hunt-style-guide-theme
description: The bug hunt of the Style guide and the Themes panel (2026-11-26): the four faults fixed, what was read clean (renamed or deleted names, dark, print)
module: design_style_guide
---

## What did the hunt fix?
- "Light | Dark" in the Style guide took the Spacing block's heading away: the bars' wrapper and the block both had the id `pgsg-spacing`, and `setMode()` replaced the block. The bars' wrapper is `#pgsg-spacing-bars` now; every id of the sheet is once only (`tests/designer_style_guide_sheet_test.php`).
- An own text class with capitals (`.BigTitle`) was drawn as `class="bigtitle"`, so its sample showed at body size while the row named 2rem. `pg_style_guide_text_styles()` keeps the class as written (`tests/design_style_guide_test.php`).
- A forced `:hover` / `:focus` copy inside `@container` was written under `@supports (…)`, which every browser passes, so the Controls block showed a hover the page shows only in a wide box. `forceStates()` keeps each at-rule's own text up to its block (`@media`, `@supports`, `@container`, `@layer`, `@scope`).
- The Themes panel's "Reset every change in this group" took one undo step per variable (eight Undo presses for Theme Colors) and one even for an untouched group: now one step, none for nothing (`designer-theme-part.md`).

## What was read and found clean?
- A component deleted or renamed while the sheet is open: its card says it could not be drawn, its name is the server's of the moment; the sheet is modal, so no variable changes under it, and `reveal()` of a missing key opens the Your own fold and stops.
- Print in dark: Chrome keeps the `@page` margins white, so the head and foot stay readable (tried with a PDF of the page).
- The Themes panel's dark rows, placeholders and the value check.

Related: docs/memory/designer/designer-style-guide-sheet.md, docs/memory/designer/designer-theme-part.md, docs/memory/workflow/bug-hunt.md
