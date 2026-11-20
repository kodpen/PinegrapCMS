---
name: designer-panels-overview
description: Start file of the Page Design Studio's redrawn chrome (plan designer-panels, ended #10760): Options panel, Overview tree, page strip, canvas bar; what binds them, which file holds what
---

## What was redrawn, and why?
- Owner (2026-11-16, discussion "Görsel Tasarımcı geliştirme", messages 7186 to 7297): the Options panel, the Overview tree, the page strip and the canvas bar were redrawn from drawings he approved; "önerilerin yerinde hepsi kabul". Reason: on long pages he got lost in the Overview, folded pages were hard to reach, the canvas settings and Prototype were mixed in one bar.
- Measured before (145-node page): Options showed 416 px of 2,300 to 3,900; the Overview 502 rows open, 13 visible, a 6 px drop band; the strip 9 of 37 tabs; the canvas bar 19 alike controls in one row.
- Built in DP1 to DP24 (#10179 to #10719, 2026-11-16 to 2026-11-20). Which feature number was built where: `designer-panels-numbers.md`.

## Where are the drawings?
- In git history only: `git show d1069d8475:docs/memory/plans/designer-panels/<sheet>.jpg` (and `boards.html`, their source). Sheets: `options-1-content` to `options-6-multiple-selection`, `measures-1-tokens` to `measures-4-sections-rules`, `overview-1-long-page`, `overview-2-drag-move`, `page-strip`, `page-strip-narrow-approval`, `canvas-bar`, `phone-pages-move`.
- Every measure the code needs is written in the topic files below; no value is read off a picture.
- Where the build differs from its drawing: `designer-panels-differences.md`, a list the owner sees before any fix is cut.

## What binds every change to these four areas?
- K1 to K12 and the working rules: `designer-panels-keep.md`.
- Colours only from the `--sd-*` tokens (`designer-chrome-tokens.md`); sizes, type, corners and control states from `designer-chrome-measures.md`. The chrome is always dark; no light theme.
- Owner (2026-11-17, poll 127): additions of other plans waited for each area's new shape ("Dört alana dokunanlar beklesin"). All four have landed, so an addition now builds on the new shape, its tokens and its measures.

## Which file holds what?
- Right column, its Options and Design tabs, the bottom strip: `designer-right-column.md`.
- Options panel: start file `designer-options-overview.md` (identity row, tabs, sections, controls, spacing and corner boxes, search, "Only what I changed", copy style, several selected). Under 300 px: `designer-options-narrow.md`.
- Overview tree: `overview-long-page.md` (rows, sections, sticky path), `overview-chains-hints.md`, `overview-search-map.md`, `overview-drop-targets.md`, `overview-long-drags.md`, `overview-move-without-drag.md`, `designer-overview-follows-selection.md`.
- Page strip: `designer-page-strip.md` (the design's menu, the narrow order, which file holds the rest).
- Canvas bar: `designer-canvas-bar.md` (zones, View menu, measures), `designer-preview-menu.md`, `designer-canvas-bar-prototype.md`, `designer-narrow-screen-bars.md`.
- Parts loaded on demand (`spacing`, `optsearch`, `review`, `treesearch`, `move`, `pages`): `designer-parts-on-demand.md`.

## How is a part of these areas added?
- New code a page does not need to open goes into a part, a decision into a pure function with its test first (`designer-panels-keep.md`).
- Texts through `_sdT('…')`, the Turkish with `tools/tr_add.php`; the terms of `designer-turkish-terms.md` bind.
- A screen no drawing shows is drawn first and is the owner's to approve, through the architect.
