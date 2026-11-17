---
name: designer-panels-drawings
description: Plan designer-panels: the sheets in `plans/designer-panels/`, what each shows, where its measures are as text, which board of `boards.html` it is and how a sheet is made from its board
---

## Which sheets are there?
- Options: `options-1-content.jpg`, `options-2-style-advanced.jpg`, `options-3-search-review.jpg`, `options-4-whole-screen.jpg` (boards 1 to 4).
- The measure sheets: `measures-1-tokens.jpg`, `measures-2-anatomy.jpg`, `measures-3-controls.jpg`, `measures-4-sections-rules.jpg` (boards 5 to 8); as text `designer-panels-measures.md`, `-measures-2.md`, `-measures-3.md`.
- Overview: `overview-1-long-page.jpg`, `overview-2-drag-move.jpg` (boards 9 and 0). Page strip: `page-strip.jpg` (a). Canvas bar: `canvas-bar.jpg` (b).
- The Options panel under 300 px: `options-5-narrow-column.jpg` (c), as text `designer-panels-measures-4.md`.
- Several elements selected in the Options panel: `options-6-multiple-selection.jpg` (d), as text `designer-panels-measures-5.md`; DP5.
- The Pages and Move windows on a 390 px phone: `phone-pages-move.jpg` (e), as text `designer-panels-measures-6.md`; DP20 and DP16.
- A screen no sheet shows yet: `designer-panels-rules.md`.

## How is a sheet read?
- The measures are written as text so no value is read off a picture. Where picture and text differ, the step asks.
- The Turkish words on a sheet are samples (`designer-panels-rules.md`).

## How is a sheet made from its board?
- `plans/designer-panels/boards.html` holds every board with its styles; `boards.html?b=<board>` shows one, 1280 by 900. `boards.css` repeats the styles of the Options and Overview boards and is not read by it. The icons are read from `pinegrap/assets/fonts/bootstrap-icons/`.
- A sheet is its board's screenshot at twice the size (2560 by 1800), a JPEG of quality 80, taken in a headless Chromium on a machine with the Inter font and no Segoe UI (the boards' font list starts with Segoe UI). Made that way again, boards c, d and e give their sheets byte for byte.
- The twelve first sheets (boards 1 to 9, 0, a and b) were drawn with Segoe UI: made again with Inter, their letter widths differ slightly.
- A board is changed in `boards.html` and its sheet made again in the same PR. A new sheet, or a changed one, is the owner's to approve first (`designer-panels-rules.md`).
