---
name: designer-drawings
description: Plan (owner 2026-11-24): the designer plans' screens are drawn first, DD1 to DD11, by the design session or the architect; a screen step waits for its drawing's merge, never for a yes
priority: 8
---

## What is asked?
- Owner (2026-11-24): "planlar çizimleri de önce tasarlansın"; drawn, with nobody waiting for his approval, for the seven new plans and the screen steps not yet started of the plans before them (`workflow/decisions-made-product-106.md`).

## Who draws, and where is a drawing kept?
- The design session, or the architect while none runs (`workflow/design-work-session.md`): one step and one docs-only PR at a time.
- No step is a coordinator's: whoever draws marks lines `free` for a worker; none is marked.
- In `docs/memory/designer/<plan>-drawing/`: one HTML sheet of boards with a light and a dark picture of each sheet, as the Flows editor's sheets are kept in the workspace folder.

## What does a sheet hold?
- A board for every screen and state its plan names: empty, filled, busy, error, refused, and the narrow width; under each board the step that builds it.
- Only parts of the Design examples screen and the look's tokens, laid out by `ui/screen-composition-rules.md`. A part that screen lacks is asked from the architect as a design example, never invented on a sheet.
- Every label in English exactly as the step will write it, each button with its rank, and what the keyboard does where the plan names keys.
- The designer's frame around it as it is today, so the new part is seen in its place.

## What waits for what?
- A screen step names its DD step after "after": it starts when that drawing has landed. It never waits for the owner or the architect to approve it.
- The owner may look at any sheet at any time; what he asks to change after a step was built is a fix step, not a reason to stop one.
- SK2 and ST2 were started before this: they finish by their description. DD4 draws them after they land; the differences become one fix step each.
- A step with no screen (a pure model, a server step, a census, a close-out) waits for no drawing.

## Which steps, in this order?
- DD1 (M) Done (#11508). `designer-variables.md`: the Variables tab, the Options chip, the start questions, Export and Import. Holds DV2 to DV5.
- DD2 (M) Done (#11526). `designer-sketch.md`: "Build the page" and "Build again", "Page details", the two AI actions, the reference picture. Holds SK4 to SK7.
- DD3 (M) Done (#11525). `designer-start-import.md`: Figma on Connections, the frame list, the report; Canva's form, reference pictures and brand. Holds ST3, ST5, ST6.
- DD5 (S) Done (#11538). `designer-preview-as.md`: the "Preview as" bar, its three choosers, the "Check all cases" list. Holds PW3, PW4.
- DD6 (M) Done (#11547). `designer-component-overrides.md`: marking a field open, the component's list, "This place" with the dot and reset, variants. Holds CO3 to CO5.
- DD7 (M) Done (#11547). `designer-publish-check.md`: the sheet's results, the five groups, a finding with Fix, Show me and Ignore, the weight budget. Holds PC4, PC5.
- DD8 (M) Done (#11550). `designer-canvas-guides.md`: Measure, the spacing bands and handles, the column grid, equal gaps, layout lines. Holds CG2 to CG5.
- DD9 (M) Done (#11559). `designer-my-library.md`: the save window, "My library" in Blocks, "Manage library", Export and Import. Holds ML2 to ML4.
- DD10 (S) Done (#11561). `designer/designer-image-focus-drawing.md`: the Focus control, "Set focus". IF2, IF3 built.
- DD11 (S) Done (#11565). `designer-phone-preview.md`: the window with the QR code, the phone's view with its thin line, the signed-in case. Holds PP2, PP3.
- DD4 (M) after SK2 and ST2 have landed: the Sketch tool and the New design window as they should look.

## What does every step keep?
- Docs only: the sheet and its pictures. No product code, no change to a plan another open PR holds.
- When the last sheet has landed this file is deleted; the rule stays in the decision file.
- Claim as `[plan:designer-drawings] <step> — <topic>`.
