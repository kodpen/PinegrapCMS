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
- User Styles CSS tab, the `pg_design_ai_known_class()` switch, an AI suggest button (own plan).

## Steps (one PR each, in order)?
- F1 User Styles range parser and splice writer, Create as one undo step (`_stylesCreateRule`, card edit ~22975-23244): tests/designer_user_styles_test.php. Done #1721.
- F2 Attributes undo before the change, style row on Enter or blur (~23484-23606). Done #1729.
- F3 Bracket and quote-aware `_parseInlineStyle`: tests/designer_image_size_test.php. Done #1739.
- F4 Code view style apply drops printed sizing (`_sdCodeSetAttr`): tests/designer_code_view_attrs_test.php.
- F5 Attributes and Styles read-only at content level (builds on #1479, merged).
- F6 done #1702: facade primer, `addCompletionRanker`, css/js sources inside HTML, swatches ([[code-editor-completion]]).
- 1 done #1704: vocabulary from tools/build_designer_vocab.php ([[designer-bootstrap-vocabulary]]).
- 2 done #1728: breakpoint-aware dedupe ([[designer-class-dedupe]]).
- 3 done #1722: predictor ([[style-predictor]]).
- 4 Loader and class field; `class_suggestions.js` removed (clean_up.php).
- 5 Attribute rows and Styles panel inputs.
- 6 Custom style model and Styles "This element": tests/designer_custom_style_test.php.
- 7 Custom style row in the Attributes panel.
- 8 CM6 html/css sources; 9 CM6 Bootstrap JS source.
- Optional: User Styles CSS tab (after F1), inline hint.

## Where to start?
- F3 next (then F4, F5, 2); step 4 builds on 1 and 3 (merged). Facade: docs/memory/core/code-editor-facade-and-bundle.md.
