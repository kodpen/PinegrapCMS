---
name: translations-screen-narrow
description: Translations screen at 1024 px and on a phone (390 px): the head in three lines, the scope button with the rail's bottom sheet, tiles that scroll sideways, the list's side padding (TS8)
---

## What changes at 1024 px?
- The rail narrows to 240 px (under 1200 px) and stands above the list under 992 px; the head and the automation line stay one line each.
- The six filter tiles stay one row of equal width (8 px apart, tighter padding); a long label such as "Keeping pages out of search" breaks into lines. The row keeps its grid: tick, source, translation, state column.

## How does the phone draw it? (under 768 px)
- Head: the language button and "More" on the first line, the tabs the full width on the second, the fill button ("Translate pending", or "Scan for new texts" with "By hand") the full width under them. Beside an engine "Scan for new texts" is the first item of "More" (`data-pg-tr-scan-proxy` presses `#pg_tr_scan`).
- The rail leaves the page: the scope button `.pg-tr-scope` (the current row's icon, name and count, a caret) opens it as a sheet from the lower edge with 44 px rows; its backdrop or Escape closes it.
- The tiles are one row that scrolls sideways; the rows stack and the selection bar shows icons (`translations-screen-row.md`, `translations-screen-selection.md`).
- The list's rows and notes keep 18 px from the card's sides (14 on a phone) through `.pg-tr-list > *`; the rules between rows run the full width.

## How was it checked?
- TS8 (2026-11-28) shot `translations.php` at 1024 and 390 px, light and dark, against board I of `translations-screen-drawing.md`: no horizontal scroll on Texts, Activity or Glossary. Before it the tiles fell to three a row under 1200 px, the phone head kept the desktop order, the rail stood inline and the rows touched the card's sides.

Related: docs/memory/ui/translations-screen-frame.md, docs/memory/ui/translations-screen-drawing.md
