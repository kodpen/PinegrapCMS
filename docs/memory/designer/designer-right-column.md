---
name: designer-right-column
description: The designer's right column (Options and Design tabs, 336 px default, kept width, narrow form under 300 px) and the bottom panel's closed strip and remembered state
---

## What does the right column hold?
- Two tabs, its header: Options (the selected element's panel, `#sd-rcol-options` with the search and `#sd-properties`) and Design (`#sd-rcol-design`, `initAssetsPanel()`: its inner tabs Files, the style sheets, scripts and fonts, and Themes). Each takes the whole height under the tabs; the dock toggle sits at the strip's end.
- Built by `initRightPanel()`; `_sdRcolShow('options'|'design')` switches, Left and Right move between the tabs. Paths that reveal an option (`focusClassControl()`, `_sdOpenWidgetSection()`) switch to Options first.
- Why: the Options panel showed 416 px of a 1440 x 900 window with the Design block fixed at 300 px under it. Owner (2026-11-16): `options-4-whole-screen.jpg` (git history: `designer-panels-overview.md`), D1.
- A content-level user gets no Design tab (it holds only the design's files) and no rail button for it (`_sdApplyContentChrome()`).

## How wide is it?
- 336 px by default above 1024 px (`--sd-right-width` in `style_designer.css`), 240 px at 1024 and below.
- A width the user dragged is kept in `sd_layout_v1` and wins on every screen. A layout saved with `v: 2` holds `rightW` only after a drag; an older one held the column's width on every save, so its 260 or 240 (the old defaults) is read as no choice (`_sdLayoutRightWidth()`, `tests/designer_right_column_test.php`).
- Under 300 px the column carries `sd-rcol-narrow` (a ResizeObserver on its own width, not the window's); the tabs drop their icons. The other parts' narrow forms are `designer-options-narrow.md`.

## How does the bottom panel open?
- Closed, it is one 28 px strip (chevron, "HTML · Attributes · Styles"); nothing inside is drawn, and a click anywhere on it opens it. D2.
- Open or closed is kept per browser in `pinegrap_designer_bp_collapsed` ('0' open, '1' closed), written by the chevron, the strip, the edge drag and its double-click. A browser that never chose starts closed (`_sdBpOpenRead()`).
- Below 992 px the docked panel keeps its three strips (`designer-sticky-panel-layout.md`); the folded state does not apply there.

## What stays under a docked layer?
- The selected element's size badge (`.sd-canvas-size-badge`, z-index 200 in the editor body, as the canvas wrapper makes no stacking context) drops to 50 while a side panel is docked or the bottom panel is docked (51), so the drawers, the backdrop and the sheet cover it.
