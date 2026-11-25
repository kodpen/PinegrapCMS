---
name: designer-hide-on-sticky-from
description: Style › Size and display's "Hide on" (Phone, Tablet, Desktop) and "Sticky from" a size (DO3): Bootstrap's d-* and sticky-{size}-top classes, written and read back by pg_size_style.js
---

## What do the rows do?
- "Hide on", under Display: three buttons, Phone (XS and SM, below 768 px), Tablet (MD) and Desktop (LG and up), as the canvas bar's widths. Pressed where every selected element is hidden at all of those sizes; a click hides or shows that group on every element in one undo step. Plan `designer-options-census-steps.md` DO3.
- "Sticky from", under Position, shown only while Position is Sticky: "Every screen size" or "From MD up" and so on.

## What do they write?
- Classes only, no User Styles rule. `PGSizeStyle.hideOnWrite()` writes the fewest Display classes (`d-none d-md-block`, `d-md-none d-lg-flex`, `d-lg-none`) in the place of the ones it replaces; a size shown again takes the display the element has at other sizes, `block` where it has none of its own. Read back by `PGSizeStyle.hideOn()` from the classes, so a Display set per size in the Display row shows here too.
- A `d-{size}-block` that only shows the element again after a hidden size, with no display before it, is no display of its own: showing every size again removes it, so the element draws as before.
- "Sticky from" at every size is `position-sticky`, as the Position list writes; from a size up it is Bootstrap's `sticky-{size}-top` (sticky, top 0, z-index 1020 from that size). The Position list reads `sticky-*-top` as Sticky and drops it when another position is chosen (`_SD_STICKY_FROM` in its `data-stylegroup`). `sticky-top` alone stays outside both rows.

## How is it loaded?
- The core holds `<div data-sd-hideon hidden>` and `<div data-sd-stickyfrom hidden>`; the part `spacing` fills them in `sizes()` (`_hoDraw`, `_sfDraw`) when Size and display is shown, also where a custom style is off. Bundle growth (#PR): spacing part 27.7 → 31.6 KB (gzip 9.8 → 10.9 KB), core +0.4 KB.

## Where is it tested?
- `tests/designer_hide_sticky_test.php`: reading and writing the groups, the fewest classes, sticky read and write, the wiring. Not unit-tested: the buttons in a browser.
