---
name: design-theme-look-drawing
description: The small drawing of a look (pg_design_look_drawing() and its twin _sdLookDrawing()): what it draws from the preview row, where it shows, the twin test
module: design_themes
---

## What does a look's drawing show?
- A 48×32 SVG drawn from the look's `preview` row (`pg_design_looks()`): a card in the look's corner (a quarter of `radius`), outline (`border`; 2 or more drawn solid) and shadow (`shadow`: crisp line, soft strip, glow, two deep strips, hard offset L), a heading bar half a unit tall per 100 of `weight`, a text line, and a button in its corner (`btn_radius` / 6, 99 = pill).
- `heading`: rounded ends for `rounded`, four cells for `mono`, a foot line for `serif`. The button's label is cut out of it: one line, or three spaced blocks when `caps`.
- Drawn in `currentColor` only, never a palette colour, so it takes the text colour around it; palettes show their own swatches.
- No preview (null) draws Plain Bootstrap: `pg_design_plain_look_preview()` / `_SD_PLAIN_LOOK_PREVIEW`.

## Where is it shown?
- The Themes tab's head row in the Visual Page Editor (`_renderThemeHead()` in `style_designer_theme.js`): the design's look in place of the old brush icon, through `_sdLookDrawingOf(key)` from the kit. A custom look draws the look it is based on; no look or an unknown one draws Plain Bootstrap.
- Choose a Template's look pills (`pg_designer_template_theme_picker()`): each look before its name, shown at 36×24 (`designer_home.css`); "Template's theme" keeps its stars icon.
- Settings > Theme cards keep their own HTML sample (`_sdThemeLookDemo()`), drawn in the palette of the moment.

## Why two twins?
- The pills are printed by PHP, the head row by the editor: `pg_design_look_drawing()` (`includes/fn/design_themes.php`) and `_sdLookDrawing()` (`style_designer.src.js`) must give the same SVG. Numbers have at most two decimals and no trailing zero; a pill button has no zero-length side.
- `tests/design_look_drawing_test.php` runs the JS twin under Node for every look, Plain Bootstrap and edge rows and compares the strings; it also checks that every look has every preview key and that each key changes the drawing.

## What does it not tell apart?
- Minimal and Plain Bootstrap draw alike: their rows differ only by a 5 or 6 px button corner.
- A new look needs only its `preview` row; a new preview key is drawn once both twins read it.

Added in P2 of [[designer-palette-drawings]] (PR #8731).
