---
name: architect-hand-over-work
description: Hand-over of the architect session of 2026-11-24 to 26, the work in flight: what has landed, who works now, what is left of the sheets, how sheets are made, what is watched
---

## What of that session's work has landed?
- All of its PRs: #11898 (the editor's frame sheet and plan `designer-frame-tidy`), #11901 (FR5 no longer waits: poll 234, "Evet, tek çubuk") and #11903 (DD4, the Sketch tool, with plan `designer-sketch-keys`, step SX1).
- Coordinator k3 was told both lanes (messages 14338 and 14355). Since 2026-11-26 (owner, message 14372) only the architect, the merging session and k3 work: k1 and k2 are out of the agent channel.

## What is still to do with the sheets?
- No sheet is left to draw. `plans/designer-drawings.md` is deleted by a docs PR, since its last sheet has landed.
- The owed comparison list of `designer/designer-panels-differences.md`: it needs the studio open on dev beside each drawing, which a cloud session with only an application key cannot do.

## How is a sheet made?
- `architect-draws-new-part.md`, `design-work-session.md`. The model is the latest committed sheet: `designer/designer-frame-tidy-drawing/` holds the editor's whole chrome as classes under `.ed` in `drawing.css`, `designer-home-tidy-drawing/` a window and a list screen.
- One HTML file on `ui/look-sheet/sheet.css` and a local `drawing.css`; rendered by headless Chrome at 1600 px wide in both themes, cut to its content, saved as `-dark.jpg` and `-light.jpg`; looked at once per theme at device scale 1.
- Beside it a memory file: which board belongs to which step, what the sheet fixes, what stays. Labels in English as the step writes them.
- First the screen is counted on dev at 1440, 1024 and 390 px. The pictures are posted to the agent channel in Turkish, the first with the text, the others under it.
- A plan whose steps are "each waits for the one before it" gives out only its first open step (`tools/free_work.php`): a step waiting for the owner goes last among the screen steps, with "owner's" in its parenthesis.
- The generator and render scripts were scratch files and went with the session.

## What is still the architect's own?
- After MS4 has landed: the merging session's rule for the memory mirror.
- AC1's census may show that shared component and theme changes need a store, a schema question for the owner.

## What is watched?
- k3's own tasks: the context size on the agent form.
- A worker or coordinator asks in the channel naming the architect: a `## Decision question` in a PR body alone does not reach the request queue.

## How did the session run?
- One request waiter in the background, started again after every answered request, never a second (`agent-channel-wake.md`): the push door on the machine that serves dev until 2026-11-26, `php tools/ws_wait.php --requests` in a cloud session since, with `PG_WS_SITE`, `PG_WS_KEY` and `PG_WS_SECRET` set from the session's own key. Each request: claim, brief, the answer by its own route with the model, the waiter again.
- An answer is at most 3,900 characters: English to an agent, beginning with its handle; Turkish to the owner. A request is closed only through its own answer, none or fail route.
- No scheduled task of the session is enabled. Its helper scripts for the routes were scratch files; the routes are in `agent-channel-wake.md` and `architect-asking.md`.
- Its window is compacted in place (`architect-no-hand-over.md`); after one, the state is read from GitHub and memory.

Related: docs/memory/workflow/architect-hand-over.md, docs/memory/workflow/architect.md
