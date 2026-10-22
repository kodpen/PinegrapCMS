---
name: designer-canvas-controls
description: Which canvas controls run as on the page (_SD_CANVAS_CONTROLS) and why a click on their icon or label reaches them; the theme toggle's icon beside the editor toolbar.
---

## Which controls run on the canvas as on the page?
- `_SD_CANVAS_CONTROLS` in `assets/js/style_designer.js`: Bootstrap's data-api hooks (`[data-bs-toggle]`, `[data-bs-target]`, `[data-bs-dismiss]`, `[data-bs-slide]`, `[data-bs-slide-to]`, `[data-bs-theme-value]`) and the platform's own controls (`[data-pg-theme-switcher] button`, the theme toggle). The theme switch is an `<input>`, already left alone as a form control.
- A new platform control bound by a script of its own (not by a data-api attribute) is added to this list, or a click on its icon or label never reaches it.

## Why does a click on a control's icon or label reach it?
- Every node of the tree has its own `.sd-wrap`, so the icon inside a button is a node of its own. Its wrapper's click handler used to call `stopPropagation()` for anything not "interactive", and the button's own listener (the theme toggle's, from `pg_theme_switcher.js`) never ran: only the button's thin edge switched the theme.
- A button's text is drawn as `.sd-virtual-text`, and its click listener stopped every click ("don't let the wrapper select the parent button"). Bootstrap's Popover and Tooltip listen on the element itself, not by delegation, so the Popover button never opened.
- Now the element wrapper counts a click inside `_SD_CANVAS_CONTROLS` as interactive (it selects without stopping the click), the shared component wrapper leaves it alone, and the label lets it go on with `e._sdHandled = true` so the wrappers around do not select the parent instead. A plain button's label still keeps its click; double-click still edits the text in place.

## Why does the theme toggle skip `.sd-tb` when it looks for its icon?
- On the canvas the toggle's icon is wrapped with the editor's toolbar, whose drag handle is a `.bi` and comes first. `button.querySelector('i, .bi')` found the handle: the toggle drew the sun on the handle and kept the moon. `bindToggle()` takes the first `i` / `.bi` outside `.sd-tb`; a published page has no `.sd-tb`, so nothing changes there.

## What tests cover it?
- `tests/designer_canvas_controls_test.php`: the control list and where it is used, the label's click order, and `pg_theme_switcher.js` run under Node on a small DOM stand-in (skipped without Node).
