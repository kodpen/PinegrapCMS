---
name: menu-scroll-rows
description: A menu's "Mark the section in view" (Bootstrap scrollspy) and "After scrolling" rows (DO22): the attributes they write, what pg_page_scroll.js does with them, defaults, lists, bytes
---

## Where are the rows?
- Options panel › Navbar Options of a `nav.navbar`, after "Smart active state": "Mark the section in view" (switch) and "After scrolling" (switch). Built by `_sdNavScrollRows()` in `style_designer.src.js`; no new bar button.
- With "After scrolling" on, four rows follow: Distance (px, 0 to 5000), Background (Unchanged, Page colour, Light, Dark, Primary), Shadow (Unchanged, None, Small, Regular, Large), Height (Unchanged, Smaller, Smallest).

## What do they write?
- Attributes only, in `props._attrs`, printed as they are by both renderers (`_render_extra_attrs()`); no prop, no CSS rule, no renderer change. Off writes nothing.
- "Mark the section in view": `data-pg-spy=""`.
- "After scrolling": `data-pg-scrolled="80"` and `data-pg-scrolled-class="shadow py-1"` when switched on (the default needs no choice). The one writer of the class list is `_sdNavScrolledClass(cls, part, value)`; `_sdNavScrolledParts()` reads it back into the three lists, so a list typed in the code view shows in the rows (a value no option has is shown as itself).
- Background values: `bg-body`, `bg-body-tertiary`, `bg-dark navbar-dark`, `bg-primary navbar-dark` (the navbar's Bootstrap 5.3.8 theme class keeps its links readable). Shadow: `shadow-none`, `shadow-sm`, `shadow`, `shadow-lg`. Height: `py-1`, `py-0`.

## What does the site do with them?
- Both markers are in `pg_page_scroll_pattern()` (and the Preview's `_SD_PAGE_SCRIPTS` row), so `assets/js/pg_page_scroll.js` (DO21, `page-scroll-components.md`) comes only to a page that holds one.
- After scrolling: once the page is scrolled the distance, the menu takes the listed classes plus `pg-scrolled`; its drawn classes of the same kind (background colour, `shadow*`, `py/pt/pb-*`, `navbar-dark/light`) step aside until the page is back above it (`scrolledClasses()`, pure, tested). `bg-opacity-*` and `bg-gradient` stay.
- Mark the section in view: the first `[data-pg-spy]` menu gets an id (`pg-spy-menu`) if it has none, and the body gets `data-bs-spy="scroll"` and `data-bs-target`, before Bootstrap's data API starts its scrollspy on load. A body that already has `data-bs-spy` is left alone; a second marked menu is not spied.
- The canvas does not load the script: the menu shows as drawn.

## How many bytes?
- A page without the markers: 0. The attributes: about 15 bytes (spy) and 60 bytes (After scrolling) of markup. `pg_page_scroll.js` grew from 4,085 to 7,045 bytes (1,663 to 2,495 gzipped). `style_designer.src.js` +6 KB of source.

## Tests?
- `tests/designer_nav_scroll_test.php`: the markers, the printed attributes, the panel writer and the site swap under Node.
