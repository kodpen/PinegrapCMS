---
name: designer-panels-keep
description: What a change to the Page Design Studio's Options panel, Overview, page strip or canvas bar may never lose (K1 to K12 of plan designer-panels) and the rules every such change keeps
---

## What may no change lose? (K1 to K12)
- (K1) What an option writes: the saved tree, the class names, every option's written value. A page opened and saved without a change is the same before and after.
- (K2) Every control's accessible name from the one naming pass, section heads as keyboard buttons (`designer-options-panel-names.md`).
- (K3) Only panels in view are drawn, debounced while a field is typed into (`designer-panels-on-demand.md`, `designer-canvas-redraw-paths.md`). No full redraw on an option change or a selection (issue #10013).
- (K4) Content level: `_sdPanelLockReason()`, `_sdPanelLock()`, the refused commit points (`designer-content-roles-panels.md`).
- (K5) Docking below 992 px, one layer open at a time, no rule that stacks panels; the rail is made from the tab strip, a new tab gets its icon in `_SD_RAIL_ICONS` (`designer-sticky-panel-layout.md`).
- (K6) The Overview follows the selection (`_sdTreeRevealSelection`); several elements are selected with Shift and Ctrl.
- (K7) Tab groups: the stored shape, its endpoint `designer/tab_layout`, both normalisers and `proto` (`designer-tab-groups.md`).
- (K8) A bar hides labels, never controls (`designer-narrow-screen-bars.md`); the canvas chrome's places (`designer-canvas-chrome-placement.md`).
- (K9) Every shortcut: Ctrl+Shift+P command palette, Ctrl+Alt+P preview, O outline, P the Prototype mode (`prototype-back-key.md`), `/` the options search, Ctrl+P the Pages window, M the Move window, Ctrl+F the tree search inside the Overview. A new one only where none exists in that focus.
- (K10) A section's open or closed state per browser (`sd_cs_<sid>`); the per-screen-size toggles.
- (K11) The Prototype view's K1 to K9 (`prototype-view-rules.md`).
- (K12) Opening the editor loads no part (`designer-parts-on-demand.md`, `tests/designer_parts_test.php`).

## Which rules does every change keep?
- New code a page does not need to open goes into a part (`assets/js/style_designer_<part>.js`, named in `pg_designer_parts()`); a decision into a pure module tested under Node as `pg_spacing_box.js`, `pg_options_search.js` are; the core file gets the hooks.
- A colour is a token (`designer-chrome-tokens.md`), a size the number of `designer-chrome-measures.md`.
- A pure function gets its test first.
- A PR that changes how one of the four areas looks shows it beside its drawing (git history, `designer-panels-overview.md`) at 1440, 1024 and 390, with a 60-character element name and the longest Turkish labels; it names what it could not verify.
- A slower selection or option change is refused: `?sd_perf=1` numbers before and after on a page of 500 tree rows fourteen levels deep.

## Which two copies are known?
- The Turkish fold of the tree search (`_tsFold()` in `style_designer_treesearch.js`, DP13 #10303) and of the options search (`PGOptionsSearch.fold()` in `pg_options_search.js`, DP8 #10658) are the same mapping in two parts. Kept apart at the close-out: merging would make the tree search part load the options search module. A change to one is made in both, with both tests.
