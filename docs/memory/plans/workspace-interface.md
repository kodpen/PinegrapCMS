---
name: workspace-interface
description: Plan (owner 2026-11-22): the Workspace's interface improved: the list in the main menu redrawn (tools dock, Unread filter, row marks, keys), one bar for its screens, the panel's foot, a New line
priority: 6
---

## What is asked?
- Owner (2026-11-22, in the architect's window): "workspace arayüzde iyileştirme planlarmısın. menüsü dahil". Recorded in `workflow/decisions-made-workspace-48.md`, with what of his earlier decisions the plan changes and what it adds.

## Where are the drawings?
- `workspace/workspace-interface-drawing/list.html` (today's list beside boards L1 to L3) and `screens.html` (boards C1, S1 to S3), each with its `-dark.jpg` and `-light.jpg`; `parts.css` is shared by both.
- Where a drawing and these words differ, the drawing wins; what neither says is asked of the architect before it is built.

## What is wrong today? (development 62b98310, seen on a sandbox with 13 channels)
- The Tools tiles stand under the channels: at a 900 px window they fall out of sight, so going from a channel to My Tasks means scrolling the list.
- Unread is told by bold text and a grey count alone; nothing lists only the channels with something new, and nothing reads them all at once.
- Channels not joined stand dimmed among the joined ones.
- Each screen's toolbar is built its own way and wraps to a second row at 1440 px (My Tasks, My Notes, Data, Work Calendar, Planning Board).
- The panel's foot (product name, version) stands right under the content: in the middle of a short screen, and under the writing box of a channel, where it takes about 85 px of the conversation.
- The top bar's description of a screen runs under the search field.
- Nothing marks where the unread messages of a channel begin.

## May its steps be given out?
- Not before the owner's word on the drawings is in `workflow/decisions-made-workspace-48.md`. So far he accepted the tools dock and refused its folded row of icons; the channels not joined, the panel's foot and what the plan adds wait.

## What does the plan settle itself?
- The list's place, order and access rules stay (`workflow/decisions-made-workspace.md`, `-5.md`, `-6.md`): one list in the main menu's Workspace panel; search, Today, Channels, My discussions, Tools and More; only what the server sends is drawn.
- The channel screen's own drawing stays (header card, tabs, messages, writing box, side panels: `workspace/workspace-channel-screen.md`); the plan adds one line to it and takes the foot away.
- Every control of today's toolbars stays; only its place changes.
- No schema, no new right. One count joins `ws_side_payload()`; one panel action marks every joined channel read.
- New parts join the Design examples screen in the step that first draws them ("Workspace list row", "Tools dock", "Screen bar with filters", "Empty screen"); its "Not drawn yet" line "Workspace: channel and lists" is shortened by them.

## How does it stand to the design language plan?
- It draws the Workspace's lists and bars DL5f waited for (`plans/panel-design-language-steps.md`). What `workspace/canvas-phone.md` left for DL5f stays there.

## What is left out?
- A sidebar or rail of the Workspace's own, direct messages between two people, presence.
- A new look for messages, the channel header or the tabs; grouping My Tasks by day.
- The narrowed rail: it keeps showing the main menu's rows, the list one click away.
- The Flows screens (`plans/flows-editor-next.md`) and the E-mails area.

## What binds it?
- `workflow/decisions-made-workspace-48.md`; `ui/screen-composition-rules.md`; `ui/look-new-parts.md`; `ui/page-button-bar.md`; `ui/main-menu-panel-scripts.md`; the key rule on `ws_nav_groups()` in `workspace/README.md`.

Related: docs/memory/plans/workspace-interface-list.md, docs/memory/plans/workspace-interface-screens.md, docs/memory/plans/workspace-interface-steps.md, docs/memory/plans/workspace-interface-steps-2.md, docs/memory/workspace/workspace-menu-list.md, docs/memory/workspace/workspace-menu-tools.md
