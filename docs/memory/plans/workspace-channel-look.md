---
name: workspace-channel-look
description: Proposal, not to be started before the owner approves the look: the Workspace channel screen in the dashboard's manner (one header card, colour wash, pill tabs, panes as cards); steps CL1-CL3
---

## What is it for?
- Owner (2026-11-10): the channel screen is "line after line" (the colour stripe, the rule under the header, under the tabs, between rows), the channel's colour shows as a stripe ("shadow") instead of in the header itself, and the insides of the tabs look dated. It is drawn again in the new dashboard's manner.

## Where does it stand?
- A proposal. The owner was shown `plans/workspace-channel-look/01-before-after.jpg` on 2026-11-10 (the conversation, Tasks and Summary tabs now and proposed, the header card in the light theme) and has not answered.
- Not to be started before he approves the look, asked as `workflow/architect-asking.md` says; the decision is then written to `workflow/decisions-made-workspace-<n>.md` with CL1.
- Built by the session the owner names for the design (`workflow/decisions-made-workspace-5.md`): coordinators do not hand its steps out. The session he named on 2026-11-10 has ended.
- The picture was made on dev with a style sheet put into the page, nothing in the code: `workspace-channel-look-prototype.md` holds CL1's part. The Tasks and Summary panes were arranged by hand for the picture.

## What does every step keep?
- `workspace/workspace-channel-screen.md`: every feature, access, keyboard use, reduced motion, the panel's colour tokens in light and dark, the phone rules (one short header line, the writing box on the keyboard, the list's height).
- Panel screens are full width: cards fill the pane in a grid, no centred max-width box.
- Checked on dev at desktop and phone width, light and dark, before it is marked ready.

## Which steps are there?
- CL1 (M, no schema) The shell.
  - The header and the tabs are one card: a border and a wash of the channel's colour (`--ws-ch`) from the side the sign stands on, no top stripe; a channel without a colour keeps a plain card. The share and era bars between them are rows of the same card.
  - Tabs are pills, the open one filled (in the channel's colour when it has one); counts keep their marks. The day mark is a chip without rules. The writing box is a card.
  - Files: the Workspace block of `backend.src.css`, `drawCenter()` in `workspace.js` (the colour on the card, not only on the header), tests, `workspace/workspace-channel-identity-motion.md`.
- CL2 (M, no schema) Tasks, Decisions and Files as cards. After CL1. Rows lose their rules: a task is a card in a grid (status, title, due line, people, done), the toolbar a quiet row; decisions a card grid; files their groups as cards.
- CL3 (M, no schema) Summary, Board and Memory. After CL2. The summary as a card with its Edit button in the head, Time spent beside it as tiles; the board's toolbar as chips and its columns in the card manner; memory's hint, New record and records as cards. Empty states sit inside a dashed card.

## What is not in it?
- The conversation's messages (C4) and the side panels (C6) keep their drawing.
- No new feature, no schema, no new right.
