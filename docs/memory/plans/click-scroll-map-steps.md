---
name: click-scroll-map-steps
description: Plan click-scroll-map, steps CM1 to CM5: the table and counter, the script and switch, the Visitors section and report route, the designer's layer, close-out
---

## Which steps are there?
- CM1 (M) The table and the counter.
  - A migration step adds one table (a working name: `page_click_daily`), listed in `get_tables()`; the draft PR's body carries its columns and indexes for the architect.
  - Existing files: `page_view.php` (one branch beside the speed one, before the page-view payload), `job.php` (the sweep), `functions.php` (the module).
  - New: `includes/fn/page_clicks.php`, modelled on `includes/fn/page_vitals.php`: ready check, signature and its check, the parse of a beacon, the fold of a label and an address, the key, the upsert, the bound, the figures a period asks for, the sweep. `tests/page_clicks_test.php`.
  - Tests, red first: a beacon's refusals, the fold, an element counted once, the bound's "other" row, the tenth's row, yesterday's signature taken and an older one refused.
  - Features: C6 to C9, the server side of C1 to C3.

- CM2 (M) after CM1: the script and the switch.
  - Existing files: `get_page.php` (`pg_clicks_beacon_inject()` beside `pg_vitals_beacon_inject()`, before the page cache), `includes/fn/output.php` (the entry in `pg_js_bundles()`), `includes/settings/features.php` and `features.save.php` (the card).
  - New: `assets/js/pg_clicks.src.js`; the landing builds its bundle.
  - Tests: the script's naming of an element and its fold under Node on fixtures the server test shares; who gets the script (the switch, a private folder, staff).
  - Features: C1 to C5, C10, C11.

- CM3 (M) after CM1: the Visitors section and the report route.
  - Existing files: `includes/fn/visitor_periods.php` (one line in `pg_visitors_sections()`), `assets/css/pg_visitors.src.css` (its labelled block), the report rows of the API schema.
  - New: `includes/visitors/clicks.php`, `tests/visitors_clicks_test.php`.
  - Pictures: the section with data and empty, a page's view, a phone. To the owner; not marked ready before his look.
  - Features: C12, C13, C14.

- CM4 (M) after CM3: the designer's layer.
  - Existing files: `assets/js/style_designer.src.js` (the toggle and the part's registration only), the designer's panel actions (one read for whoever may open Visitors).
  - New: the part's own file (`designer/designer-parts-on-demand.md`), its test with the twin of the fold.
  - Pictures: badges and tenth lines on a page with data, the list of elements the canvas does not hold, a device choice. To the owner.
  - Features: C15, C16, C17.

- CM5 (S) close-out: every C number checked against the code with its PR; `visitors/page-clicks.md` and `ui/visitors-clicks-section.md`; this plan's three files deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- CM2 and CM3 run beside each other once CM1 has landed. CM4 waits for CM3's figures.
