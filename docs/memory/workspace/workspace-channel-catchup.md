---
name: workspace-channel-catchup
description: Over a channel's conversation (C2, C3 of workspace-channel-screen.md): the catch-up line in place of the greeting card, the pinned line, one row on a phone
---

## What does a channel show above the conversation?
- `catchupNode()` (workspace.js) draws one line only when `briefing.catchup` is set: "While you were away" (or "Waiting for you" when only tasks are late) with the counts as words (`a new message` / `{var:1} new messages`, mentions, overdue tasks), Go to where you were mentioned or Go to new messages (`jumpTo(go, true)`), Overdue tasks (the Tasks tab), Sum up what I missed (`ws_brief_recap`, the summary under the line) and close.
- Closed, it stays closed in that channel for the day (`ws-brief-closed:<channel>:<date>` in localStorage, the old card's key). On a phone its buttons keep their icons only and the lead word goes; they and Close are 2.5rem touch targets, negative block margins keep the row thin (#3945).

## How do the catch-up line and the pinned message stand?
- `drawCenter()` puts both in `.ws-strip`: one above the other; on a phone, when both are there, one row (`:has()`), the catch-up line first and the pin as a button that opens it; the whole strip hides while the keyboard is up.
- `drawPinBar()`: one line, the pin icon, who wrote it and its text (`.ws-pin-words`), a click opens it in the conversation (`jumpTo()`); who pinned it is the tooltip, the read receipt and close follow.

## Where does the line come from?
- `ws_channel_catchup()` (`includes/workspace/briefing.php`), called at the end of `ws_channel_briefing()`: null when no message came (a first visit counts none), nobody mentioned the reader and no task is late; `go` is the first unread mention, else the first unread message. `tests/workspace_channel_catchup_test.php`.
- "Unread" counts from `ws_channel_members.last_read_id`, moved by `ws_channel_mark_read()` (`includes/workspace/channels.php`) and the clear in `eras.php` with `GREATEST(last_read_id, <bare int>)`. A quoted id made MySQL 8 compare as text ('9' > '157'): once ids gained a digit the mark, the counts and this line never moved (#3938, `tests/workspace_read_mark_test.php`).
- `ws_channel_briefing()` still builds the greeting, its lines and advice as data; the channel no longer draws them (the greeting stays on the overview and in the morning mail).

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): the drawn design; the card took a third of a phone's screen.
