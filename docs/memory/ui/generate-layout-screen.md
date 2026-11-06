---
name: generate-layout-screen
description: Generate Layout (generate_layout.php, includes/templates/generate_layout.php) in the edit screen pattern: the read-only code, the Summary, Copy the code and Edit Layout in the save bar
---

## How is Generate Layout laid out?
- Since #7389 (B11): the generated layout code (read-only `#layout`, PHP code editor) in a "Layout" card in the form column; a Summary (Page, Page Type, Layout Type, Lines of Code) in the sticky status column written after it; a sticky save bar. Before, one full-width card held only the code.
- Nothing is saved: the screen has no form post. The back arrow goes to Edit Page.

## What is in the save bar?
- "Copy the code" (`#generate_layout_copy`, primary): copies the textarea value without the leading newline the liveform field adds, then `pgToast` "Copied to clipboard". The label never changes (`button-busy-state.md`). No clipboard API (plain http): nothing happens.
- "Edit Layout" only when the page's `layout_type` is `custom`, to the same `pg_layout_edit_url()` target as Edit Page's link (the designer for a visual design's page). A page on the system layout has nowhere to paste yet.

## What keeps it?
- `tests/generate_layout_screen_test.php`: column order, the code in the form column, the Summary, the save bar's two actions and the custom-only Edit Layout.
