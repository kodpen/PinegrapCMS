---
name: click-scroll-map-steps
description: Plan click-scroll-map, steps CM1 to CM5: the table and counter, the script and switch, the Visitors section and report route, the designer's layer, close-out
---

## Which steps are there?
- CM1 (M) The table and the counter. Done: #9902 (`visitors/page-clicks.md`; the columns: architect, agent channel message 6041).
  - A migration step adds one table (a working name: `page_click_daily`), listed in `get_tables()`; the draft PR's body carries its columns and indexes for the architect.
  - Existing files: `page_view.php` (the clicks part taken beside the speed part of one beacon, before the page-view payload), `job.php` (the sweep), `functions.php` (the module).
  - New: `includes/fn/page_clicks.php`, modelled on `includes/fn/page_vitals.php`: ready check, signature and its check, the parse of a beacon, the fold of a label and an address with the rules of C5 and C6, the key, the upsert, the bound, the figures a period asks for, the sweep. `tests/page_clicks_test.php`.
  - Features: C12 to C15, the server's side of C1 to C3, C5 and C6.

- CM2 (M) after CM1: the script and the switch. Done in #9946.
  - Existing files: `get_page.php` (`pg_clicks_beacon_inject()` beside `pg_vitals_beacon_inject()`, before the page cache), `assets/js/pg_vitals.src.js` (the one beacon both share), `includes/fn/output.php` (the entry in `pg_js_bundles()`), `includes/settings/features.php` and `features.save.php` (the card with its privacy sentence).
  - New: `assets/js/pg_clicks.src.js`; the landing builds its bundle.
  - Features: C1 to C11, C16.
  - The switch through `pg_page_clicks_switch_on()` and `pg_page_clicks_switch_save()` (CM3 #9913, `page_clicks.php`); the card writes no reader or saver of its own (architect, message 6222).

- CM3 (M) after CM1: the Visitors section and the report route. It lands before CM2 and adds the switch's reader and saver (message 6222). Done in #9913.
  - Existing files: `includes/fn/visitor_periods.php` (one line in `pg_visitors_sections()`), `assets/css/pg_visitors.src.css` (its labelled block), the report rows of the API schema.
  - New: `includes/visitors/clicks.php`, `tests/visitors_clicks_test.php`.
  - Tests: a label holding markup printed as text in the section and the route.
  - Pictures: the empty state with its two lines and button, the section with data, a page's view, a phone. To the owner; not marked ready before his look.
  - Features: C7, C17 (Visitors), C18, C19, C20.

- CM4 (M) after CM3: the designer's layer, cut by files because the core files are held by other PRs.
  - CM4a: new files only. Done in #10056. The part `clicks`, `assets/js/style_designer_clicks.js`: the twin fold and the canvas match (C21), badges, tenth lines, device choice, the side list of elements the canvas does not hold (C22), the empty state with its button through `visitors_clicks_switch.php` (CM3's saver, C17), variants not told apart (C23). `tests/designer_clicks_part_test.php`; `visitors/page-clicks-designer-layer.md`.
  - CM4b: the toggle, the stub, `pg_js_bundles()` and `pg_designer_parts()`, the read `page_clicks` in `includes/panel/designer.php`, `sdDesign.clicks` in `includes/designer_screen.php`, the panel styles. Done in #10715 (`visitors/page-clicks-designer-layer.md`). No pictures (`workflow/look-pictures-none.md`).
  - The switch through CM3's reader and saver in `page_clicks.php` only (message 6222).

- CM5 (S) close-out: every C number checked against the code with its PR; `visitors/page-clicks.md` and `ui/visitors-clicks-section.md`; this plan's five files deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- CM2 and CM3 run beside each other once CM1 has landed. CM4 waits for CM3's figures.
