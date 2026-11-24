---
name: legacy-run-starters
description: Where the legacy-editable run gets a widget's starter layout for a stock-layout page (step LE5b): built in the browser at Convert and posted, never a PHP twin or an empty tree
---

## Where does the run get a widget's starter layout?
- Architect (2026-11-24), the question of LE5 (#11270): the move screen builds the starters in the browser and sends them with Convert, as the install wizard does (`platform/design-template-install-at-setup.md`).
- At Convert the screen loads `style_designer.js`, calls `StyleDesigner.starterTree(kind)` for the kinds the dry run lists and posts the trees with the request that starts the run.
- Why: starter layouts live only in `_buildStarterTree()` of `style_designer.src.js`, 24 kinds; the run works on the server in job ticks. One source stays one source.
- Not chosen: PHP twins of every starter (thousands of lines, each later change made twice); a row with an empty tree the editor fills on first open (visitors would see an empty widget until a designer opens the page).

## What must the step keep?
- The posted trees are outside text: the start request keeps the screen's gate and CSRF check, and each tree passes the same cleaning and checks a tree saved from the editor passes before the run keeps it. A kind the dry run did not list is dropped.
- The run keeps the trees with its own state (a file beside its cursor when they are too large for the cursor), so a later tick, a paused run taken up again or another browser needs no second post. They are removed when the run ends.
- A page whose kind has no kept tree is not converted and never gets an empty widget: the run leaves it as it is and lists it in its report with the reason.
- The server puts the page's own settings into the posted starter with `pg_layout_widget_tree_apply()`; the browser sends the plain starter only.
- The editor script is loaded when Convert is pressed, not with the screen.
- `StyleDesigner.starterTree()` is public API: never removed, its signature unchanged.

## Tests?
- Unit: a posted tree of an unlisted kind dropped, a missing kind leaving its page unconverted, the settings put into a posted tree. The browser part is checked on a sandbox by the step's PR.

Related: docs/memory/designer/legacy-stock-types.md, docs/memory/plans/legacy-editable-steps.md
