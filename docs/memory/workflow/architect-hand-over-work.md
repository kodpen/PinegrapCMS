---
name: architect-hand-over-work
description: Hand-over of the architect session of 2026-11-24 to 26, the work in flight: its open PR, the step to free, the sheet still to draw, how sheets are made, what is watched
---

## Which PR of the architect is open?
- #11898 (`docs/designer-frame-tidy`): the editor's frame sheet and plan `designer-frame-tidy`, labelled `ready`, not touched after.

## What is done first when it has landed?
- One docs PR on the plan file designer-frame-tidy (it comes with #11898): FR5's parenthesis becomes "(M)", the line "Owner (2026-11-26, agent channel poll 234): "Evet, tek çubuk"" is added, the reading about his no and the words "FR5 alone waits for the owner" leave. The drawing's memory file needs no change.
- Coordinator k3 was told the lane (message 14338): FR1 is free once #11898 and HT4 have landed; it is told when FR5 is freed.

## Which sheet is still to draw?
- DD4 of `plans/designer-drawings.md`, the Sketch tool: SK2 has landed (#11497), so it is due. It is drawn over the Prototype view as UR3 leaves it (#11884, `plans/designer-use-round.md`); what differs from the built tool becomes one fix step. When it has landed the plan file is deleted.
- The owed comparison list of `designer/designer-panels-differences.md`.
- Every other sheet is drawn: DD1 to DD11, the Options census sheets, the home and the frame.

## How is a sheet made?
- `architect-draws-new-part.md`, `design-work-session.md`. The model is the latest committed sheet: `designer/designer-frame-tidy-drawing/` holds the editor's whole chrome as classes under `.ed` in `drawing.css`, `designer-home-tidy-drawing/` a window and a list screen.
- One HTML file on `ui/look-sheet/sheet.css` and a local `drawing.css`; rendered by headless Chrome at 1600 px wide in both themes, cut to its content, saved as `-dark.jpg` and `-light.jpg`; looked at once per theme at device scale 1.
- Beside it a memory file: which board belongs to which step, what the sheet fixes, what stays. Labels in English as the step writes them.
- First the screen is counted on dev at 1440, 1024 and 390 px. The pictures are posted to the agent channel in Turkish, the first with the text, the others under it.
- A plan whose steps are "each waits for the one before it" gives out only its first open step (`tools/free_work.php`): a step waiting for the owner goes last among the screen steps, with "owner's" in its parenthesis.
- The generator and render scripts were scratch files and went with the session.

## What is still the architect's own?
- PS3 of `plans/plan-state-channel.md`: a worker's PR no longer marks its step in the plan file; the rule goes into this folder, CLAUDE.md's Claim step 3, `claim-work.md` and `coordinator-worker-steps.md` are changed, the plan is deleted.
- After MS4 has landed: the merging session's rule for the memory mirror.
- DD4's Sketch half above; AC1's census may show that shared component and theme changes need a store, a schema question for the owner.

## What is watched?
- k3's own tasks: the context size on the agent form.
- A worker or coordinator asks in the channel naming the architect: a `## Decision question` in a PR body alone does not reach the request queue.

## How did the session run?
- One waiter on the agent door in the background, started again after every knock, never a second and no polling (`architect-push-door.md`, `agent-channel-wake.md`). On a knock: the request list, claim, the answer by the request's own route, the model report, the waiter again.
- An answer is at most 3,900 characters: English to an agent, beginning with its handle; Turkish to the owner. A request is closed only through its own answer, none or fail route.
- No scheduled task of the session is enabled. Its helper scripts for the routes were scratch files; the routes are in `agent-channel-wake.md` and `architect-asking.md`.
- Its window is compacted in place (`architect-no-hand-over.md`); after one, the state is read from GitHub and memory.

Related: docs/memory/workflow/architect-hand-over.md, docs/memory/workflow/architect.md
