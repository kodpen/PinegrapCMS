---
name: architect-hand-over-work
description: Hand-over of the architect session of 2026-11-24 to 26, the work in flight: who works now, what is left of the sheets, how sheets are made, what is watched
---

## Who works now?
- Since 2026-11-26 (owner, message 14372) only the architect, the merging session and k3 work; k2 is back for the Paraşüt round alone (`architect-hand-over.md`).

## What is still to do with the sheets?
- No sheet is left to draw; what a sheet holds is in `designer-sheet-rules.md`.
- The owed comparison list of `designer/designer-panels-differences.md` needs the studio open on dev beside each drawing: not possible with only an application key.

## How is a sheet made?
- `architect-draws-new-part.md`, `design-work-session.md`. The model is the latest committed sheet: `designer/designer-frame-tidy-drawing/` holds the editor's whole chrome as classes under `.ed` in `drawing.css`, `designer-home-tidy-drawing/` a window and a list screen.
- One HTML file on `ui/look-sheet/sheet.css` and a local `drawing.css`; rendered by headless Chrome at 1600 px wide in both themes, cut to its content, saved as `-dark.jpg` and `-light.jpg`; looked at once per theme at device scale 1.
- Beside it a memory file: which board belongs to which step, what the sheet fixes, what stays. Labels in English as the step writes them.
- First the screen is counted on dev at 1440, 1024 and 390 px. The pictures go to the agent channel in Turkish.
- A plan whose steps are "each waits for the one before it" gives out only its first open step (`tools/free_work.php`): a step waiting for the owner goes last among the screen steps, with "owner's" in its parenthesis.

## What is still the architect's own?
- After MS4 has landed: the merging session's rule for the memory mirror.
- AC1's census may show that shared component and theme changes need a store, a schema question for the owner.
- The office editors (owner 2026-11-26, `decisions-made-product-115.md`): the steps of `plans/office-sheet-abilities.md`, `office-sheet-formulas.md` and `office-document-abilities.md` are not free; the architect marks their lines `free` when PR #11993, SA5 and OP3 have landed, each by a docs PR, and tells k3.
- The owner asked for the designer first (`designer-faults-first.md`): an open designer `bug` issue goes ahead of a plan step, with k3.
- His stutter is the design's size: #11974 landed (five selections in the Store template 1.2 to 0.9 s on a sandbox, 0.12 s in a small design); the rest is bug issue #11987. Poll 245 asks him to try design 838 again.
- Phone preview: #11966 has landed; #11968 (the live page's QR code, poll 243) is free; another reason his window names gets its own issue.
- Plan `designer-new-version` (poll 244, `decisions-made-product-116.md`): NV1 is PR #11981; a step needing a table or column comes back here.
- Poll 242: Options is too narrow; his console line under it picks the issue.
- Paraşüt: the staging round passed; what is left is in `plans/erp-parasut-open-work.md`, with k2's local workers.

## What is watched?
- k3's own tasks: the context size on the agent form.
- A worker or coordinator asks in the channel naming the architect: a `## Decision question` in a PR body alone does not reach the request queue.

## How did the session run?
- One request waiter in the background, started again after every answered request, never a second (`agent-channel-wake.md`): `php tools/ws_wait.php --requests` in a cloud session, with `PG_WS_SITE`, `PG_WS_KEY` and `PG_WS_SECRET` set from the session's own key. Each request: claim, brief, the answer by its own route, the waiter again.
- An answer is at most 3,900 characters: English to an agent, beginning with its handle; Turkish to the owner. A request is closed only through its own answer, none or fail route.
- No scheduled task of the session is enabled. The routes are in `agent-channel-wake.md` and `architect-asking.md`.
- Its window is compacted in place (`architect-no-hand-over.md`).

Related: docs/memory/workflow/architect-hand-over.md, docs/memory/workflow/architect.md
