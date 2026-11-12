---
name: workspace-channel-overview
description: A Workspace channel screen as a whole: header, catch-up and pinned line, messages, writing box, info panel, the Tasks/Decisions/Files/Summary/Board/Memory tabs, export, icon; which file holds each
---

## What is it?
- The screen a channel opens on in the Workspace (`drawCenter()` in `assets/js/workspace.js`): one card in the channel's colour holding the header and the tabs, the conversation, the writing box, and a side panel for the channel info or a discussion.
- Redrawn in C1-C8 (2026-10-31), card look CL1-CL3 (2026-11-10); every feature stayed, a rare one moved behind More or the menu.

## Which decisions bind it?
- The redraw, the phone rules and who changes the design: `workflow/decisions-made-workspace-5.md`, kept in `workspace-channel-screen.md`; the card look and who builds it: `workflow/decisions-made-workspace-13.md` (`plans/workspace-channel-look.md`).
- What the channel menu may hold: `workflow/decisions-made-workspace-2.md` (a key rule). The tour: `workflow/decisions-made-workspace-6.md`. Export: `workflow/decisions-made-workspace-10.md`. When a channel makes a sound: `workflow/decisions-made-workspace-14.md`, built in `workspace-channel-sound.md`.
- Owner lines of their own: catch-up, composer, Decisions tab, messages, panels, panes, look, Summary/Board/Memory.

## Which parts are there? (label on screen, code name)
- Header: name with its tags, people, search, Channel info, New task, the tour, the ⋯ menu (`workspace-channel-head.md`). Search in this channel, "Only in #channel", reads its discussions too (`workspace-channel-search-scope.md`).
- Over the conversation: "While you were away" with Sum up what I missed, and the pinned message (`workspace-channel-catchup.md`); how the counts treat an empty due date (`workspace-channel-greeting-task-counts.md`).
- Messages: grouped lines, the hover bar (reaction, Reply, discussion, Make a task of it, More), the decision tag, the discussion line, Convert to text (`workspace-channel-messages.md`).
- Writing box: one row, Aa for the marks, a phone's tools switch (`workspace-channel-composer.md`).
- Channel info panel beside the conversation, in a discussion's place: Purpose, Status, Members, Add people, Notifications, Time spent (`workspace-channel-panels.md`).
- Tabs: Tasks, Decisions and Files as cards (`workspace-channel-panes.md`, `workspace-channel-decisions-tab.md`, `workspace-channel-files-tab.md`); Summary, Board and Memory and the empty states (`workspace-channel-summary-board-memory.md`).
- The card, its colour wash, pill tabs, the day chip and the send, bounce and slide-in motions: `workspace-channel-identity-motion.md`.
- A channel's icon, chosen in the channel form (`workspace-channel-icon.md`), and what a template gives it (`workspace-channel-icon-templates.md`).
- Export in the channel menu: Markdown or a print page (`workspace-channel-export.md`).
- Channel group webhook events: `workspace-channel-group-events.md`.

## Where is each part?
- Screen code: `assets/js/workspace.js` (`drawCenter()`, `messageNode()`, `composer()`, `showMembers()`, `draw<Tab>()`); Board `workspace_board_channel.js`, Memory `workspace_memory.js`; styles `backend.src.css`.
- Server: `includes/workspace/` (`briefing.php` the catch-up, `channel_export.php`, `channel_icons.php`, `groups.php` the events, `threads.php` the search scope).
- What every change keeps, a phone's rules, card titles: `workspace-channel-screen.md`. Voice on the screen: `workspace-voice-overview.md`. Mail channels: `mail-compose.md`.

## How is a part added?
- Into the channel menu only the channel's own settings and actions (`channelMenu()`, `tests/workspace_channel_menu_test.php`); onto the hover bar only through `messageActions()`' `tool` number (`tests/workspace_message_bar_test.php`).
- Check it at desktop and 390 px, light and dark, against what `workspace-channel-screen.md` keeps; access through `ws_can_read_channel()` and its siblings.
- A new tab or panel part gets its own `workspace-channel-<name>.md`, named in the parts above.
