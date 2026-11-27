---
name: designer-left-column-heads
description: Left column heads of the Page Design Studio (board F4): the palette tab holder as its head, rail icons under 230 px, the Overview in one row, Recently used as a label
---

## What is the palette's head?
- The tab holder `#sd-panel-tabs` (`.sd-pal-tabs`) in a 40 px row `.sd-panel-header.sd-pal-head`, then the dock button. The old title row "Components" is gone; `_sdAddDockToggle()` still takes the left panel's first `.sd-panel-header`, which is now this row.
- A tab is drawn by `_sdPalTabHTML(key, label, active)`: the word in `.sd-pal-tab-txt`, the rail icon (`_SD_RAIL_ICONS[key]`) in `.sd-pal-tab-ic`, the word as its `title`. Tabs keep `.sd-panel-tab` and `data-tab`: the rail, the tour and My library read them.
- Under 230 px (a container query on the head) a tab that is not open shows only its icon; the open tab keeps its word.
- No tab has a colour of its own: Shared's green stays on the tree and the rail, System's indigo is gone (`tests/designer_chrome_contrast_test.php`).

## What is the Overview's head?
- One 40 px row `#sd-overview-header`: the name button `#sd-ov-name` (icon and `#sd-overview-header-title`, `aria-expanded`), the Tree / Notes pair `#sd-ov-subtabs` (buttons with `aria-pressed`, the notes' count `#sd-notes-tab-count`), the caret `#sd-overview-caret` at the right.
- A press anywhere on the row but the pair shuts or opens the Overview (stored in `pinegrap_designer_ov_collapsed`); shut, the pair hides and the caret points up.
- The name stays "Overview" while Notes is open; it no longer turns into "Notes".
- Under 230 px the name shows only its icon; the button's `title` keeps the word. At 250 px a long translation ("Genel Bakış") ends in an ellipsis.

## What else changed in the column?
- "Recently used" is a quiet label `.sd-pal-label` with the text button "Clear" (named "Clear the recently used list"); it no longer folds. Favourites keeps its folding head.
- Palette group heads (`.sd-category-header`) and the System list's heads (`.sd-system-header`) are 11.5 px in sentence case, never `text-transform: uppercase`.
- Pins: `tests/designer_left_heads_test.php`, `tests/designer_palette_keyboard_test.php`.

Related: docs/memory/designer/designer-frame-tidy-drawing.md, docs/memory/designer/designer-palette-tabs-and-blocks.md, docs/memory/designer/designer-sticky-panel-layout.md
