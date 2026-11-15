---
name: custom-form-open-slots
description: Custom form slots opened (CB4, #9806): the step buttons a designer builds (form_step_nav), the steps and CAPTCHA drawn on the canvas as the site prints them
module: custom_form_steps
---

## What did CB4 open?
- Step CB4 (#9806); the census: [[canvas-closed-blocks-widgets]]. Steps on the site: [[custom-form-steps]].
- Step buttons: a tree. CAPTCHA and the step progress: settings only, drawn true on the canvas.

## How are the step buttons a tree?
- A box bound to section `form_step_nav` ("Step buttons" in the Section binding list; its children kept, `_SD_SECTION_KEEPS_CHILDREN`). Select a step break: under "Section binding (system widget)" the button "Design the step buttons" (`_sdFormAddStepNav()`) puts the starter (`_sdFormStepNavStarter()`) after the last element of the break's container. Offered only while the form has none.
- Starter as the built-in buttons: `div.pg-cf-step-nav.d-flex.gap-2.mt-3`, a Back button (`btn-outline-secondary`) shown on `has_prev_step`, a Next (`btn-primary ms-auto`) on `has_next_step`, both `type="button"`.
- The flags are the buttons' function, never a class: `has_prev_step` gets `data-pg-step-back`, `has_next_step` gets `data-pg-step-next`. A child without a flag shows under every step. The flags are in `SW_VISIBILITY_FLAGS.custom_form`; a flag on an element outside the box does nothing (the form renderer runs no visibility pass).

## What does the site print?
- `pg_cf_apply_steps()` takes every `form_step_nav` out of the tree (`pg_cf_take_step_nav()`, the first one kept) before it groups the steps; then under each step `pg_cf_step_nav_node()`: a copy, children dropped by the two flags (`_eo_apply_visibility_bindings()`), marked, the box with `data-pg-step-nav hidden`. The steps script is unchanged.
- No box: the built-in `_pg_cf_steps_nav_html()`; a tree without a box is byte for byte as before. One step or none: the box never prints.
- `tools/check_bindings.php` reads `includes/fn/custom_form_steps.php` too, where the two flags are evaluated.

## What does the canvas draw?
- `_sdFormStepPlan()` / `_sdFormStepSamples()` in `buildNodeEl()`'s children loop, for a container of a custom_form holding breaks, two steps or more: the progress line before the first step, the built-in Back / Next at the end of every step (left out once the form has the box). Inert samples, never saved.
- A break with a title is drawn as its title with its classes (no label box); an empty one keeps the box.
- CAPTCHA: the sum question with "Shown when CAPTCHA is on in the site settings; staff never get it." (`_sdFormSectionSample()`, the comments' sample).

## How was it checked?
- `tests/custom_form_step_buttons_test.php`: per step, without the box, one step, no break, a second box dropped. Sandbox pictures before and after at 1440 and 390, dark, and the designed buttons.
