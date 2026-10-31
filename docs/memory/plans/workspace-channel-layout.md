---
name: workspace-channel-layout
description: Plan (owner decision 2026-10-31) for the redrawn Workspace channel screen: one-line header, catch-up line for the briefing card, compact lines, clean composer, side panels, Overview; phone rules
---

## What is this plan?
- Owner decision (2026-10-31, `workflow/decisions-made-workspace-5.md`): the channel screen is made again in the drawn design, modern and tidy, as the list is (`plans/workspace-menu-layout.md`). Built only by the session the owner names for the design; each step is checked on dev against the drawing at desktop and phone width, light and dark.
- Code: `assets/js/workspace.js` (head ~8153, tabs ~8275, `briefingNode()` ~9445, pin bar ~6183, composer ~12585), the Workspace block of `assets/css/backend.src.css`, `includes/workspace/briefing.php`. Panel colour tokens; no schema, no new right.

## What is kept?
- Every feature: a rarely needed item may move behind a menu or show on hover, never go.
- Access (`ws_can_read_channel()` and the rest), links that open in a new tab, keyboard use, `prefers-reduced-motion`.
- A misaligned or poorly drawn workspace part met on the way is fixed in the step that meets it.

## What must hold on a phone?
- The writing box rides on the keyboard: it moves up with it and the message list shrinks above it, as today; 16 px text, the send button and drafts unchanged.
- The header is one short line (back, name, ⋯); tabs, members, search and info go into ⋯ or a scroll row; the catch-up and pinned lines fold into one thin line. At 390 × 844, keyboard closed, the message list keeps about 70 % of the height.
- The drawn prototype failed both (the box stayed under the keyboard, the list had a third of the screen); the owner approved it for the desktop.

## What are the steps?
- C1 Header: done #3843 (`workspace/workspace-channel-head.md`; one line, the purpose in the channel's info, on a phone back, name and ⋯).

- C2 Briefing card to catch-up line: in a channel only one closable line "While you were away …" (new messages, mentions, overdue tasks) when something is new, with Go to new messages and the overdue tasks; the greeting stays on the Overview and in the morning e-mail (`briefing.php` stays as data).

- C3 Pinned and system lines: pinned messages as one line with the rendered text and a count, opening on a click; a system notice as one short line with its link; no raw markdown.

- C4 Messages: one author's consecutive messages grouped, actions on hover, the decision tag, Convert to text as a small button, a discussion's link "3 replies · last …".

- C5 Composer: one row (attach, formatting, @, /, emoji, voice, send), the hint line goes, / opens the command list in the panel's look; the phone rules above.

- C6 Side panels: the discussion (title, channel, Claude's follow line with Stop) and the channel info (purpose, status, members, notifications, read answers aloud, time spent, channel settings).

- C7 Overview: the greeting only here, then Waiting for you, Today and overdue, Calendar today, What happened in the channels, Recent decisions.

## What is still open?
1. Whether C7 renames Overview to Today; asked when C7 starts.

## Where to start?
- C2. The local sandbox (`tools/setup_sandbox.sh`) draws the channel screen at desktop and phone width; try each step at 390 px with the keyboard open before anything else.
