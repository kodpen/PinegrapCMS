---
name: designer-options-panel-names
description: How controls of the visual editor's Options/Appearance panel get their accessible names (one naming pass), keyboard section headers, per-screen-size toggles, Styles inspector names
---

## How does a control in the options panel get its name?
- `row(label, ctrl)`, `mkRespRow()`, `mkNonRespRow()` and the hand-written rows print the label beside the control (`.sd-prop-label` / `.sd-sp-label`), never with `for`. `sw()` and `_cmtSwitch()` wrap the checkbox in a `<label>` without text unless `sw()` gets its third argument.
- `_sdNamePropsPanel(panel)` names them all after each redraw: every input, select and textarea without a name (no `aria-label`, no `aria-labelledby`, no `<label>` with text) gets `aria-labelledby` = its row label's id; a per-screen-size control (`.sd-sp-bp-item`) gets the row label plus its `.sd-sp-bp-lbl` ("Display SM"). Ids are `sd-pl-N`. Placeholder and title do not count as a name.
- `_sdPropsA11yInit(panel)` is called by `renderProperties()` and installs once: a `MutationObserver` (childList, subtree) that reruns the pass in a microtask, so the multi-selection panel and sections filled later are covered without a call in each.
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
