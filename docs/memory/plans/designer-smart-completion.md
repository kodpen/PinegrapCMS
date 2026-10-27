---
name: designer-smart-completion
description: Plan (2026-10-24): deterministic smart completion for designer CSS/HTML/JS and class inputs, a per-element custom style rule, fixes first
---

## What is being built?
- One pure, text-free predictor (`assets/js/pg_style_predict.js`, lazy) ranks classes, CSS properties, values, units, tokens, selectors, attributes and the Bootstrap JS API from the element, its parents and the design. It feeds panel inputs (light dropdown) and CM6 completion sources.
- A "Custom style" field (Attributes panel, Styles panel "This element" card): one User Styles rule keyed by a generated `pgs-` class.
- CM6 (`@codemirror/autocomplete` 6.20.3) has the engine, not Bootstrap, design or theme knowledge. No npm package or bundle rebuild.

## Which rules apply?
- No heavier designer page: predictor and `designer_vocab/bootstrap5.json` load on first focus or first editor; plain inputs never load CM6.
- Extra CSS lives in User Styles, never inline or in `<style>` (designer-block-style.md).
- The predictor returns codes; `style_designer.js` maps them to `_sdT()`.
- One `saveState()` before a change; a rule-only change rides in `style_custom_css`; `_sdCanEditNode` in render, `_sdMayEdit` on commit.

## How do we keep clear of other sessions in style_designer.js?
- New logic goes into new files (predictor, vocabulary, pure CSS text helpers) with Node tests; `style_designer.js` gets wiring only.
- Each claim names the functions it touches; before a step read the open PRs' hunks there (`pulls/<n>/files`); one open step per region; merge `development` just before the PR.

## Which decisions are made?
- Owner (2026-10-24): deterministic first, AI decided later, never per keystroke; the custom style field is a growing textarea with the light dropdown (no CM6 in panel inputs); off inside shared components and system widgets, reason shown; fixes F1-F4 and the predictor base (F6, 1, 3) run side by side.
- Defaults (owner may change): class `pgs-` + 6 characters; a copied element shares the rule ("N elements use it" + Split); base rule first; theme-breaking classes last, with the replacement; `class_suggestions.js` replaced; inline hint off by default; Export keeps `pgs-` rules; `!important` allowed with a warning.
- The facade resets the lezer cache (`resolveInner(0, 0)`) before every completion source (#1702).
- The vocabulary is generated from the local Bootstrap 5.3.8 and kept by a regenerate-and-compare test.

## What is open (owner)?
- An optional User Styles CSS tab (a CM6 editor over the whole User Styles text) and the inline hint (off by default).
- The `pg_design_ai_known_class()` switch to the predictor and vocabulary.
- An AI suggest button: its own plan, not this one.

## Which steps landed?
- All: F1 #1721, F2 #1729, F3 #1739, F4 #1755, F5 #1765, F6 #1702; 1 #1704, 2 #1728, 3 #1722, 4 #1754, 5 #1778, 6 #1785, 7 #1793, 8 #1770, 9 #1779. Behaviour is in [[code-editor-completion]], [[designer-bootstrap-vocabulary]], [[designer-class-dedupe]], [[style-predictor]], [[style-suggest]], [[style-fields]], [[custom-style]].

## Where to start?
- Nothing is claimable now: every step has landed. What is left (CSS tab, inline hint, `pg_design_ai_known_class()` switch) waits for the owner's go, and each needs wiring in `style_designer.js`, so it follows the region rule above.
- When the owner decides, record the answer under "Which decisions are made?", cut the work into steps here, then start from docs/memory/core/code-editor-facade-and-bundle.md (CSS tab) or [[style-predictor]] (switch). If the owner drops them, move the decisions to the topic files and delete this plan.
