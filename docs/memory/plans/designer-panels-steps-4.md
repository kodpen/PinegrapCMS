---
name: designer-panels-steps-4
description: Plan designer-panels, steps DP17 to DP25: the page strip (the design's menu, groups, tab states, the Pages window, suggested groups), the canvas bar (zones, Preview menu, Prototype mode), close-out
---

## Which steps has the page strip?
- DP17 (S) after DP1: the design's menu. Done in #10206.
  - Existing files: `includes/designer_screen.php` (`.sd-toolbar-pages`, the gear of `#styleSettingsModal`, `$ctx['delete_button']`), `style_designer.css`.
  - Pictures: the menu open, the question of Delete, 390. Features: D45.

- DP18 (M) after DP17: the open group as a box, folded groups as chips. Done in #10245.
  - Functions: `_pgTabsRender()`. New: a pure function from the tab layout and the strip's width to what is drawn (the box, its tabs, "+N", the chips), with its test.
  - Tests: one group open; a design without groups; "Other"; a tab that does not fit behind "+N"; the open page's group opened when a page is opened from a chip.
  - Features: D46, D47, D48. Keeps K7. On a narrow strip the function draws the six steps of `designer-panels-measures-7.md`, each with its test case; picture beside `page-strip-narrow-approval.jpg`.

- DP19 (S) after DP18: tab states (`designer-tab-states.md`). Done in #10291.

- DP20 (M) after DP19: the Pages window.
  - New: a part; it takes the place of `_pgTabsAllToggle()` and `#sd-tabs-all`.
  - Tests: the filters; the search by name and address with Turkish letters folded; F2; a page dragged onto a group written through `designer/tab_layout`.
  - Features: D50, D51, D52. Keeps K9: Ctrl+P is free today.
  - Done in #10607 (`designer/designer-pages-window.md`).

- DP21 (S) after DP20: suggested groups. New: a pure function from the pages (address, kind) to the suggested groups, with its test; accepted, it is written through `designer/tab_layout`. Features: D53.

## Which steps has the canvas bar?
- DP22 (M) after DP1: the zones and the View menu. Done in #10205.
  - Functions: the bar built in `createCanvasIframe()` (`.sd-bp-bar`). The ids `#sd-vb-*` and `#sd-bp-label` stay, so handlers and shortcuts do.
  - The switch's Prototype side opens today's view with its own bar until DP24.
  - Tests: the menu's badge from the switches; the fold order by width as a pure function.
  - Pictures: beside `canvas-bar.jpg` at 1440, 1024 and 390; an e-mail design.
  - Features: D54, D55, D56, D57, D61, D62. Keeps K8.

- DP23 (S) after DP22: the Preview menu (`#sd-vb-preview`, `#sd-vb-live`, `#sd-view-page`). Features: D58 Done in #10244.

- DP24 (M) after DP23; the plan prototype-canvas has ended with its close-out PV12 (#10307). Prototype mode in the same bar. The Prototype bar's Play, zoom, Fit, "Read the links again" and Close (designer/prototype-view.md) move here; its Close button gives way to the switch, as `canvas-bar.jpg` shows. Features: D59, D60. Keeps K11. Done in #10507 (`designer/designer-canvas-bar-prototype.md`); its pictures beside `canvas-bar.jpg` not taken.

## How does the plan end?
- DP25 (S) close-out: every D and K number checked against the code with its PR; the measures and the rules that last move to `designer/` (the chrome's tokens, the Options panel, the Overview, the page strip, the canvas bar, with an overview file); this plan's files and its folder are deleted.
  - Every landed step is also compared with its accepted picture, screenshot beside drawing; the differences go to the owner as one list and fixes are cut from it (owner 2026-11-17, `workflow/decisions-made-product-73.md`).
