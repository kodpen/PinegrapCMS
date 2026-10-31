---
name: workspace-channel-messages
description: The channel's messages as drawn (C4, plans/workspace-channel-layout.md): grouped lines with their time on hover, the short hover bar, the decision tag, the discussion line, Convert to text
---

## How are lines grouped?
- `messageNode()` (workspace.js): `follow` when the same sender wrote the line before within 300 s, the line is a plain message (`kind` message, no `parent_id`), the one before is not a task, and it is not a voice recording (`voiceFile()`, `PGWsVoice.isVoice()`). A decision, a note, a task, an answer and a voice message start a group.
- A later line hides the face and the head; its time (`clockText()`, in the site's time zone) sits in the face's column as `.ws-msg-clock`, `aria-hidden`, shown on hover, focus or a tap (`.ws-msg-active`). The line is still read with its hidden head (`aria-labelledby`).

## What is on the hover bar?
- `messageTools()`: the reaction picker, the `messageActions()` items whose `tool` is a number, in that order (1 Reply, 2 the discussion: start, open or join, 3 Make a task of it), then More with every action. Copy, notes, decision and note marks, image and text edits, Edit and Delete wait in More and the context menu; the three quick emojis left the bar (the menu's emoji row keeps them). `tests/workspace_message_bar_test.php`.
- A phone shows the same bar under the tapped message (`.ws-msg-active`).

## How is a decision marked?
- By its tag alone (`.ws-msg-flag`, no icon; the lock stays on a record change's trace). The coloured edge belongs to the Decisions tab's cards only (`.ws-pane-decisions > .ws-msg-decision`).

## What does the discussion line say?
- `threadBar()`: up to three faces, then "No replies yet", "1 reply" or "3 replies · last reply 15:20" (`th_bar_*`; "Concluded" first when closed), the unread count, Join for a person not in it. The title is the tooltip and a `visually-hidden` "Discussion: title:" read first; a click opens the panel. The assistant and follow notes stay under it.

## Where is Convert to text?
- A small "Aa" in the voice player's row, before the download link (`PGVoiceText.control(url, texts, locale, { into, before })`, voice-browser-to-text.md); the words appear under the player from the first click.

## What was fixed on the way?
- A recording's waveform drew one thin line: the progress fill's rule `.ws-voice-bar > span` also took the strokes (absolute, width 0). The fill has its own class, `.ws-voice-fill`.

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): the drawn design; every feature kept, a rarely used one behind More.
