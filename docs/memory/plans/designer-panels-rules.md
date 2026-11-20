---
name: designer-panels-rules
description: Plan designer-panels: what today's editor does that no step may lose (K1 to K12), every step's rules, the screens no drawing shows yet, and what a step's PR body carries
---

## What does today's editor do that no step may lose?
- (K1) What an option writes: the saved tree, the class names and every option's written value. A page opened and saved without a change is the same before and after a step.
- (K2) Every control's accessible name from the one naming pass, section heads as keyboard buttons (`designer/designer-options-panel-names.md`).
- (K3) Only panels in view are drawn, debounced while a field is typed into (`designer/designer-panels-on-demand.md`, `designer-canvas-redraw-paths.md`). No step adds a full redraw to an option change or a selection (issue #10013).
- (K4) Content level: `_sdPanelLockReason()`, `_sdPanelLock()`, the refused commit points (`designer/designer-content-roles-panels.md`).
- (K5) Docking below 992 px, one layer open at a time, no rule that stacks panels, the rail made from the tab strip: a new tab gets its icon in `_SD_RAIL_ICONS` (`designer/designer-sticky-panel-layout.md`).
- (K6) The Overview follows the selection (`_sdTreeRevealSelection`); a multiple selection with Shift and Ctrl.
- (K7) Tab groups: the stored shape, its endpoint, both normalisers and `proto` (`designer/designer-tab-groups.md`).
- (K8) A bar hides labels, never controls (`designer/designer-narrow-screen-bars.md`); the canvas chrome's places (`designer-canvas-chrome-placement.md`).
- (K9) Every shortcut of today (Ctrl+Shift+P the command palette, Ctrl+Alt+P the preview, O the outline). A new one is taken only where today has none in that focus; a clash stops the step.
- (K10) A section's open or closed state per browser (`sd_cs_<sid>`); the per-screen-size toggles.
- (K11) The Prototype view's K1 to K9 (`designer/prototype-view-rules.md`).
- (K12) Opening the editor loads no part (`designer/designer-parts-on-demand.md`, `tests/designer_parts_test.php`).

## Which rules does every step keep?
- New code that a page does not need to open goes into a part (`assets/js/style_designer_<part>.js`, named in `pg_designer_parts()`), a decision into a pure module tested under Node as `pg_proto_hotspots.js` is; the core file gets the hooks.
- A colour comes from a token (`designer-panels-measures.md`); a size is the measure files' number.
- Texts: `_sdT('English')`, the Turkish with `tools/tr_add.php`. The drawings' Turkish words are samples; `designer/designer-turkish-terms.md` and `editor-turkish-terms.md` bind.
- A pure function gets its test first.
- Additions of other plans to the four areas: `designer-panels-additions.md`.
- No schema change, no new endpoint; a need for either comes back as a question to the architect.
- A step that finds its feature cannot be built as written stops and asks; it does not leave the feature out.

## Which screens does no drawing show yet?
- None today; the sheets are listed in `designer-panels-drawings.md`.
- A screen a step meets without a drawing is asked from the drawings' author through the architect and is the owner's to approve. Until its drawing is in `plans/designer-panels/`, that state draws what it draws today inside the new shell, and the PR says so.

## What does a step's PR body carry?
- A table of the D and K numbers its step names, each with the test or the picture that shows it. A number with nothing beside it is not done: the PR stays a draft.
- Pictures beside the drawing, same crop: 1440; a 1024 px screen (the right column at its narrowest); 390 with the panel docked. An element with a 60-character name and the longest Turkish labels of its kind.
- The test design: thirty pages or more in three groups, a page of 500 tree rows fourteen levels deep.
- The `?sd_perf=1` numbers for selecting an element and changing one option on that page, before and after: not slower.
- In words: what could not be verified.
