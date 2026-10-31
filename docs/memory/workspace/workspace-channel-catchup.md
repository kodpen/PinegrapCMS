---
name: workspace-channel-catchup
description: The channel's catch-up line (C2, plans/workspace-channel-layout.md) in place of the greeting card: what came while away, the way to it and to late tasks, the AI summary
---

## What does a channel show above the conversation?
- `catchupNode()` (workspace.js) draws one line only when `briefing.catchup` is set: "While you were away" (or "Waiting for you" when only tasks are late) with the counts as words (`a new message` / `{var:1} new messages`, mentions, overdue tasks), Go to where you were mentioned or Go to new messages (`jumpTo(go, true)`), Overdue tasks (the Tasks tab), Sum up what I missed (`ws_brief_recap`, the summary under the line) and close.
- Closed, it stays closed in that channel for the day (`ws-brief-closed:<channel>:<date>` in localStorage, the old card's key). With the keyboard up on a phone it hides; there its buttons keep their icons only.

## Where does the line come from?
- `ws_channel_catchup()` (`includes/workspace/briefing.php`), called at the end of `ws_channel_briefing()`: null when no message came (a first visit counts none), nobody mentioned the reader and no task is late; `go` is the first unread mention, else the first unread message. `tests/workspace_channel_catchup_test.php`.
- `ws_channel_briefing()` still builds the greeting, its lines and advice as data; the channel no longer draws them (the greeting stays on the overview and in the morning mail).

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): the drawn design; the card took a third of a phone's screen.
