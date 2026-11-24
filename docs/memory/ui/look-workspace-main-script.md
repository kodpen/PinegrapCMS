---
name: look-workspace-main-script
description: WL2: the buttons of assets/js/workspace.js (channel head, panes, drawers, dialogs, the writing box's Send and Send later, My Tasks, settings parts) on look A's ranks; the old buttons left and why
---

## Which rank does a button of workspace.js take?
- The recipes of `plans/workspace-look-leftover.md`: a solid main action is `pg-button pg-button-fill pg-button-sm`, an outline one `pg-button-quiet`, a labelled text or link one `pg-button-ghost`, an icon-only one `pg-icon-button pg-icon-button-sm`, a red one (`theme-danger`, `fg-danger`) `pg-button-danger`. `rounded-pill`, `px-*` and `py-0` went with the old classes.
- A solid that is not the view's main action stays filled only where it was the view's one solid (Mark done, Join, Keep mine); the meeting card's Join in Notifications is a row action, so quiet.
- `button()` gives a label's icon no `me-1` when its class holds `pg-button` or `pg-seg-item`: the recipe's gap spaces it. Icons appended by hand into those buttons lost their `me-1` too.

## How are Send and Send later drawn?
- A `.pg-button-split.ws-send-group`: Send is the fill, Send later (`.ws-send-later`) the fill's `pg-button-split-more`. The old `rounded-start-pill` swap and the `.ws-send-group .ws-send-later` padding and border rule were removed.
- The microphone (`.ws-voice-mic`) is a fill too: it takes Send's place while the box is empty (`voiceSwap()`), so one fill shows at a time.
- On a phone `.ws-composer-tools .pg-button` and `.pg-icon-button` keep the 2.75rem touch height; `.ws-composer-tools .pg-button-fill` keeps Send's narrow pad (`tests/workspace_composer_phone_test.php`).

## Which chip rows became segmented controls?
- Write / View of a text file's editor (`.ws-text-tabs`), the state filter of scheduled agents (`.ws-sa-filters`, `pg-seg-wrap`) and the notes import's sources row: `pg-seg` with `pg-seg-item`, the chosen one `is-active`.

## Which old buttons are left, and why?
- The context menu's rows (`.ws-ctx-item`, shared with `workspace_editor.js`): menus are left out of the plan.
- Buttons joined to a field in an `input-group` (the checklist's Add item, the guest link's Copy, the poll's link Remove, an option's More and Remove) moved with the fields in WL8 (#PRNUM): `pg-button pg-button-quiet` (`-sm` in a small group), joined corners kept (`ui/look-workspace-fields.md`).
- Pressed toggles with a label: My Tasks' and the timeline's Agents filter (`active` + `aria-pressed`), and the agent feedback marks (`is-on`): no recipe draws a pressed labelled button (`plans/workspace-look-leftover.md`, "What does a step do when no recipe fits?").

## Which CSS followed the classes?
- `backend.src.css` rules that named `.btn` for these buttons also name `.pg-button`: the poll's send row, foot and More, a version row, the thread source head's link, a decision's jump. `.ws-head-share` is `.pg-icon-button.ws-head-share` so its warning tint beats the recipe's colour. `.ws-sa-filters` lost its flex row (the seg lays it out).

Related: docs/memory/ui/look-check.md, docs/memory/ui/look-marketplace-ranks.md
