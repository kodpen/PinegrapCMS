---
name: designer-options-panel-names
description: How controls of the visual editor's Options/Appearance panel get their accessible names (one naming pass), keyboard section headers, per-screen-size toggles, Styles inspector names
---

## How does a control in the options panel get its name?
- `row(label, ctrl)`, `mkRespRow()`, `mkNonRespRow()` and the hand-written rows print the label beside the control (`.sd-prop-label` / `.sd-sp-label`), never with `for`. `sw()` and `_cmtSwitch()` wrap the checkbox in a `<label>` without text unless `sw()` gets its third argument.
- `_sdNamePropsPanel(panel, root)` names the controls on show under `root`: every input, select and textarea without a name (no `aria-label`, no `aria-labelledby`, no wrapping `<label>` or `label[for]` with text) gets `aria-labelledby` = its row label's id; a per-screen-size control (`.sd-sp-bp-item`) gets the row label plus its `.sd-sp-bp-lbl` ("Display SM"). Ids are `sd-pl-N`. Placeholder and title do not count as a name.
- A subtree folded by an inline `display:none` (a closed section body, a closed per-size box, a row the property search hid) is skipped whole and named when it opens. `_sdPropsHasName()` never reads `ctrl.labels`: its first read after a redraw scanned the whole document (#9498).
- `_sdPropsA11yInit(panel)` is called by `renderProperties()` and installs once: a `MutationObserver` (childList, subtree, and `style` attributes) whose batch runs in a microtask over what it put on show (`_sdPropsMutatedRoots()`): the nodes added and the elements whose style changed. So the multi-selection panel, sections filled later and a section opened by its header, the property search or a toggle are covered without a call in each.
- The same batch adds Browse beside each page select on show (`_swPageBrowseDecorate(root)`); a folded one gets it when its section opens.
- A new row needs nothing: keep the label as the row's direct child with its class. Label text and classes stay as they are; the property search and the modified marking read them.

## How do section headers and the screen-size chevrons work from the keyboard?
- `sect()`, `as()` and the background-image group print `role="button" tabindex="0" aria-expanded`; the three header click handlers (options, multi-selection, text inner panel) keep `aria-expanded` in step. One delegated keydown on `#sd-properties` turns Enter / Space on a `.sd-sect-hdr` into a click.
- `.sd-sp-toggle` gets `aria-label` "More settings for {var}" (row label), `aria-controls` and `aria-expanded` from the pass; both toggle click handlers update `aria-expanded`.

## What about the Styles inspector?
- Its inputs carry `_sdT('Property')`, `_sdT('Value')`, `_sdT('Selector')` as placeholder and `aria-label`; a declaration checkbox is named `Apply {var}` (`_stylesToggleName()`); the `inline` / `overlay` section tags go through `_sdT()`.
- Borders inside the dark options panel use `var(--sd-panel-border)`, never `#e5e7eb`.

## What is not covered?
- The Overview/HTML tree is mouse-only; the modified label's click-to-reset is mouse-only; the `disk` / `inline` tags of the extract list are English.

Test: `tests/designer_panel_names_test.php`.
