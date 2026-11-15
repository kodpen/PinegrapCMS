---
name: channel-open-items
description: Plan (owner 2026-11-15, agent channel message 5143): a line above a channel's conversation naming its open polls, agent questions, check-ins and approval requests, each with a scroll button
priority: 6
---

## What is asked?
- Owner (2026-11-15, agent channel message 5143): "oylama ve ajan sorusu, checkin ve onay isteği gibi özellikler bir kanalda kullanılmışsa kanalın üst tarafında kısaca ne olduğu ile kaydırma düğmesi yer alsın."
- Today the strip above a conversation holds the catch-up line and the pinned message (`workspace/workspace-channel-catchup.md`); an open poll or question is found only by scrolling.

## Which steps are there?
- OI1 (M) The open items line. Given 2026-11-15 (agent channel message 5157), `@adnan`.
  - Server: one function gives a channel's open items the reader may see: open polls and agent questions, running check-ins, approval requests still waiting; each with its kind, its message, a short text and whether it waits for the reader's own answer; the reader's own first; a bounded list. It reuses today's readers (`ws_agent_questions_for()`, `ws_approvals_waiting()`), comes with the channel's load and follows the events that redraw those cards.
  - Screen: a line in `.ws-strip` under the pinned line (`drawCenter()`): the first item's kind and short text and a Go button that scrolls to its message (`jumpTo()`). More than one: the count, which opens a small list, each row going to its own message. An item waiting for the reader is marked. A closed or answered item leaves the line; no item, no line.
  - Phone: one row with the catch-up line and the pin, a button with the count that opens the list.
- OI2 (S) after OI1 and CA12 of `channel-api-gaps`: `open_items` on `GET /workspace/channels/{id}`, the same list.
- Close-out, in OI2's PR: `workspace/workspace-channel-catchup.md` gains the line, this plan deleted.

## Whose look?
- The owner's: Workspace is a kept screen and the line is new on it. OI1 posts its pictures (one item, several, the open list; 1440 and 390, light and dark) in the agent channel, the architect brings them to him, and the step is not marked ready before his look.
- The architect's readings, not his words: only open items show, the reader's own first, a list behind a count when there are several.
