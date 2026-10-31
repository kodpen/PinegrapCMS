---
name: workspace-channel-layout
description: Plan (owner decision 2026-10-31) for the redrawn Workspace channel screen: one-line header, catch-up line for the briefing card, compact lines, clean composer, side panels, Today page; phone rules
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

- C2 Briefing card to catch-up line: done #3873 (`workspace/workspace-channel-catchup.md`; `ws_channel_catchup()`, the greeting stays as data).

- C3 Pinned and system lines: done #3873 (the pinned message in one line; with the catch-up line one row on a phone; system notices already one line).

- C4 Messages: done #4125 (`workspace/workspace-channel-messages.md`; a grouped line's time on hover, a bar of reaction, Reply, discussion, task and More, the tag alone for a decision, the discussion as one line, Convert to text an "Aa" in the player).

- C5 Composer: done #3898 (`workspace/workspace-channel-composer.md`; the hint line gone, marks behind Aa, a phone's one row with a tools switch, the strip and tabs hide while the keyboard is up; the / list keeps its look).

- C6 Side panels: done #4143 (`workspace/workspace-channel-panels.md`; the channel info beside the conversation in the discussion's place with purpose, status, members, notifications, read aloud, time spent and settings; the discussion's head and follow line as drawn).

- C7 Overview: the greeting only here, then Waiting for you, Today and overdue, Calendar today, What happened in the channels, Recent decisions.

- C8 Name: the page is called "Today" (owner, 2026-10-31, #4192, "bugün olsun"): `t('home')` in `includes/workspace/home.php`, the `home` place label of `ws_nav_groups()` (`includes/workspace/nav.php`), the tour step and the help texts that name it; `lang('Today')` exists. The address `?view=home` and every link stay. With C7 or right after it.

## What is still open?
- Nothing: the name is decided (C8).

## Where to start?
- C7, then C8. The local sandbox (`tools/setup_sandbox.sh`) draws the channel screen at desktop and phone width; try each step at 390 px with the keyboard open before anything else.
