---
name: bug-hunt-areas-channel-memory
description: Bug hunt of the channel memory (2026-11-26, PR #11790): who reads and writes, Claude's application and the general memory, a Turkish tag, the brief's size bound; what read clean and what is asked
---

## What was the area?
- Given by the architect in the agent channel (2026-11-26, request 3055): the channel memory (#8188 to #10546), which every agent's brief is built from.
- Files read: `includes/workspace/channel_memory.php`, `api_memory.php`, the memory cases of `includes/workspace/actions.php`, `ws_message_mark_check()` / `ws_message_mark()` (messages.php), `ws_ai_memory_parts()` and `pg_ai_data_block()`.

## What did it fix? (PR #11790)
- The application Claude works through read, listed and wrote the general memory where Claude may be asked in no agent channel, so a private agent channel's records moved there left the site. Now `ws_api_memory_general_can()` asks `ws_memory_general_can($viewer, true)`: an agent channel the owner posts in and Claude may be asked in.
- A tag with a dotted capital I ("İade") was refused: lowered it took a combining dot the tag check refuses, and a list filtered by it found nothing. `ws_memory_tag()` folds it to i, for storing and for both list filters.
- The brief's digest ran past `WS_MEMORY_DIGEST_MAX` by its closing count line, which was added after the lines were fitted. The layout is redone with less room until the count fits too.

## What read clean?
- Who reads: a record by id, the channel list and the panel's save, delete and move check the record's own channel; the general list holds channel_id 0 only; a guest is no viewer; a removed member fails `ws_can_post_channel()` at the write.
- The move: only an agent channel's record, by a poster of it who keeps the general memory.
- The brief: expired records left out twice; titles one line; `<<<` cannot close the data block.
- The mark: a system message is not markable; an application's mark is named and filtered by `ws_mark_by_app()` everywhere; deleted messages are left out of the brief and the lists.
- The API bounds match the panel's: both trim and count characters; `ws_memory_check()` is shared.
- The sweep: only records expired 30 days ago and those of gone channels, never a general one by its channel.
- The note: `ws_message_system()` wakes no agent; a burst by one writer extends one note.

## What is left as a question?
- An update that changes nothing still writes a "changed" note and moves the record up the index.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workspace/channel-memory.md, docs/memory/workspace/channel-memory-general.md
