---
name: designer-options-states-drawing-state
description: The Options panel's State row as drawn (options-states.html, boards S1 to S3, step DO10): the row, the ring, what the panel and the canvas show under a state, "Reset this state"
---

## Where is the State row and what does it hold?
- First in the Style tab's body, above the section cards: the label "State" and one group, Normal, Hover, Focus, Pressed. Under 300 px the group stands under its label.
- An element that takes no focus and no press (a card, a section, a text) has Normal and Hover only.
- A press chooses a state; a press on the chosen one, or Escape, goes back to Normal.
- The state is held while this element and the Style tab are open: another element, another tab, typing in the search and "Only what I changed" go back to Normal.
- The first time a state is chosen, one line under the row: "What you set under a state holds only while the element is hovered, focused or pressed. The canvas shows the chosen state on this element.", with "Got it".

## What marks a state that holds a value?
- A ring after the state's name in the group: 7 px across, a 1.5 px accent line, white on the chosen segment.
- On Normal a section's head carries the ring after its name and count when a state changes a row in it; its tip names the states ("Hover and Focus change this section"). The count stays Normal's changed rows.

## What does the panel show under a state?
- A line under the row: "2 options set for Hover" with the button "Reset this state"; with nothing set "Nothing is set for Hover." and no button.
- Text, Spacing and Size and display keep their place, closed and dimmed, with the summary "same in every state"; they do not open.
- Color and background, Border and corner and Shadow and effect write. The Style tab's and the heads' counts are the chosen state's; an untouched section's summary reads "as Normal".
- A row shows what holds in the state: its own value with the changed dot, or Normal's in grey. Changing it writes the state's rule; the dot's arrow reads "Reset to Normal" and takes that one value out.
- A row no state can write (Bg Image and its rows, Text Opacity, Bg Opacity, Placeholder, Focus Ring) is dimmed in place.

## What do "Reset this state" and "Only what I changed" do?
- "Reset this state" clears every value of the chosen state on the selected elements in one undo step, with no question.
- "Only what I changed" lists Normal's rows as today, then under "In a state" one line for each state that holds a value ("Hover", "2 options") with "Show", which opens the Style tab on that state. Its count adds the states' values, and "Reset all" clears them too.

## What does the canvas show?
- The selected element in the chosen state, with the state's name in its tag ("Button", "Hover"). Several selected elements are drawn in it together; a row where they differ reads "— Multiple —".
- Only what the Options panel set for the state: a hover the theme gives a button by itself is seen in the Preview. The pointer over the canvas changes nothing, as today.
- Nothing of the shown state is saved with the page.

## Which keys and names?
- Tab reaches each state as it reaches a group's buttons today; Space or Enter chooses. The group is named "State"; a segment with a ring is read "Hover, has values", and choosing a state is announced.

Related: docs/memory/designer/designer-options-states-drawing.md, docs/memory/designer/designer-options-controls.md, docs/memory/designer/designer-options-review.md
