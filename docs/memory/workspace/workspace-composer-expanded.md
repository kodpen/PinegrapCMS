---
name: workspace-composer-expanded
description: The composer's "Aa" expanded box (Editor.expand()): Enter a new line, Ctrl/Cmd+Enter sends, every tool shown, the per-viewer choice in localStorage, how the switch gets on the row without workspace.js
---

## What does the "Aa" switch do?
- A box made with `onEnter` (only the channel composer) has an "Aa" button on its row of tools. On: the field is taller (`.ws-rich.is-expanded`, 7.5rem up to min(24rem, 50vh)), the row shows every tool group even where the container query hides them (`.ws-composer-tools.is-expanded`, wrapping), and the hint under it reads "… Ctrl+Enter to send" (⌘ on a Mac).
- Keys there: Enter is a new line (lists and headings go on as usual), Ctrl+Enter or Cmd+Enter sends; Ctrl+Shift+Enter does not. `enterSends(keys, expanded, touch)` decides for every box that sends; the one-line box is unchanged (Enter sends, Shift+Enter or a touch screen's Enter a new line, Ctrl+Enter still sends).
- The Ctrl+/ sheet lists Enter = new line, Ctrl+Enter = send while expanded (`shortcutRows('ctrl', true)`).

## Where is the choice kept?
- `expandedSet()`: localStorage `ws-composer-expanded` = `on` (removed when off), as autoformat's switch; per browser, no schema. Every new composer (another channel, a thread) opens as the viewer left it (`expandedOn()` in the Editor). Storage refused: the choice lasts the page.

## Why is the button added by toolbar() itself?
- The composer's row is built in `assets/js/workspace.js`; `toolbar()` puts `expand` before the first group of a box with `onEnter` unless the caller names it, so the feature lives in `workspace_editor.js` alone. The hint is found by its class `.ws-composer-hint` and its own text put back when the box is made small again. `expand()` calls `announce()` so the conversation refits to the new height.

## What decided it?
- Owner answer to question 2 of #2420: (a) Enter in the expanded box is a new line, Ctrl+Enter sends. Step 5 of the composer tools plan; no schema.

## How was it checked?
- `tests/workspace_composer_expand_test.php`: `enterSends()` and the sheet rows under Node, the wiring. Headless Chromium on workspace_editor.js alone: the switch only on a sending box, Enter sends small, new line expanded, Ctrl+Enter sends, hint and stored value follow. Not Safari, Firefox, a phone or a running site.

Related: docs/memory/workspace/workspace-editor-toolbar.md, docs/memory/workspace/workspace-editor-link-keys.md
