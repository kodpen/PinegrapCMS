---
name: architect-hand-over-work
description: Hand-over of the architect session of 2026-11-24 to 26, the work in flight: who works now, what is left of the sheets, how sheets are made, what is watched
---

## Who works now?
- Since 2026-11-26 (owner, message 14372) only the architect, the merging session and k3 work; k2 is back for the Paraşüt round alone (`architect-hand-over.md`).

## What is still to do with the sheets?
- No sheet is left to draw; what a sheet holds is in `designer-sheet-rules.md`.
- The owed comparison list of `designer/designer-panels-differences.md` needs the studio open on dev beside each drawing: an application key cannot.

## How is a sheet made?
- `architect-draws-new-part.md`, `design-work-session.md`. The model is the latest committed sheet: `designer/designer-frame-tidy-drawing/` holds the editor's whole chrome as classes under `.ed` in `drawing.css`, `designer-home-tidy-drawing/` a window and a list screen.
- One HTML file on `ui/look-sheet/sheet.css` and a local `drawing.css`; rendered by headless Chrome at 1600 px wide in both themes, cut to its content, saved as `-dark.jpg` and `-light.jpg`; looked at once per theme at device scale 1.
- Beside it a memory file: each board's step, what the sheet fixes, what stays. Labels in English.
- First the screen is counted on dev at 1440, 1024 and 390 px. The pictures go to the channel in Turkish.
- A plan whose steps are "each waits for the one before it" gives out only its first open step (`tools/free_work.php`): a step waiting for the owner goes last among the screen steps, with "owner's" in its parenthesis.

## What is still the architect's own?
- After MS4 has landed: the merging session's rule for the memory mirror.
- AC1's census may show that shared component and theme changes need a store, a schema question for the owner.
- The office editors (owner 2026-11-26, `decisions-made-product-115.md`): `plans/office-sheet-abilities.md` and `office-document-abilities.md` are free since 2026-11-27; `office-sheet-formulas.md` waits: the architect marks its lines `free` by a docs PR when SA5 has landed, and tells k3.
- The owner asked for the designer first (`designer-faults-first.md`): an open designer `bug` issue goes ahead of a plan step, with k3.
- His stutter is the design's size: #11974 and #12011 landed (`designer/designer-performance.md`). He has not tried design 838 again (poll 245); his word then says whether an issue follows.
- Phone preview: #11966 and #11991 (the live page's QR code, poll 243) landed; another reason his window names gets its own issue.
- Plan `designer-new-version` (poll 244, `decisions-made-product-116.md`): NV1 landed, NV2 is PR #12023; a step needing a table or column comes back here.
- Poll 242: Options is too narrow; his console line under it picks the issue.
- Paraşüt: the staging round passed and its six issues are fixed; what is left is in `plans/erp-parasut-open-work.md`, with k2's local workers.
- 2026-11-27: one hunt round at up to 15 workers a coordinator (`bug-hunt-areas-6.md`); the usual limits are back.

## What is watched?
- k3's own tasks: the context size on the agent form.
- A worker or coordinator asks in the channel naming the architect: a `## Decision question` in a PR body alone does not reach the request queue.

## How did the session run?
- One request waiter in the background, started again after every answered request, never a second (`agent-channel-wake.md`): `php tools/ws_wait.php --requests` in a cloud session, with `PG_WS_SITE`, `PG_WS_KEY` and `PG_WS_SECRET` set from the session's own key. Each request: claim, brief, the answer by its own route, the waiter again.
- An answer is at most 3,900 characters: English to an agent, beginning with its handle; Turkish to the owner.
- No scheduled task of the session is enabled. The routes are in `agent-channel-wake.md` and `architect-asking.md`.
- Its window is compacted in place (`architect-no-hand-over.md`).

Related: docs/memory/workflow/architect-hand-over.md, docs/memory/workflow/architect.md
