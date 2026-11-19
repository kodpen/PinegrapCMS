---
name: page-clicks-designer-layer
description: The designer's Clicks layer (part clicks, CM4a #10056): the canvas matched by the four folded parts, badges, tenth lines, device choice, side list, empty state; what CM4b wires in the core
module: page_clicks
---

## What does the part do?
- (#10056) `assets/js/style_designer_clicks.js`, `StyleDesigner.definePart('clicks', …)`: names each link and button of the canvas (`a[href]`, `button`, `role=link/button`, input submit/button/image) by the four parts the visitors' script uses (`page-clicks-script.md`) and pairs them with `pg_page_clicks_page()` elements by exact key (the table compares binary).
- On the canvas, one `#sd-clicks-layer` without pointer: a badge per matched element with its share of views, a line a tenth (1 to 10) with the share reaching it. Beside it (`host.panel`): device choice, views, the counted elements the canvas does not hold (C22, never dropped) and "Other elements: n clicks".
- Links are resolved against the page's own address (`OUTPUT_PATH` + page name), not the panel's. A repeated element (same four parts) gets the same badge.
- Every variant of an experiment is counted together; the panel says so (C23).

## Which functions are pure, and tested?
- `foldUrl`, `foldLabel` (twins of the server and `pg_clicks.src.js`), `name`, `match`, `lines`, `address`, `deviceOf`, `empty`, `list`, `pct`. `tests/designer_clicks_part_test.php`: the fold on `page_clicks_test.php`'s fixtures, `name` against the script's `name()`, the side list, labels with markup as text, the empty state's button, the kit-only names.

## What while the switch is off?
- C17: the Visitors section's two lines, then "Turn on" only when `sdDesign.clicks.features`; others read "A manager can turn it on…". The button posts `token` to `sdDesign.clicks.turnOn` (`visitors_clicks_switch.php`, CM3's saver, `page-clicks-switch.md`), redirect not followed, then loads the figures. No reader or saver of its own.

## What must CM4b add?
- Kit: nothing new; the part uses `_sdT`, `esc`, `sdToast`, `_pgToken`, `design`, `pages`, `activeKey`, `canvasDoc`.
- `sdDesign.clicks`, only for whoever may open Visitors (`includes/panel/designer.php`): `{ visitors: true, features: <role up to 2>, on: pg_page_clicks_switch_on(), ready: pg_page_clicks_ready(), report: <url>, turnOn: <visitors_clicks_switch.php> }`.
- `report`: a session address answering `GET ?page_id=&device=` with the `PageClicksReport` body (or `{data: …}`) of `GET /reports/page-clicks`, last 30 days. The external route needs a key, so CM4b names the address that runs `pg_page_clicks_page()` through `api_page_clicks_page_present()` under the Visitors right.
- `output.php`: `style_designer_clicks.min.js` in `pg_js_bundles()` and `clicks` in `pg_designer_parts()`, handed only with `sdDesign.clicks`.
- `style_designer.src.js`: a "Clicks" toolbar toggle (`bi-hand-index`) and a stub: `_sdPartLoad('clicks')` then `open({ panel })`, `refresh()` after each canvas draw, `reload()` on a tab switch, `close()` off.
- Styles: `.sd-clicks-badge` and `.sd-clicks-line` live in the canvas frame, which the designer stylesheet does not reach; `.sd-clicks-off` and `.sd-clicks-missing` in the panel. The look is the owner's (pictures in CM4b).
