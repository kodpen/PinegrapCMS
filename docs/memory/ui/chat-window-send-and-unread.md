---
name: chat-window-send-and-unread
description: Panel chat send rules (body check before the conversation row, 2000-character limit in the composer), header button names and sizes, close confirmation, closed tag, unread loop inside a conversation
---

## When is a panel conversation row written?
- `pg_chat_send()` runs `pg_chat_check_body()` and `pg_chat_rate_limited()` first, and only then creates the `chat_conversations` row of a draft (`conversation_id = 0`). A refused first message (empty, too long, too fast) used to leave an empty row in both people's lists until `pg_chat_cleanup_empty()` removed it five minutes later (#5960).
- `pg_chat_check_body()` treats anything but a string or a number as empty, so an array body is refused instead of printing a PHP warning and saving "Array". `pg_chat_site_send()` uses the same check.
- `pg_chat_conversation_list()` lists only rows with `last_message_id > 0`.

## Where is the 2000-character limit?
- `pg_chat_max_length()` in `chat.php`; the launcher config carries it as `max_length`, the composer sets it as `textarea.maxLength` and shows "Characters left" in the last 100 characters. The error text names the limit (#5965).

## How are the header icon buttons drawn?
- Back, contact/user edit links, close, delete and the bell carry `.pg-chat-head-btn`: at least 24x24 px, 32x32 px in the full-screen phone layout, and an `aria-label`. The bell has `aria-pressed` for its state; the launcher's name adds the unread count, the badge is `aria-hidden`. Emoji tabs take their names from `STR.emoji_groups`, in the order of `EMOJI` (#5961, #5962).

## What happens on a closed conversation?
- Closing asks first (`confirmClose()`, like `confirmDelete()`): it cannot be undone. A closed conversation has no close button and carries a "Closed" chip in the list. List rows carry `peer.username`, so the header shows `@username` wherever the conversation is opened from (#5962, #5963).

## Does the badge move while a conversation is open?
- Yes. `openConversationView()` stops the list refresh and starts `checkUnread()` every `POLL.list` seconds on the `badge` timer; `leaveConversationView()` stops it (the list refresh takes over) and closing the window restarts the 60 s badge loop (#5964).
