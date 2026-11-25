---
name: architect-hand-over-work
description: Hand-over of the architect session of 2026-11-24 and 25, the work in flight: its open PRs, who holds the plans, the sheets the architect draws, what is open with other agents
---

## Which PRs of the architect are open?
- #11548: the Options panel packages as steps DO2 to DO27, product part 110 of the decisions list, the note on coordinator 2's pause, who draws the sheets. #11550: the canvas guides sheet, DD8. Both are labelled `ready` and not touched after.
- Owed once #11547 (DD6 and DD7, a worker's PR) has landed: DD8 is marked done with #11550 in `plans/designer-drawings.md`, and the "Who draws" lines there and in `plans/designer-drawings-levels.md` are corrected: the architect draws while no design session runs.

## Who holds the plans?
- Owner (2026-11-25, agent channel): coordinator 2 takes no new work and its open steps stay open; coordinator 1 goes on. Coordinator 1 gives out every plan's free step, coordinator 2's too, and plan steps come before a bug hunt.
- Free when their PRs land: DO2, DO7, DO18 and DO21 (#11548), CG2 and CG3 (#11550). BM1 to BM6 of the channel board are free now. Bug issue #11549 is with coordinator 1, ahead of plan steps.
- Still the architect's own: PS3 of `plans/plan-state-channel.md`. A worker's PR no longer marks its step in the plan file; the rule goes into this folder, CLAUDE.md's Claim step 3, `claim-work.md` and `coordinator-worker-steps.md` are changed, the plan is deleted.

## Which sheets does the architect draw?
- The design session stopped on 2026-11-25; its own note is channel note 24. Every sheet not yet drawn is the architect's (`design-work-session.md`): one sheet, one docs-only PR, pushed before the next is begun.
- Order: DD9 (My library), DD10 (image focus), DD11 (phone preview), DD22 (the Options panel's states, holds DO10), DD12 to DD21 of the levels plan, DD23 to DD26, and DD4 after SK2 and ST2 have landed.
- Method: the HTML of a committed sheet is the model (the canvas guides sheet of #11550 is the latest), both themes are rendered by a script and looked at once per theme at device scale 1. The session's generator and render scripts were scratch files and go with it.
- Readings answered, written into the plans by the steps' workers: DD3 (ST3's PR), DD6 (CO3 to CO5), DD7 (PC4 and PC5). From DD3: the Figma import's drop place and progress bar become design examples by a small step before ST5 and ST6; ST5 is cut into ST5a and ST5b.

## What is open with other agents?
- The merging session was asked whether the trains of 2026-11-24 stopped on conflicts. If none or one did, a train's total goes from 2,000 to 3,000 product lines while the queue is over its limit, written into `merge-train-order.md`. No answer yet.
- AC1's census may show that shared component and theme changes need a store: a schema question for the owner.
- A worker or coordinator asks in the channel naming the architect: a `## Decision question` in a PR body alone does not reach the request queue.
- Nobody is asked to remove the generated footer of a PR text (`pr-text-generated-footer.md`).

## Which numbers are next?
- Decision parts: product 111, schema 53, workspace 52. Sheets: DD26 is the last one named.
- Bug hunt 59. Hunts 57 (the designer's Interactions) and 58 (the designer's History) are named to coordinator 1 and wait until no plan step is free.

## How did the session run?
- One request waiter in the background, started again after every request (`agent-channel-wake.md`). No scheduled task of the session is enabled. The model is reported at least hourly with `tools/ws_profile.php`.
- Its helper scripts for the request routes were scratch files and go with the session; the routes are in `agent-channel-wake.md` and `architect-asking.md`.
- A request is closed only through its own answer or fail route, never by a plain message.

Related: docs/memory/workflow/architect-hand-over.md, docs/memory/workflow/architect.md
