---
name: designer-options-overview
description: Start file of the designer-options-* files: what the Options panel is, what binds it (plan designer-panels, K1 to K12), its parts by their on-screen label, which file holds what, how a part is added
---

## What is the Options panel?
- The right column's first tab of the Page Design Studio: the selected element's options. Redrawn by plan designer-panels (owner 2026-11-16, `plans/designer-panels.md`): the identity row, the tabs Content, Style and Advanced, section cards, rows with a changed dot, the search.
- What no step may lose is K1 to K12 of `plans/designer-panels-rules.md`: the same saved tree and classes, the accessible names, only panels in view drawn, the content level's locks, the open state per browser (`sd_cs_<sid>`).

## Which file holds what?
- The identity row and the section cards (own section first and open, closed ones with a count and summary): `designer-options-section-cards.md`.
- The three tabs, which section goes where, the tab kept per kind, only the open tab built: `designer-options-tabs.md`.
- Style and Advanced: section names, order and which open by themselves: `designer-options-style-advanced.md`. Advanced › Layout by context: `designer-options-layout.md`.
- A row's controls: button groups, the changed dot and its reset, the size badge, fields that refuse a bad value: `designer-options-controls.md`. Style › Spacing's box: `designer-spacing-box.md`.
- Several elements selected: `designer-options-multiple.md`.
- Accessible names and keyboard section heads: `designer-options-panel-names.md`.
- "Search or add an option…" (results, Enter, the class offered): `designer-options-search.md`; the label filter under it: `designer-options-panel-search.md`.
- "Only what I changed" (the switch at the bottom, the list of changed rows, "Reset all"): `designer-options-review.md`.
- "Copy style", "Paste style" (the brush) and the quick strip on Content: `designer-options-copy-style.md`.

## How is a part added?
- New code a page does not need to open goes into a part (`designer-parts-on-demand.md`), a decision into a pure module tested under Node (`pg_spacing_box.js`, `pg_options_search.js`); the core gets the hooks.
- A new section gets a key (`_sdSectKey()`) so `_SD_OPT_TAB_OF` places it in its tab; a renamed one keeps its card id (`designer-options-style-advanced.md`).
- Texts through `_sdT('…')`, the Turkish with `tools/tr_add.php`.
