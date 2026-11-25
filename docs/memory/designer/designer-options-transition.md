---
name: designer-options-transition
description: "Transition" in Shadow and effect as built (DO11): the three rows, the rule in User Styles inside the no-preference block, the look's values, under a state, reduced motion, the part, the cost
---

## What does the Transition sub-head hold?
- In "Shadow and effect", after Focus Ring, under the sub-head "Transition" (`_sdTransRowsHtml()`): "Transition" (None, Colours, Shadow, Size and place, All). None is the default: no transition until one is chosen.
- Once chosen, "Duration" (a slider 0 to 1,000 with its number in ms) and "Easing" (Default, then the Interactions list: Ease out, Ease, Ease in, Ease in and out, Linear).
- Until changed, Duration shows the look's `--pg-duration` (read from the canvas) in grey and Easing reads "Default"; the rule then holds `var(--pg-duration, 0.15s)` and `var(--pg-ease, ease-in-out)`, so a change of the look carries.
- Shown for one element whose custom style is on (`_sdStateNode()`), as the State row is.

## Where is it written?
- In User Styles, on the element's custom style class, inside `@media (prefers-reduced-motion: no-preference) { .pgs-xxxxxx { transition-property; transition-duration; transition-timing-function } }` (`PGOptionStates.transWrite()`, one undo step through `_sdStateWrite()`). The class is added the first time.
- Colours is `color, background-color, border-color`; Shadow `box-shadow`; Size and place `transform, width, height, inset`; All `all`. A property list or curve no row offers is read back and kept (shown as its own entry).
- None (or the dot's reset on Transition) takes the rule out, and the block with it when nothing else stands in it. A declaration no row writes stays in the rule.
- The slider writes when let go, never during the drag.

## Why the no-preference block?
- A visitor who asks for less motion gets no transition, on the site and on the canvas, with no script and no extra rule: the media query does it. Base theme CSS has no global reduced-motion rule, so the element's own rule carries it.

## How does it behave under a state?
- One value for the element: under Hover, Focus or Pressed the rows stay open (the states part skips `[data-sd-trans-box]` rows), the sub-head shows the mark "every state" (CSS on `#sd-properties.sd-state-on`), and they write the same rule. Their changed dot shows on Normal only, so a state's count never includes them.
- Choosing a state plays the change on the canvas by itself: `#sd-state-view` sets the state's values on the element, which the transition animates.

## How does it load?
- The rows are core markup; the model is in `pg_option_states.js`, part `states`. Nothing is parsed or loaded while the User Styles hold no `prefers-reduced-motion` text with the element's class (`_sdTransOf()`); an element that holds one loads the part and draws again; the first change loads it too.
- Cost: core +3.2 KB minified (+1.0 KB gzip); part `states` 15.4 → 18.3 KB minified (5.5 → 6.5 KB gzip).

## What is not built?
- Split ("This element" card) copies the base rule only: the transition, like a state's rule, stays with the old class.
- The dragged easing curve is DO19's.

## Where is it tested?
- `tests/designer_option_states_test.php` (`test_designer_option_states_transition`): write, read, the look's values, the 1,000 ms cap, None, kept declarations and lists. The panel and canvas are not unit tested.

Related: docs/memory/designer/designer-options-states.md, docs/memory/designer/designer-options-states-drawing-motion.md, docs/memory/designer/custom-style.md
