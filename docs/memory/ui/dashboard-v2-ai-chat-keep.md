---
name: dashboard-v2-ai-chat-keep
description: Dashboard Assistant card chat box after a reload: the browser keeps the talk's ids only, reads the chain again through ws_sync, last 5 lines, 24 hours, New chat; why its links say "Open it"
---

## Does the talk stay after a reload?
- Yes (owner's trial, 2026-11): the browser keeps the talk's ids only, in `localStorage` under `pg_dashboard_v2_ai_chat_store(PATH, user id)` (per site and person): assistant key, channel, root, the chain's ids (last 100), the last line's time and the person's last send. Never a message's text; no table or server store.
- On load `restore()` reads the channel again with `ws_sync` (`since_id` root − 1, `panel`, pages of 200, at most 10), so the Workspace's gate decides what comes back; `pgDash.aiTalk.chain()` keeps the person's own lines of it and the answers to any chain id (deleted ones drop) and the box shows the last 5. Nothing else said in the channel comes in.
- The next line replies to the same root. Kept 24 hours after the last line, then the box is empty and the next line starts a new message; another assistant or channel, a channel the person may no longer read, or an assistant no longer askable drops it too. "New chat" beside the title empties it at once.
- Polling goes on after a reload when the last line is the person's and inside its ten minutes. The pure helpers are tested in Node (`tests/dashboard_v2_ai_test.php`).

## Why does a link say "Open it"?
- `lang('Open')` is the state ("Açık"). The card's links (sent note, chat lines, daily summary) and the Team cards' Open links (`queue.php`, `waiting.php`) use `lang('Open it')` ("Aç"); the key "Open" stays for its other users.
