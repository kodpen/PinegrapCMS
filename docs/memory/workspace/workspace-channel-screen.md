---
name: workspace-channel-screen
description: The Workspace channel screen as redrawn (C1–C8, 2026-10-31): what every change to it keeps, what must hold on a phone, who changes the design, where each part is described
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

## What must hold on a phone?
- The writing box rides on the keyboard: it moves up with it and the message list shrinks above it; 16 px text, the send button and drafts unchanged.
- The header is one short line (back, name, ⋯); tabs, members, search and info are in ⋯ or a scroll row; the catch-up and pinned lines fold into one thin line, and they and the tabs hide while the keyboard is up.
- At 390 × 844 with the keyboard closed the message list keeps about 70 % of the height. The first drawing failed both (the box stayed under the keyboard, the list had a third of the screen); measure at 390 px with the keyboard open before anything else (the local sandbox, `tools/setup_sandbox.sh`).

## Who changes the design?
- The session the owner names in chat for the design, against the drawn design, checked at desktop and phone width, light and dark (`workflow/decisions-made-workspace-5.md`); coordinators do not hand such work out.
