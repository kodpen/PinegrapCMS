---
name: designer-options-layout
description: Advanced › Layout in the Options panel draws its rows by context (DP6, #10504): flex container rows, flex child rows under "parent: flex", the note naming hidden rows, "Make it a container"
---

## Which Layout rows does an element get?
- `_sdLayPlan(ctx, held)` in `style_designer.src.js` (block "Layout by context: pure helpers") answers from `_sdOptCtx()`: `flex` (the element is a flex container) and `parentFlex` (its parent is one).
- A flex container: Flex Container, Direction, Wrap, Justify, Align Items, Align Content, Gap. A child of one: Align Self, Order, Grow, Shrink, Fill, after a divider. Both: both groups. Neither: no row, only the note and "Make it a container".
- The table of rows is `_SD_LAY_ROWS` (id, group, the name the note uses); `rowCss()` builds them from `_layRows` in the plan's order, each row the `mkRespRow()` of before, writing the same classes (K1).

## Is a row with a value ever hidden?
- No. `_sdLayHeld(cls, prefix, values)` reads which rows hold a class at any screen size; a held row is always drawn, so nothing set is out of sight. The note then names only the rows still hidden.

## What does the note say?
- `_sdLayNoteHtml(plan, several)`: "This element is not a flex container; the direction, line wrapping, alignment and gap rows are hidden." and "Its parent is not a flex container; the own alignment, flex order, grow and shrink rows are hidden."; on several elements "Not every selected element is a flex container…" and "…has a flex parent…". Names come from `_sdLayName()`, joined by `_sdLayList()` ("a, b and c").
- The "Make it a container" button (several: "Make them containers") sits in the note while the element is not a flex container and holds no container row.

## What does "Make it a container" write?
- `_sdLayMakeCls(cls)`: `d-flex`, or a display class turned to its flex form at its size (`d-md-block` to `d-md-flex`, `d-none` kept, so `d-none d-md-block` gives `d-none d-md-flex`); with no base `d-flex` or `d-none` left, `d-flex` is added (`d-md-grid` gives `d-md-flex d-flex`). One undo step (`saveState()` then `_sdRenderNode()`; several: `_sdMcCommit()` on the elements not flex). View mode nudges; a content-level lock refuses (K4).
- The click goes through `_sdControlsInit()` (`data-sd-lay-make`).

## What counts as a flex container?
- `_sdOptIsFlex(type, cls)`: `d-flex` or `d-inline-flex` at any size; else a base display class (`d-block`, `d-grid`, `d-none`…) says no; else a grid row or a Bootstrap class that is flex by itself (`row`, `hstack`, `vstack`, `nav`, `navbar`, `btn-group`, `input-group`, `list-group`, `card`, `pagination`… in `_SD_FLEX_CLASSES`). A rule in the design's own stylesheet is not read. The same test opens Layout by itself (`designer-options-style-advanced.md`).

## What does the card head show?
- "parent: flex" as a tag (`_sdSectTag()`, `.sd-sect-tag`) on Layout's head while the parent is a flex container, drawn only while the card is open, as the drawing shows. The card id stays `Flexbox` (K10).

## How does the several-element panel decide?
- `renderMultiProperties()` puts the AND of the elements' context in `_sdSectBuild.ctx` (as the open rule) and their joined classes in `_sdSectBuild.lay`; a row any of them holds is drawn.

## Where is it tested?
- `tests/designer_options_tabs_test.php`: the four cases and the held rows, the note's sentences and button, the flex form of display classes, the flex components, and the wiring in the source. `tests/designer_turkish_terms_test.php` keeps the Wrap row's own key.
