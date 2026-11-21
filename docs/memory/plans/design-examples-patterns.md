---
name: design-examples-patterns
description: Plan (owner 2026-11-20): the Design examples screen finished with whole patterns: folded settings pane, starting-point sheet, form drawer with tabs and foot, connect row, phone width; DX1-DX5
priority: 8
---

## What is asked?
- Owner (2026-11-20, in the architect's window): "bir ara tasarım örnekleri sayfası bile yapılmaya başlanmış ama ne dikkate alınmış ne de tamamlanmış" (`workflow/screen-described-in-plan.md`).

## What is there today?
- `design_examples.php` draws look A's parts (type, buttons, segments, tiles, fields, cards, rows) in a light and a dark pane (`ui/look-design-examples.md`). Its own top card lists as not drawn: dialogs and drawers, the settings modal, dashboard cards, report screens, the Workspace, phone widths.
- So a step finds a button to copy and no screen to copy: how the parts are put together is in words only (`ui/screen-composition-rules.md`).

## What is built?
- (X1) A "Patterns" group of sections under the parts, each a whole piece drawn live in both panes with its classes under it, as the parts are.
- (X2) The settings pane: purpose line, folded rows with tile, name, state line, pill and chevron, one open; a list of things as rows with their actions.
- (X3) The connect row: the state pill in its states (connected, a sign-in missing, not connected with its button); a list's empty state.
- (X4) The starting-point sheet in three steps (cards with an icon and a line, details, done) and the numbered steps drawer.
- (X5) The form drawer: head, tabs as the segmented control, groups with labels, a folded "Advanced", the foot with Save, Cancel and the danger button; the confirm it opens.
- (X6) A phone pane: the settings pane and the drawer at 390 px.
- (X7) The top card's "not drawn yet" list loses what is drawn; each pattern has the test the parts have (`tests/look_examples_test.php`).

## Which steps are there?
- DX1 (M) first, after AT1 of `plans/assistants-settings-tidy-steps.md` has landed (its fold helper and row style): X1, X2, X3.
- DX2 (S) done #10885: X4. The sheet's rules became the recipe "Look A: starting-point cards" in `backend.src.css` (`.pg-start`, `.pg-start-head`, `.pg-start-acts`, `.pg-start-advanced`, `.pg-start-copy`); the examples stand in the Drawer section's frame.
- DX2b (M) done #10930: the e-mail settings drawing's parts as patterns (`plans/design-examples-patterns-mail.md`), before EM1; recipes and classes in `ui/look-mail-patterns.md`.
- DX3 (M) after DX2 and after AA3 has landed (its drawer): X5. It also moves New application's markup (`includes/api/app_new_view.php`, the one-time sheet in `api_settings.php`) to the recipe names and deletes the inline `.api-start*`, `.api-new-advanced` and `.api-secret-box` rules of `api_settings.php` (architect, 2026-11-20).
- DX4 (S) after DX3: X6, X7.
- DX5 (S) close-out: `ui/look-design-examples.md` corrected, each line of `ui/screen-composition-rules.md` names its pattern, this plan deleted.

## What binds it?
- `includes/fn/look_examples.php` and `design_examples.php` are entered by one step at a time.
- A pattern copies the markup of the screen that built it and adds no look of its own; a rule it misses is asked of the architect.
- No drawing and no approval round: the patterns are the drawings the owner said yes to (`workflow/decisions-made-panel-12.md`), built.
