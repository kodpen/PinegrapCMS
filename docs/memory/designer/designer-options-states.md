---
name: designer-options-states
description: The Options panel's State row as built (DO10, #11588): Normal, Hover, Focus, Pressed; the state's rule in User Styles, which rows write, the canvas view, the part 'states', what is not built yet
---

## What does the State row do?
- First row of the Style tab's body (`_sdStateRowHtml()`), never in the panel's head: Normal, then Hover; Focus and Pressed only for a link, a button, a form control, a summary, a `btn` or `nav-link` (`_sdStatesFor()`).
- A press chooses a state; the chosen one, Normal or Escape (outside a field) goes back to Normal. Another element, another tab, the search, a jump to a row and "Only what I changed" go back to Normal (`_sdStateDrop()` in `renderProperties()` and `_sdOptTabGo()`).
- Shown for one element whose custom style is on (`_sdCustomStyleOff()`); not for several elements, the page root, a text's inner element, inside a system widget or a shared component.

## Where is a state's value written?
- In User Styles, on the element's custom style class: `.pgs-xxxxxx:hover`, `:focus-visible` (Focus), `:active` (Pressed), after the base rule; the element gets the class the first time, as "This element" does (`custom-style.md`). One undo step per change (`_sdStateWrite()`).
- A row's Bootstrap class is written as its declarations with `!important` (`PGOptionStates.decls()`: `bg-primary` → `background-color: var(--bs-primary)`), in a fixed order so `border` comes before `border-color`. A declaration no row writes stays in the rule.
- Read back by matching the declarations to the row's classes (`match()`).

## Which rows write under a state?
- Color and background (Text Color, Background Color, Gradient, Opacity), Border and corner (Border, Color, Width, the five radius rows instead of the corner box), Shadow and effect (Shadow).
- Dimmed in place: Text Opacity, Bg Opacity, Custom Background Color, Bg Image and its rows, Placeholder, Animation, Focus Ring.
- Text, Spacing and Size and display stay closed and dimmed with the summary "same in every state"; their heads do not open.
- A row set in the state has the changed dot; its arrow reads "Reset to Normal" and takes that one value out (`_sdStateRowReset()`). A row not set shows Normal's value in grey. An untouched writable section reads "as Normal". The heads' and the tab's counts are the state's.
- The line under the row: "2 options set for Hover" with "Reset this state" (the state's rule removed, one undo step, no question), or "Nothing is set for Hover.". The first time, a line of what a state is for with "Got it".

## What marks a state that holds a value?
- A ring after the state's name in the group (read "Hover, has values"); on Normal a ring after a Style section's name when a state changes a row in it, its tip naming the states.
- "Only what I changed": under the list, "In a state", one line per state with its count and "Show" (the Style tab opened on it); the switch's count and "Reset all" (one undo step) include the states' values.

## What does the canvas show?
- The selected element with the state's declarations (style element `#sd-state-view` in the canvas, by `data-sd-id`) and the state's name before its toolbar. Never saved.

## How does it load?
- Part `states` (`pg_option_states.js`, then `style_designer_states.js`), loaded the first time a state is chosen or an element whose class holds a state's rule is shown (`_sdStateAfter()`). The core keeps the row and the hooks. Core +3.6 KB minified; the part 15.4 KB (#11588).

## What is not built yet?
- States for several selected elements (the drawing's "— Multiple —" under a state), and Custom Background Color under a state: both dimmed or absent for now.

## Where is it tested?
- `tests/designer_option_states_test.php`: the model and `_sdStatesFor()`; the panel and canvas are not unit tested.

Related: docs/memory/designer/designer-options-states-drawing-state.md, docs/memory/designer/custom-style.md, docs/memory/designer/designer-options-review.md
