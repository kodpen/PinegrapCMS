---
name: decisions-made-schema-32
description: Decisions list, schema part 32 (2026-11-20 on): ws_app_answers, an application's answer shown to the writer of a command alone
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-31.md`.

## May the Workspace get a table for an application's answer to the writer alone? (owner, 2026-11-20, agent channel poll 169, message 10332)
- Owner: "Evet, tablo eklensin". The question named the table `ws_app_answers` (for whom, which channel, which application, the answer's text), rows deleted after 24 hours, no other table or column changed.
- What it is for (feature K28 of plan channel-commands): until now an application could answer a command only with a normal message everyone reads. With the table the answer reaches the writer alone, on the phone and in a second window too; no bell, no push, no inbox line.
- Running sites: nothing changes; an application that posts a normal message works as before.

## Which columns does ws_app_answers have? (architect, 2026-11-20, agent channel message 10333, answer to K2's request 1744)
- `id` BIGINT UNSIGNED AUTO_INCREMENT, `user_id`, `channel_id` and `app_id` INT UNSIGNED, `command` (the command's name), `token` CHAR(32) (a digest of the signed answer token), `body` TEXT, `created_at`.
- Keys: (`user_id`, `channel_id`, `id`) for the read, `created_at` for the sweep, `token` for the count.
- `token` is there because an answer token takes at most three answers, which cannot be counted otherwise. The token itself is signed with the site's secret over user, channel, application and time, valid 15 minutes, and stored nowhere.
- The token alone is not enough: the calling application must be the token's application and still hold the command. The writer must still read the channel.
- `body` is no longer than a message, cleaned as a message body and drawn as one.
- Rows go with their channel, application and account, and at 24 hours in maintenance, as `ws_call_signals` are swept.
- `ws_sync` reads them for the open channel only, in one indexed read; before the migration step has run nothing is read or written.
- Built by step CC12b; the census it rests on landed with #10636.
