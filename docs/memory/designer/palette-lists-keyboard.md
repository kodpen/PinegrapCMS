---
name: palette-lists-keyboard
description: Palette lists from the keyboard: focusable rows Enter/Space insert, header toggle buttons with aria-expanded, named search boxes, reachable bulk box, tip card on focus
---

## How does a palette row work from the keyboard?
- Every `.sd-drag-item` (Components, Blocks, Recently Used through `_sdPaletteItemHTML`, the Shared rows, the System "New" and existing rows) carries `tabindex="0" role="button"`. A new row kind does the same (`tests/designer_palette_keyboard_test.php` counts them).
- One delegated keydown on `#sd-components` (`_sdWirePaletteKeys()`, wired once in `initLeftPanel()`): Enter or Space on a focused row dispatches the row's own `dblclick`, so the four insert wirings, `_sdInsertSpot()` and its view-mode check stay the only insert path (`palette-insert-at-selection.md`). In select mode (`.sd-bulk-on`) it dispatches a click, which the select-mode guard turns into a pick.
- A focused row stops Delete, Backspace and N from reaching any document-level shortcut handler: they would delete the canvas selection or open a note. (On the editor document `_sdShortcutKeydown` is currently bound only to the canvas, so nothing listens yet; the stop keeps it safe if it is bound again.)

## How do the foldable headers work?
- The header's title is a real `<button class="sd-category-toggle" aria-expanded>` inside the `.sd-category-header` div, not `role=button` on the div: the Recently Used header also holds its Clear button. The click still lands on the header's listener.
- `_sdPalSyncExpanded(cat)` keeps `aria-expanded` right after a click and when a search unfolds a category.
- The Shared/System "Site-wide" toggle redraws its list; `_paletteToggleFocus` hands focus to the redrawn toggle (the Shared list redraws after a request). The static "In this design" and "New" headings do not fold and stay plain.

## What else is named or reachable?
- The two search inputs are named Search components / Search blocks, both clear buttons Clear search, the Recently Used clear button Clear.
- The bulk-select box is the v6 `check` class and the row's only tab stop in select mode (the row goes to `tabindex=-1`). The guard lets a click on the box keep its default: a cancelled checkbox click puts the box back after `pick()` set it, so it used to show the opposite of the selection.
- `_sdPalTipWire()` shows the description card on `focusin` after the same pause as hover and hides it on `focusout`; Escape on a row hides it.
- `style_designer.css`: `:focus-visible` rings on rows, header toggles and the clear buttons.

## What is not covered?
- The command palette (`_getCpPaletteItems()`) still lists components, content and layout only; blocks, shared components and system widgets are reached through the focusable rows.

Related: docs/memory/designer/designer-palette-tabs-and-blocks.md, docs/memory/ui/panel-accessible-names.md
