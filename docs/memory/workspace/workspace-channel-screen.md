---
name: workspace-channel-screen
description: The Workspace channel screen as redrawn (C1–C8) and its card look (CL1-CL4, what it left alone): what every change keeps, the phone rules, who changes the design, where each part is
---

## Where is each part described?
- Header in one line (C1, #3843): `workspace-channel-head.md`.
- Catch-up line in place of the greeting card, pinned line (C2, C3, #3873): `workspace-channel-catchup.md`.
- Messages, hover bar, decision tag, discussion line (C4, #4125): `workspace-channel-messages.md`; the Decisions tab: `workspace-channel-decisions-tab.md`.
- Writing box (C5, #3898): `workspace-channel-composer.md`.
- Channel info and discussion panels beside the conversation (C6, #4143): `workspace-channel-panels.md`.
- The first page, Today (C7, C8, #4196): `workspace-today-page.md`. The Workspace list beside them: `workspace-menu-list.md`, `workspace-menu-tools.md`.

## What does every change keep?
- Every feature: a rarely needed item may move behind a menu or show on hover, never go.
- Access (`ws_can_read_channel()` and the rest), links that open in a new tab, keyboard use, `prefers-reduced-motion`; the panel's colour tokens in light and dark (`decisions-made-workspace-5.md`), no schema of its own.
- A misaligned or poorly drawn workspace part met on the way is fixed in the change that meets it, as the drawing would have it: one look for cards, rows and badges, nothing stuck one under the other.

## How is a Workspace card titled?
- As the channel's Summary card (`drawSummary()`): an `h3.h6.mb-0` with a `me-1` icon in the `.card-header`, never the uppercase `text-uppercase h5 fg-primary fw-bold` form card heading (`ui/form-card-headings.md`). The Summary tab's Time spent (`workspace_task_time.js`) and Customer (`workspace_customer.js`) cards drew that until #5195; `tests/workspace_card_titles_test.php` pins them.
- Today's cards are dashboard cards (`todayCard()`, `.pg-dash-card-title`); the overview's `homeCard()` this rule first named is gone.

## What must hold on a phone?
- The writing box rides on the keyboard: it moves up with it and the message list shrinks above it; 16 px text, the send button and drafts unchanged.
- The header is one short line (back, name, ⋯); tabs, members, search and info are in ⋯ or a scroll row; the catch-up and pinned lines fold into one thin line, and they and the tabs hide while the keyboard is up.
- At 390 × 844 with the keyboard closed the message list keeps about 70 % of the height. The first drawing failed both (the box stayed under the keyboard, the list had a third of the screen); measure at 390 px with the keyboard open before anything else (the local sandbox, `tools/setup_sandbox.sh`).

## Who changes the design?
- The session the owner names in chat for the design, against the drawn design, checked at desktop and phone width, light and dark (`workflow/decisions-made-workspace-5.md`); coordinators do not hand such work out.
- The channel's card look (CL1-CL4, 2026-11-11) was the one exception: coordinators handed its steps out, built against the picture the owner was shown (`workflow/decisions-made-workspace-13.md`). It is done; its files are below.

## What did the card look change, and what not?
- Owner (2026-11-10): the screen was "line after line" (the colour stripe, the rule under the header, under the tabs, between rows), the channel's colour a stripe instead of in the header, the tabs' insides dated; redrawn in the dashboard's manner.
- The shell (one card in the channel colour, pill tabs, day chip, writing box card): `workspace-channel-identity-motion.md`. Tasks, Decisions, Files and owner (2026-11-11) on using the width: `workspace-channel-panes.md`. Summary, Board, Memory, empty states: `workspace-channel-summary-board-memory.md`.
- A channel pane fills the width: cards in a grid or in columns that flow top to bottom, never a centred max-width box.
- Not changed: the conversation's messages (C4) and the side panels (C6) keep their drawing; no new feature, schema or right came with it.
