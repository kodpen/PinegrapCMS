---
name: designer-chrome-tokens
description: The Page Design Studio chrome's --sd-* colour tokens in style_designer.css: which token is for what, the measured ratios, the four areas that read tokens only, and the test that checks them
---

## Where are the editor chrome's colours?
- In the `:root` block at the top of `assets/css/style_designer.css`, as `--sd-*` tokens. The chrome is always dark; there is no light set.
- The values are the measure sheet of the Options panel drawings (`measures-1-tokens.jpg`, git history: `designer-panels-overview.md`); the sizes beside them: `designer-chrome-measures.md`.

## Which token is for what?
- Grounds: `--sd-panel-bg` the panel; `--sd-raised-bg` an open section card; `--sd-raised-bg-2` the active tab and a badge's ground; `--sd-input-bg` a field, a button group, the tab holder.
- Lines: `--sd-panel-border` and `--sd-input-border` the border; `--sd-border-strong` the strong border.
- Text: `--sd-text`; `--sd-label` a row's label; `--sd-text-muted` secondary text; `--sd-icon-quiet` only an icon that says nothing by itself, never text.
- Accent: `--sd-accent` (and `--sd-accent-rgb`) the changed dot, a badge, an icon, a selected row's tint; `--sd-accent-strong` with `--sd-on-accent` for every ground that carries a white label (selected segment, Publish, primary buttons, count badges); `--sd-accent-soft`, `--sd-accent-line` the soft ground and line.
- States: `--sd-warning` (screen-size badge, on its ground `--sd-warning-soft`; locked and note marks, the margin box), `--sd-success` (shared, editable, the padding box), `--sd-danger`.
- `--sd-shadow` is the shadow colour; `--sd-focus-ring`, `--sd-dot-ring`, `--sd-segment-shadow` are whole box-shadow values.

## How is a translucent colour written?
- `color-mix(in srgb, var(--sd-text) 6%, transparent)` for a light hover layer, `var(--sd-shadow)` the same way for a shadow, `rgba(var(--sd-accent-rgb), .12)` for an accent tint; other amber layers are `color-mix()` from `--sd-warning`, not new tokens. No `rgba(255,255,255,…)` or hex.
- White on the plain accent `#5b9bff` is under 3:1: a white label always stands on `--sd-accent-strong`.

## Which ratios do the tokens reach?
- On the panel: text 14.5:1, secondary text 5.8:1. The quiet icon is 2.97:1 (2.68:1 on a card), under 3:1 on purpose: never text, never the only sign of a state or an action (that takes `--sd-text-muted`); the test pins at least 2.9:1. The screen-size badge's text on `--sd-warning-soft` reaches 4.5:1 over the panel and a card. A row's label 7.4:1 on a section card (8.2:1 on the panel). White on the strong accent 5.2:1.
- Text, label, secondary text, the accent and the three states reach 4.5:1 on all four grounds.

## Which rules read tokens only?
- The page strip (`.sd-tab*`, `.sd-tabs-*`), the canvas bar (`.sd-bp-bar`, `.sd-vb-*`), the Overview (`.sd-tree*`, `.sd-overview*`, `.sd-ov-*`, its Notes list `.sd-notes-*`) and the Options panel (`#sd-properties`, `.sd-prop*`, `.sd-sect-*`, `.sd-sp-*`, the colour and background pickers, the spacing box `.sd-spb-*`, `.sd-align-*`, `.sd-tr-*`).
- A custom property set inside a rule is a token too: the tab group colours (`--sd-tg`) are the user's palette and stay as they are.
- Other parts of the file still write their own colours (995 literals at #10179, most in the Theme rows, the file list, settings dialogs and the AI panel), and the Overview's markup in `style_designer.src.js` has inline colours.

## What checks it?
- `tests/designer_chrome_contrast_test.php`: `test_designer_chrome_measures()` checks every token's value and every pair above; `test_designer_chrome_areas_read_tokens_only()` refuses a hex, `rgb()`/`rgba()` with numbers or a named colour in a rule of the four areas. A new rule of those areas takes a token; a missing colour is a new token in the `:root` block with its ratio checked.
