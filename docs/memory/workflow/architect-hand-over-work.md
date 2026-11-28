---
name: architect-hand-over-work
description: Hand-over of the architect session of 2026-11-24 to 26, the work in flight: who works now, what is left of the sheets, how sheets are made, what is watched
---

## Who works now?
- Owner (2026-11-27, messages 14812, 15067, 15232): k3 works at the usual limits, k2 has ended; with no work left, 15 hunts, then the night ends: `bug-hunt-areas-7.md`.

## What is still to do with the sheets?
- No sheet is left to draw; what a sheet holds is in `designer-sheet-rules.md`.
- The owed comparison list of `designer/designer-panels-differences.md` needs the studio open on dev beside each drawing: an application key cannot.

## How is a sheet made?
- `architect-draws-new-part.md`, `design-work-session.md`. The model is the latest committed sheet: `designer/designer-frame-tidy-drawing/` holds the editor's whole chrome as classes under `.ed` in `drawing.css`, `designer-home-tidy-drawing/` a window and a list screen.
- One HTML file on `ui/look-sheet/sheet.css` and a local `drawing.css`; rendered by headless Chrome at 1600 px wide in both themes, cut to its content, saved as `-dark.jpg` and `-light.jpg`; looked at once per theme at device scale 1.
- Beside it a memory file: each board's step, what the sheet fixes, what stays. Labels in English.
- First the screen is counted on dev at 1440, 1024 and 390 px.
- A plan whose steps are "each waits for the one before it" gives out only its first open step (`tools/free_work.php`): a step waiting for the owner goes last among the screen steps, with "owner's" in its parenthesis.

## What is still the architect's own?
- After MS4 has landed: the merging session's rule for the memory mirror.
- AC1's census may show that shared component and theme changes need a store, a schema question for the owner.
- The office editors (`decisions-made-product-115.md`): the document's ability plan is free; the formulas have landed (`platform/office-sheet-formulas.md`).
- The owner asked for the designer first (`designer-faults-first.md`): an open designer `bug` issue goes ahead of a plan step, with k3.
- The Translations screen (message 15186), redrawn: a change needing a stored value comes back here (`ui/translations-screen-overview.md`).
- Plan `designer-new-version` (poll 244): a step needing a table or column comes back here.
- Poll 242: Options is too narrow; his console line picks the issue.
- Paraşüt: the six issues are fixed; what is left is in `plans/erp-parasut-open-work.md`.
- Nine features (polls 246, 247): `plans/global-features.md`. Every census is read and every plan has its build steps; polls 248 to 253 are answered (`decisions-made-schema-53.md` to `-55.md`, `decisions-made-product-119.md`). German's DE9 is `free`, then PL3; Etsy's steps are written (ET1 to ET6); left: Amazon's after ET6, with the owner's questions of `plans/sales-channels-global-census-proposal.md`.

## What is watched?
- k3's own tasks: the context size on the agent form.
- A worker or coordinator asks in the channel naming the architect: a `## Decision question` in a PR body alone does not reach the request queue.

## How did the session run?
- One request waiter in the background, started again after every answered request, never a second (`agent-channel-wake.md`): `php tools/ws_wait.php --requests` in a cloud session, with `PG_WS_SITE`, `PG_WS_KEY` and `PG_WS_SECRET` set from the session's own key. Each request: claim, brief, the answer by its own route, the waiter again.
- An answer is at most 3,900 characters: English to an agent, beginning with its handle; Turkish to the owner.
- No scheduled task of the session is enabled. The routes are in `agent-channel-wake.md` and `architect-asking.md`.
- Its window is compacted in place (`architect-no-hand-over.md`).

Related: docs/memory/workflow/architect-hand-over.md, docs/memory/workflow/architect.md
