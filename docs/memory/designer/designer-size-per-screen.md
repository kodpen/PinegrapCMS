---
name: designer-size-per-screen
description: A length per screen size (DO2): the size chooser on Size and display and Text, @media rules on the pgs- class, what moves to the base rule, the canvas at the size, the dot
---

## Which rows take a value per screen size?
- Style › Size and display: Width, Height, their Min and Max, and Position's Top, Right, Bottom, Left. Style › Text: Font Size. Options census DO2 (#11571) (`options-panel-census.md`).
- Each of those two sections has the Spacing box's size chooser (XS, SM, MD, LG, XL, XXL) in its head while it is open. One chosen size (`_sdSpSize`) serves Spacing, Size and display and Text until the editor closes.
- A size holding a value of any selected element shows a dot under its name (`.sd-spb-size.is-has`), on the Spacing chooser too.

## What does a value at a size write?
- `assets/js/pg_size_style.js` (window.PGSizeStyle), pure: a rule on the element's custom style class (`custom-style.md`) inside `@media (min-width: 576px | 768px | 992px | 1200px | 1400px)`, one rule per size, in size order after the base rule; XS is the base rule `.pgs-xxxxxx { … }`.
- Bootstrap 5.3 has no per size class for these rows (`w-md-*` does not exist), so every row writes a rule; none writes a class.
- Emptied, the declaration goes; a rule left empty goes with its `@media`; an `@media` holding other rules stays. A value with `;`, `{` or `}` outside a string is refused.
- Font Size at a size offers the same list; a class is written as its size (`fs-3` → `min(1.3rem + 0.6vw, 1.75rem)`, the class's own responsive size) and read back to the class, `fs-*` before `display-*` (fs-1 and display-6 are the same size).

## Why does a value move into the base rule?
- An inline style and Bootstrap's `fs-*` (`!important`) beat any User Styles rule. So the first value at a size moves the element's every-size value (the inline Width prop, the `fs-*` class) into the base rule, in the same undo step. An element never given a size value draws as before, byte for byte.
- While the class holds a row by size or in its base rule, XS writes the base rule too; otherwise XS writes the prop or class as before.
- An element without a `pgs-` class gets one on its first value (`_sdNewCustomStyleClass(text)`).

## Where is it off?
- Where a custom style is off (a system widget, a shared component, a component placement): no chooser, the rows hold one value for every size.
- A picture's Width and Height are its attributes: they stay one value for every size.

## What does the chooser do to the canvas?
- Choosing a size puts the canvas at it through the canvas bar's own width buttons (`_sdCanvasToSize()`: 375, 576, 768, 992, 1200, full width), unless the canvas already shows that size. XXL is the full width, at least 1400 px.
- The panel is drawn again at the new size (`_sdSpSizeSet()` → `renderProperties()`); the focus goes back to the size in the new head.

## How is it loaded?
- In the part `spacing` (`style_designer_spacing.min.js`: the spacing, corner and size modules, then the part). Each section holds `<div data-sd-szbox hidden>`; `_sdSpBoxBind()` runs `_sdPartCall('spacing', 'sizes', [_sdSzHost(…)])` once the section is shown. At XS with nothing held by size, the rows' fields stay the core's; otherwise the part puts its own field in their place (`.sd-sz-at`), the inherited value as the hint ("50% (from MD)").
- The host's `commit(text, changes)` writes User Styles and every element's class and props as one undo step, then `render()`.

## Where is it tested?
- `tests/designer_size_style_test.php`: writing in size order, read back, the value in force, held sizes, clearing, refused values, the font classes as values, the wiring. Not unit-tested: the fields and chooser in a browser.
