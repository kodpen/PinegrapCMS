---
name: designer-sheet-rules
description: What a designer plan's drawing sheet holds (boards for every screen and state, English labels, button ranks), where it is kept, which steps wait for it and which do not
---

## What does a sheet hold, and where is it kept?
- In `docs/memory/designer/<plan>-drawing/`: one HTML sheet of boards with a light and a dark picture, and a memory file beside it naming the step each board belongs to.
- A board for every screen and state its plan names: empty, filled, busy, error, refused, and the narrow width; under each board the step that builds it.
- Only parts of the Design examples screen and the look's tokens, laid out by `ui/screen-composition-rules.md`. A part that screen lacks is drawn by the architect as a design example first (`architect-draws-new-part.md`), never invented on a sheet.
- Every label in English exactly as the step will write it, each button with its rank, and what the keyboard does where the plan names keys.

## What waits for a sheet?
- A screen step names its sheet after "after" and starts when that drawing has landed; it never waits for a yes (`decisions-made-product-106.md`). A step with no screen (a model, a server step, a census, a close-out) waits for no drawing.
- What the owner asks to change on a sheet after its step was built is a fix step. A tool built before its sheet keeps its build; what the sheet shows otherwise is one fix step (the Sketch tool: `plans/designer-sketch-keys.md`).

Related: docs/memory/workflow/design-work-session.md, docs/memory/workflow/decisions-made-product-106.md, docs/memory/workflow/architect-draws-new-part.md
