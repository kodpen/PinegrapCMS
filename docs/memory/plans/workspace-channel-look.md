---
name: workspace-channel-look
description: Plan (owner 2026-11-10, handed to workers 2026-11-11): the Workspace channel screen in the dashboard's manner (one header card, colour wash, pill tabs, panes as cards); steps CL1-CL4 in order
---

## What is it for?
- Owner (2026-11-10): the channel screen is "line after line" (the colour stripe, the rule under the header, under the tabs, between rows), the channel's colour shows as a stripe ("shadow") instead of in the header itself, and the insides of the tabs look dated. It is drawn again in the new dashboard's manner.

## Where does it stand?
- Free to build. The owner was shown `plans/workspace-channel-look/01-before-after.jpg` on 2026-11-10 (the conversation, Tasks and Summary tabs now and proposed, the header card in the light theme). On 2026-11-11 he asked that another session take the work over and complete it (`workflow/decisions-made-workspace-13.md`).
- Coordinators hand the steps out like any plan's; claim as `[plan:workspace-channel-look] CL<n> — <topic>`. They are built in the order written: each waits for the one before it.
- The picture was made on dev with a style sheet put into the page (`workspace-channel-look-prototype.md` holds CL1's part); CL2 and CL3 follow it.

## What does every step keep?
- `workspace/workspace-channel-screen.md`: every feature, access, keyboard use, reduced motion, the panel's colour tokens in light and dark, the phone rules (one short header line, the writing box on the keyboard, the list's height).
- Panel screens are full width: cards fill the pane (a grid, or columns that flow top to bottom), no centred max-width box.
- Architect (2026-11-11, #8636): each step's PR shows its screens before and after from the sandbox at desktop (1440) and phone (390) width, light and dark, as a private page linked in the PR (`ui/panel-screenshots.md`), beside `plans/workspace-channel-look/01-before-after.jpg`. That round is the check: the steps are built in cloud containers, which cannot reach dev.
  - The dev check goes under "Could not verify"; the PR is marked ready when green, without an approval issue. The owner looks on dev after it lands: what he asks for joins the next step, or a small follow-up PR after CL3.
  - CL1 shows a channel with a colour, one without, and one with the share and era bars; CL2 and CL3 each pane they change.

## Which steps are there?
- CL1 (M, no schema) The shell: the header and the tabs one card washed in the channel's colour, pill tabs. Done (#8678): `workspace/workspace-channel-identity-motion.md`.
- CL2 (M, no schema) Tasks, Decisions and Files as cards. Done (#8688): `workspace/workspace-channel-panes.md`. After CL1. Rows lose their rules: a task is a card in a grid (status, title, due line, people, done), the toolbar a quiet row; decisions a card grid; files their groups as cards.
- CL3 (M, no schema) Summary, Board and Memory. Done (#8703): `workspace/workspace-channel-summary-board-memory.md`. After CL2. The summary as a card with its Edit button in the head, Time spent beside it as tiles; the board's toolbar as chips and its columns in the card manner; memory's hint, New record and records as cards. Empty states sit inside a dashed card.

- CL4 (M, no schema, CSS only) Decisions, Tasks and Board use the width. Done (#8759). After CL3. Owner (2026-11-11), on the Decisions tab on dev (six narrow cards a row, each with a green start edge): cards up to twice as wide and one under the other, the green gone, a modern card; Tasks and Board the same.
  - Decisions and Tasks flow in columns; no coloured start edge on the Decisions tab (the tag names the kind); framed cards with a soft shadow; Board columns grow up to twice their width. `.ws-wt-board`, My tasks and a record's window unchanged. Files: `backend.src.css`, `tests/workspace_channel_look_test.php`, the four `workspace/workspace-channel-*` pane files.

## What is not in it?
- The conversation's messages (C4) and the side panels (C6) keep their drawing.
- No new feature, no schema, no new right.
