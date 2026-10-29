---
name: workspace-editor-link-keys
description: The link tip's Text field (linkText()), the Ctrl+/ shortcut sheet (openKeys(), shortcutRows(), keyLabel()) of the Workspace writing boxes since #2101, and the selection bar acting on click
---
## What does the link tip's Text field do?
- `openLink()` shows a Text field above the address, filled with the selection's words or the clicked link's own. `linkText(typed, shown, url)` decides: the same words (spacing aside) keep the selection or link as it is (marks, chips: `linkRange()`); other words replace it as one `<a>`; an empty field, or a caret, takes the address (without `mailto:`). An edited link's words change inside `changed()`, so one Ctrl+Z reverts address and words.

## What does Ctrl+/ open?
- `shortcut()` catches Ctrl/Cmd+/ (`key === '/'`, a shifted key on Turkish Q, or the keypad's /) on every surface and opens `openKeys()`: a sheet in the `tip` slot (`ws-link-tip ws-keys-sheet`, `role="dialog"`) listing `shortcutRows()`: B, I, Shift+X, Shift+H, E, K, Z, Y, then Enter/Shift+Enter (Enter sends where the Editor has `onEnter` and no touch screen; none on table cells), then Ctrl+/. Keys written by `keyLabel()` as the toolbar titles (⌘ on a Mac). Escape or its close button puts the focus and caret back on the surface; a click outside closes it. Labels `keys_*` in `ws_editor_js_strings()`.
- Ctrl/Cmd+B, I, Shift+X, Shift+H, E, K, the selection bar, the browser's `formatBold/Italic/StrikeThrough` and table and checklist cells go through `formatSurface()`.

## When does a tool on the selection bar act?
- On click (`showBar()` in `workspace_editor.js`); its mousedown is only prevented, so the selection stays where it is, as on the row under the box. Before, the mark or link tip ran inside mousedown: a press could not be slid off and a click-only activation (a screen reader's) did nothing (#PR).

## How was it checked?
- `tests/workspace_editor_link_keys_test.php` runs `linkText()`, `keyLabel()`, `shortcutRows()` under Node and holds the wiring. Headless Chromium on `workspace_editor.js` alone: a caret link takes the address, a selection prefills and is replaced by new words, an edited link's words revert with one undo, the sheet takes the focus and Escape gives it back. Not Safari, Firefox or a phone.

Related: docs/memory/workspace/workspace-editor-toolbar.md
