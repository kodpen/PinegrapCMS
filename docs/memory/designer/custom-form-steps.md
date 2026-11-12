---
name: custom-form-steps
description: Steps of a designed form (FL2, FL3): the Step break (section form_step), pg_cf_apply_steps() fieldsets, Back/Next, progress, pg_cf_steps_script(), the sessionStorage draft and Start over
module: custom_form_steps
---

## What is a step break?
- The Form palette's "Step" (`CF_PALETTE.step`): a `div` bound to section `form_step` (`_bindings.section`, never a class), its text the step title, its classes the legend's (default `h5 border-top pt-3 mt-4`). It is no field: `_pg_cf_controls()` reads only input/select/textarea. The Section binding list of a custom_form names it.

## How does the site draw it?
- `_render_system_widget_custom_form()` calls `pg_cf_apply_steps($tree_decoded)` after the section bindings. The first container whose direct children hold a break is split by `pg_cf_step_groups()`: what stands before the first break (introduction, messages) stays above every step; each break opens a `<fieldset class="pg-cf-step" data-pg-step="n">` with its title as `<legend>`; an empty step (two breaks in a row, a break at the end) is dropped. Breaks anywhere else are removed.
- Two or more steps: a progress `<nav data-pg-step-progress>` (count line "Step 1 of 4" with `aria-live`, a Bootstrap progress bar, the titles as a list with `aria-current="step"`) and Back / Next (`data-pg-step-back`, `data-pg-step-next`) per step; the last step has no Next, its own submit sends. All of it is drawn `hidden`, so without script the whole form shows and posts. One `<form>`, one post: the server is unchanged.
- One step: a fieldset with its legend, no navigation, no script. No break: the tree is untouched (byte-for-byte as before, test guarded).

## What does the script do?
- `pg_cf_steps_script()`, once per page, only for a stepped form: shows the progress and buttons, hides all steps but the first (or the one holding a control the server marked `aria-invalid`). Next checks the shown step's controls that are enabled, not hidden and `willValidate` (a control hidden by a Shown-when rule is disabled, so skipped), writes the refused fields' text ([[form-field-errors-before-sending]]); Back never checks. Focus goes to the new step's first control, else its legend.
- The history is never touched (no `pushState`): the browser's back key leaves the page as before.
- On submit a failing control in another step brings that step forward (capture listener) before `pg_cf_validation_script()` focuses it. That script focuses `input/select/textarea:invalid` only: a fieldset holding an invalid control matches `:invalid` and takes no focus.

## What does the browser draft keep?
- `pg_cf_draft_key($page_id, $kind)` = `pg-cf-draft:<form|item>:<page id>`; the renderer passes it to `pg_cf_apply_steps()` except in the editor's Preview (`$mode === 'background'`) and the SEO pass, and the progress `<nav>` carries it as `data-pg-draft` with a hidden "Start over" button. A single-step form has no draft.
- The script saves the answers and the shown step to `sessionStorage` (never `localStorage`) on every input, change and Next/Back, and restores them at load unless the server refilled a refused submission (`aria-invalid`), then fires `change` so the rule scripts re-read the form. Never kept: file, password and hidden inputs (a signature's value, tokens), `autocomplete="cc-*"` card fields, the CAPTCHA box (`.captcha`, its honeypot included) and a signature pad.
- A submission that is not prevented and "Start over" (reset, `change`, then the draft removed, or the save listener writes it back) clear it. Off inside a same-site frame (`window.frameElement`): the editor canvas and prototype play.

## Tests?
- `tests/custom_form_steps_test.php`: breaks at the start, in the middle, at the end, none, in a row, nested container, single step, tree untouched without a break, escaping, script once, renderer and palette wiring, the draft key, markup with and without it, what the draft never keeps.

Related: docs/memory/designer/custom-form-render.md, docs/memory/designer/designer-form-palette-and-fields.md
