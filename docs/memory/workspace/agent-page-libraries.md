---
name: agent-page-libraries
description: The site's libraries a live page names (Chart.js #8830, the chart_guide preset DG5): the list, how a call names one, how live_page.php puts the site's own copy in, never an address
---

## How does a page use a library?
- `libraries` (a list of names, at most 5) on create and edit; left out on an edit, the version before's stay. Only names in `ws_page_libraries()` (`chartjs`: Chart.js 4.4.7, `assets/lib/chartjs/`), else 422 on `libraries`. Kept in the version's document; GET answers `libraries`. Built in #8830.
- `live_page.php` puts the site's own copy in ahead of the page (`ws_page_with_libraries()`): an inline `<script>` right after the doctype. A source holding `</script` or `<!--` is left out. The frame allows no script from any host, so a page never loads one from an address.
- Another library: its file under `assets/lib/` (`path`) or a function writing its script (`build`), a row in `ws_page_libraries()`; `needs` names libraries `ws_page_libraries_check()` puts in ahead of it. `tests/workspace_agent_page_test.php` checks it.

## What does the chart_guide preset do?
- `chart_guide` (DG5 of `plans/agent-drawing-guides.md`) brings `chartjs` and then `ws_page_chart_guide_script()`: Chart.defaults set to the drawing guide's look (`agent-canvas-guide.md`), its values from `ws_canvas_guide_values()` through `ws_page_chart_guide_values()`, never a second copy.
- Type and marks are the guide's board pixels times 2/3 (a board is read at half size, a page at its own), text never under 12; font Inter then the system sans (the frame loads no font).
- A dataset that sets no colour gets its series colour (a donut a colour per slice, past the fifth `other`); a filled line the series mixed 14 % into the background as a solid colour. Colours a page sets stay.
- Dark or light: `<html data-theme>` when set, else the reader's `prefers-color-scheme` (the frame does not know the panel's theme); either changing recolours every chart. `--pg-bg`, `--pg-ink`, `--pg-ink2`, `--pg-ink3`, `--pg-grid`, `--pg-font` etc. on `<html>` for the page around the charts; `PgChartGuide.series(i)`, `.palette()`, `.use('dark'|'light'|'')`.
- Chart.js copies scale defaults into each chart at its start, so a switch rewrites a scale colour only where it is the old set's own.
