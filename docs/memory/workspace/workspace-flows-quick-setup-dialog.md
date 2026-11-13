---
name: workspace-flows-quick-setup-dialog
description: Quick setup dialog (workspace_flow_quick.js, QS2 #9244, QS3 #9278): first stage (areas, starts, search, Only when), second (recipes, kinds, a step's setting, One more step)
---

## Where is it?
- `assets/js/workspace_flow_quick.js`, served in `workspace_flow.min.js` after `workspace_flow.js` (`pg_js_bundles()`). CSS: the `.wsf-quick*` block before the Share dialog's in `backend.src.css`. Texts: `fl_quick_*` in `ws_flow_js_strings()`; `tests/workspace_flows_canvas_test.php` reads this script too.
- Server and choices: `workspace-flows-quick-setup.md`; the drawing: `flows-quick-setup-look/quick-setup.html`.

## How is it opened?
- `window.PGWsFlowQuick.open(kit, quick, options)`: kit is `window.PGWsKit`, quick is `flows.quick` of `ws_flow_js_config()`, `options.operators` the comparisons' labels (`steps.operators`), `options.start` a start to begin on, `options.home` (channel), `options.onDone`, `options.onClose`. It answers false with no catalogue. No screen opens it yet: the ways in are QS5.

## What does the first stage do?
- Rail: the catalogue's areas in order, a line where `part` changes, a count for the site's own areas; it opens on the first `site` area, or a given start's own area (never Often used).
- Cards: the area's starts as native radios in a `radiogroup`, the sentence (`label`) and the short name under it; the tile shows the area's icon. Search: every word in the sentence, name or area name, Turkish letters folded as `foldText()`, each start once; the rail goes quiet meanwhile.
- "Only when": shown when the start has values; a row is a value, a comparison of its type (as the canvas's `VALUE_OPS`), and what it is compared with (none for empty / not empty), up to `limits.only_when`. Another start clears the rows.

## What does the second stage do? (QS3 #9278)
- Drawn in this script. `state.chosen.steps` [{id, kind, label, config, steps}], `state.at` the id of the step whose setting is shown, '' while a kind is chosen; the row and the stage label number steps in order, held ones after their box.
- Kinds: a row of the steps chosen (each opens its setting, the place being chosen last), the recipes that fit the start while no step is chosen (`recipesFor()`, as `ws_flow_quick_recipes_for()`), then the catalogue's groups, each kind the palette offers (`PGWsFlow.boxOffered()`) as a button with its tile.
- A kind adds a step with the palette's settings (`PGWsFlow.boxConfig()`), id `step1`… (`freeId()`, never start or read); a recipe adds copies of its steps (`fromRecipe()`). Both open the step's setting.
- A step's setting: its kind's name, "Remove this step", and the canvas's own fields through `PGWsFlow.boxForm()` (`workspace-flows-box-form.md`) on `graphOf()`: start, the start's Read a record box, the steps laid as `ws_flow_quick_graph()` lays them; the box's config is the step's object, its title the step's label.
- "One more step" in the footer (a setting shown) goes back to the kinds; refused past `limits.boxes` (start, steps, a loop's End box). Back from the kinds shows the last step; Next needs a step.
- A step is added at the end; the Summary puts it inside a condition or loop.

## How does it end?
- The third stage, the Summary: `workspace-flows-quick-setup-summary.md`. `PGWsFlowQuick.choices()` gives what the dialog would send (`pure.sent()`).
- Pure parts in `PGWsFlowQuick.pure`, run in Node by `tests/workspace_flow_quick_js_test.php`.

## What was checked?
- A browser look on a harness page with the real CSS and a test catalogue, light, dark and 390 px (QS2, QS3: the fields of a recipe's steps, a value of the Read a record box); no run on a site.
