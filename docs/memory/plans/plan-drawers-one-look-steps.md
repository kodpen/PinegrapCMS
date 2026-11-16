---
name: plan-drawers-one-look-steps
description: Plan plan-drawers-one-look, steps PD1 to PD3: the shared form parts with "New plan" on them, "Add to the plan" on them with the owner's look, close-out
---

## Which steps are there?
- PD1 (S) The shared parts, and "New plan" on them. After #9858 landed (the same drawer and stylesheet).
  - The parts of D1 in the Workspace kit; where in the kit is the step's own call. `planDrawer()` and its roadmap part build every field from them; its own `field()` and `input()` are gone.
  - Nothing changes on the screen: the three selects stay as they are until PD2.
  - Tests: a source test that `workspace_my_calendar.js` defines no `field()` or `input()` of its own and takes them from the kit; the drawer's tests of today stay green, `test_workspace_personal_plan_drawer_fits_a_phone` among them.
  - Pictures, light and dark, 1280 and 390, before and after: a new plan, a repeating plan with chosen people, somebody else's plan read only, a plan with a roadmap. Each pair is the same picture.
  - Features: D1, D3, D10, D12, D14.
- PD2 (M) "Add to the plan" on the parts. After PD1.
  - `eventForm()` builds from the parts; `formRow()` stays for the other forms. The order, wording, line under the header and footer of D4 to D7 and D9; the three selects of "New plan" in look A (D2).
  - Tests, red first: `eventForm()` and `planDrawer()` hold none of D2's classes; the names posted to `ws_event_save` are the list of before the step (written into the test); a twin of the phone test for `eventForm()` (D8); the labels of D5 are the same keys or the same English text in both.
  - Pictures, light and dark, 1280 and 390, each with "New plan" beside it: a new meeting; a visit with a customer; a leave (no times, no link); all day; an existing item with Delete, the meeting link and the Meeting note button; Who set to Department. At 390 the page width equals the window's in every picture.
  - The sheet goes to the owner through the architect: the two drawers side by side, the line of D6 and the missing "Cancel" of D7 named in the question. Not marked ready before his answer.
  - Features: D2, D4 to D9, D11, D13, D14.
- PD3 (S) Close-out. After PD2.
  - A new `workspace/workspace-plan-drawers-look.md`: where the parts are, the rule that a field in either drawer is built from them, the one order and wording of shared fields, what each drawer keeps for itself.
  - Every D number checked against the code with its PR; this plan's three files deleted.

## What must no step do?
- Edit a caller of `eventForm()`, a server action, a table or the API (D11, D13); add or drop a field; merge the two drawers into one function.

## What does a step's PR body hold?
- Its D numbers, each with the test's name or the picture that shows it; a feature without one is not done.
- What could not be verified, and why.
