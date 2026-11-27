---
name: designer-home-narrow
description: The New design window below 768 px (a list, then the pane with "All starts") and the Page Design Studio home on a phone (one column, the other designs as a list)
---

## What does the New design window look like below 768 px?
- The whole screen (`md-down:dialog-fullscreen`), two screens of one window: first the starts as one grouped list, each row ending in a chevron (`.sd-ns-chev`) and none marked; a press opens that start's pane (`#sdStartModal.is-pane`, set by `assets/js/designer_home.js`).
- The pane sits under a sticky bar with "All starts" (`#sd-ns-back`) that goes back to the list; entering the pane adds a history entry, so the phone's Back does the same. The foot's buttons are full width, the filled one on top.
- The home's "New design" (`data-sd-narrow="list"`) opens on the list; a first-visit card or `?start=` opens on its start's pane. Template cards are two a row (`col-6 xl:col-4`).
- Architect (2026-11-26): narrow, the strip of starts leaves for a list: the strip that stood over the pane had five of the seven starts off its edge (board N5 of `designer-home-tidy-drawing.md`, #11934).

## What does the home look like on a phone (below 576 px)?
- The head keeps "Designs", the count and "New design"; the sentence leaves, "Shared components and system widgets" is its icon with the words as its tooltip (`pg_designer_home_comp_button()`, `.sdh-comp-label` hidden), the search takes a row of its own.
- The large card stacks; its page chips fill two rows before "+n more" (`--sdh-chip-rows` on `.sdh-pages.is-row`, read by `fitChips()`); its buttons share the width. The first visit's three cards are rows.
- Below 560 px the other designs are a list: a small preview at the left (no browser bar), then the name with ⋯ and the one line (board H5).

## Tests?
- `tests/designer_home_test.php`: the window's fullscreen class, the "All starts" bar, each row's chevron, "New design" opening the list, the shared components button's label span and tooltip.

Related: docs/memory/designer/new-design-window.md, docs/memory/designer/designer-home-screen.md, docs/memory/designer/designer-home-tidy-drawing.md
