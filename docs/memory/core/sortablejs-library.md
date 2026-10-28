---
name: sortablejs-library
description: The vendored SortableJS (assets/lib/sortablejs/, 1.15.7, unmodified): why it replaced jQuery UI .sortable(), which screens load it, how to update it
---

## What is it and why is it here?
- SortableJS 1.15.7 (MIT, npm `sortablejs`, released 2026-02-11), the drag-to-reorder library that replaces jQuery UI `.sortable()` in the panel (decided in #2511, option 2). jQuery UI is leaving the panel with Bootstrap 6 (`plans/bootstrap-6-redesign.md`, B12).
- `assets/lib/sortablejs/Sortable.min.js` is the release file byte for byte (sha256 `bf4241bc…1fcf76`), with its `/*! Sortable 1.15.7 - MIT … */` header; `LICENSE` sits beside it. No local change: an update copies `Sortable.min.js` and `LICENSE` from the npm package and bumps the version in `tests/sortablejs_test.php`.
- It has no dependency and no PHP side, so the PHP 7.1 base rule does not apply.

## Where does it load?
- Only on the screens that drag, each with its own `<script>` tag, never in the panel header: `welcome.php` (dashboard widgets), `product_builder.php` (both places it prints `product_builder.js`: image tiles), `view_menu_items.php` (the menu tree, Sort Items). A new screen that needs it adds its own tag.
- The setups use `Sortable.create(element, options)` and guard on `typeof Sortable`. How each screen is set up: `ui/sortable-init-pitfall.md`.

Related: docs/memory/core/file-header-and-embedded-libraries.md, docs/memory/ui/sortable-init-pitfall.md
