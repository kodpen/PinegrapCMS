---
name: workspace-channel-search-scope
description: A search narrowed to one channel (palette "Only in #channel", ws_message_search) reads the channel's discussions too: ws_channel_search_ids()/ws_channel_search_sql(), #4239
---

## What does "Only in #channel" read?
- The channel and every discussion opened on its messages (`ws_threads.parent_channel_id`): `ws_channel_search_ids()` in `includes/workspace/threads.php` lists them, `ws_channel_search_sql()` turns them into `AND channel_id IN (…)`.
- Used by `ws_message_search()` (messages group, `ws_message_search` action) and `ws_palette_message_rows()` (decisions, files). Every row is still checked with `ws_can_read_channel()`, so a discussion the person cannot read stays out.
- A hit in a discussion shows the discussion's own name under it, as with "Everywhere".

## Why?
- Since the discussion opens as a panel beside its channel, people take it as part of the channel; the channel scope found nothing that "Everywhere" found (#4239).
- `tests/workspace_channel_search_test.php`.
