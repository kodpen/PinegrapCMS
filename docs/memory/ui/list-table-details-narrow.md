---
name: list-table-details-narrow
description: List table driver below 1024 px: the details sheet follows only the switch pressed on the page and opens once a row is picked; the kept switch is the wide window's
---

## Why did a phone open the list as an empty sheet?
- The "Detaylar" switch is kept per user and list in localStorage (`{order, hidden, details}`, `list-table-driver-browser.md`). Below 1024 px the panel is a fixed sheet over the list (`list-table-layout.md`), and the driver opened it from the kept value whatever the width: a switch left on at a desktop covered the phone's list on every load with only "pick a row" in it, and closing it there turned it off on the desktop too (#9031).

## How does it behave now?
- `assets/js/pg_list_table.js`: a wide window follows the kept switch as before. Below 1024 px (`matchMedia('(max-width: 1023.98px)')`, the CSS breakpoint) the sheet follows `this.sheet`, set by the switch and the close button on that page and never stored, and shows only once a row is picked (click or keyboard). The rule is the pure `detailsOpen(kept, narrow, sheet, picked)`, tested in Node by `tests/list_table_js_test.php`.
- `aria-pressed` on the switch shows the state for the current width; crossing 1024 px redraws the panel for the new width.
