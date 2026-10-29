---
name: widget-parts-palette
description: W20a parts palette: a Components group following the selection with a widget's parts and values, row items kept in their loop, the detail widget's placed outside it as parts
---

## What does the palette offer?
- A group at the top of the Components list (below Recently Used), redrawn from `renderProperties()` when what it offers changes (`_sdPaletteRefresh()`): "Parts of <widget>" and "Values of <widget>".
- The widget: the system widget the selection stands in; outside every widget (and not in a shared component) the page's first detail widget (`_sdPaletteDetailSid()`, kinds `SD_PART_KINDS`), whose items land outside it as its parts (`props.partOf`, [[widget-parts-anywhere-editor]]). Only for full access.
- Parts: the keys of `SW_DEFAULT_PARTS[kind]` without the `section:`/`content:` spellings and `_inner_html` twins, named by `_sdPalettePartLabels()`; a part without a name there is not offered (the test fails for one). Outside the widget only a part `_sdPartRefusal()` accepts (no form or button).
- Values: `_swBindTokenOptions()` read as if in the widget (`_sdPaletteAsIn()` through `_sdPartDrawing`), then the row values of the lists around the selection and the row positions. Left out: a `cond` (yes/no) entry, a part's token, `SD_PALETTE_SKIP` (`__page_link`, `__ref`, `__link_label`: they need settings), a repeat. `_swBindTokenOptions()` now marks a condition-only entry `cond: true`.

## What does an item create?
- `createFromPalette('widget_part', extra)` → `_sdPaletteCreate()`: a part as its builder draws it (several elements in one `div`), else `_sdPaletteValueNode()`: `_sdPaletteValueProp()` picks by the token's name: a picture address (`image`, `thumbnail`, `photo`, `picture`, `logo`, `avatar`, optionally `_url`/`_src`/`_N`) a content image bound `src` (`alt` empty), another `_url`/`_href` a link bound `href` with the value's name as words, a `_html` token a `div`, else a `span`, bound `text`. Away: `partOf` = the widget.
- Not added to Recently Used: the item names one widget.

## How are row items kept in their loop?
- loop: `main` for `SD_PALETTE_ROW_PARTS` (item form, gift card, schedule, comment stars), `list` for a list row's value or part (`__return_actions_html`), `position` for `__loop_*`. `_sdPaletteLoops(chain)` reads the loops from the place up (a recipient loop ends it); `_sdPaletteFits()` decides.
- Offered when they fit at the selection; `canDrop()` refuses a `widget_part` outside its widget (`_sdPaletteFitsAt()`: owner = sid, loops at the target; away: no shared owner, widget on the page), so a drop, a double-click and the command palette all follow it.

## Tests?
- `tests/widget_palette_parts_test.php` (Node): parts per kind and their names, values (cond, skip, loop tags, prop), loops and fit, the created node with and without `partOf`. No browser run: the group's drawing and refresh, the drop refusal on the canvas.
