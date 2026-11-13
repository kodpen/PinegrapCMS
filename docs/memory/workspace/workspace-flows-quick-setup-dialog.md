---
name: workspace-flows-quick-setup-dialog
description: The quick setup dialog (workspace_flow_quick.js, QS2 #9244): own script in the flow bundle, the first stage (areas, start cards, search, Only when), PGWsFlowQuick and later stages
---

## Where is it?
- `assets/js/workspace_flow_quick.js`, served in `workspace_flow.min.js` after `workspace_flow.js` (`pg_js_bundles()`). CSS: the `.wsf-quick*` block before the Share dialog's in `backend.src.css`. Texts: `fl_quick_*` in `ws_flow_js_strings()`; `tests/workspace_flows_canvas_test.php` reads this script too.
- Server and choices: `workspace-flows-quick-setup.md`; the drawing: `plans/flows-quick-setup/quick-setup.html`.

## How is it opened?
- `window.PGWsFlowQuick.open(kit, quick, options)`: kit is `window.PGWsKit`, quick is `flows.quick` of `ws_flow_js_config()`, `options.operators` the comparisons' labels (`steps.operators`), `options.start` a start to begin on, `options.onClose`. It answers false with no catalogue. No screen opens it yet: the ways in are QS5.

## What does the first stage do?
- Rail: the catalogue's areas in order, a line where `part` changes, a count for the site's own areas; it opens on the first `site` area, or a given start's own area (never Often used).
- Cards: the area's starts as native radios in a `radiogroup`, the sentence (`label`) and the short name under it; the tile shows the area's icon. Search: every word in the sentence, name or area name, Turkish letters folded as `foldText()`, each start once; the rail goes quiet meanwhile.
- "Only when": shown when the start has values; a row is a value, a comparison of its type (as the canvas's `VALUE_OPS`), and what it is compared with (none for empty / not empty), up to `limits.only_when`. Another start clears the rows.

## How do the later stages join?
- A stage script sets `window.PGWsFlowQuick.stages.<steps|summary> = { draw(body, state, kit) }`; Next shows only when the next stage is there and a start is chosen; Back returns. `PGWsFlowQuick.choices()` gives start, config (an event start's `event`) and the sent `only_when`.
- Pure parts in `PGWsFlowQuick.pure`, run in Node by `tests/workspace_flow_quick_js_test.php`.

## What was checked?
- A browser look on a harness page with the real CSS and a test catalogue, light, dark and 390 px; no run on a site.
