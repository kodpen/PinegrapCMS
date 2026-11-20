---
name: page-clicks-designer-layer
description: The designer's Clicks layer (part clicks #10056, wired #10715): toolbar toggle and panel, the read page_clicks, sdDesign.clicks, badges, tenth lines, side list, empty state
module: page_clicks
---

## What does the part do?
- (#10056) `assets/js/style_designer_clicks.js`, `StyleDesigner.definePart('clicks', …)`: names each link and button of the canvas (`a[href]`, `button`, `role=link/button`, input submit/button/image) by the four parts the visitors' script uses (`page-clicks-script.md`) and pairs them with `pg_page_clicks_page()` elements by exact key (the table compares binary).
- On the canvas, one `#sd-clicks-layer` without pointer: a badge per matched element with its share of views, a line a tenth (1 to 10) with the share reaching it. In the panel: device choice, views, the counted elements the canvas does not hold (C22, never dropped) and "Other elements: n clicks".
- Links are resolved against the page's own address (`OUTPUT_PATH` + page name), not the panel's. A repeated element (same four parts) gets the same badge.
- Every variant of an experiment is counted together; the panel says so (C23).

## How is it opened in the editor? (#10715)
- A "Clicks" toolbar toggle (`#sd-clicks-btn`, `bi-hand-index`, before Undo), made by `_sdClicksPartInit()` only when `sdDesign.clicks` and the part are handed over. On: a panel `.sd-clicks-panel` over the canvas' right edge, its body is `host.panel`; `_sdPartCall('clicks', 'open', …)`. Off or its close button: `close()`.
- `_sdClicksDrawn()` at the end of `renderCanvas()`: `refresh()` soon after a full draw, `reload()` when the active tab changed. A one-node redraw keeps the layer.
- Badge and line look: `_sdClicksCanvasStyle()` puts `<style id="sd-clicks-style">` into each canvas document, since `style_designer.css` does not reach the frame. Panel look (`.sd-clicks-*`): `style_designer.css`.

## Where do the figures come from?
- Sub action `page_clicks` of the designer endpoint (`includes/panel/designer.php`, a JSON POST through `_pgApiPost`): `{page_id, device?}` gives `{status, page}`, `page` in the `PageClicksPage` shape of `GET /reports/page-clicks` (`api_page_clicks_page_present()`), last 30 days (`pg_page_clicks_designer_window()`). Refused without `pg_page_clicks_may_view()`. Open to content-level operators; a clean sub action for the page cache.
- The part's `ask(pageId, device)` builds the request; null for a page not saved yet.

## Who gets it?
- `sdDesign.clicks` = `pg_page_clicks_designer_config()` (`includes/fn/page_clicks.php`, built in `includes/designer_screen.php`): null without the Visitors right (role up to 2, or `manage_visitors`), else `{visitors, features: role up to 2, on, ready, turnOn: visitors_clicks_switch.php}`. The part is in `sdDesign.parts` only with it.

## What while the switch is off?
- C17: the Visitors section's two lines, then "Turn on" only when `features`; others read "A manager can turn it on…". The button posts `token` to `turnOn` (CM3's saver, `page-clicks-switch.md`), redirect not followed, then loads the figures. No reader or saver of its own.

## Which functions are tested?
- Part: `foldUrl`, `foldLabel`, `name`, `match`, `lines`, `ask`, `deviceOf`, `empty`, `list`, `pct` in `tests/designer_clicks_part_test.php`. Server: the config and the window in `tests/page_clicks_test.php`.
