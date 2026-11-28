---
name: decisions-made-schema-56
description: Decisions made, schema part 56 (2026-11-28 on): files sent together in a channel become one group by the column ws_messages.file_group and its key (poll 255)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-55.md`.

## Which column do files sent together get? (owner, 2026-11-28, agent channel poll 255, message 15447)
- His words (messages 15440, 15443): "birde 3 görsel koydum bu mesaja her biri ayrı gönderilip ayrı bir mesajla düştü kanala bu da değerlendirilsin birlikte konulan görseller grup gibi olsun"; "her görsel ayrı ayrı yükleniyor penceresi açıyor ve ayrı mesaja düşüyor".
- Asked (message 15446). A: one column, a mark on `ws_messages` for the files sent together, drawn as one message, each file still its own record in the API. B: no schema, file messages that follow each other drawn joined on the screen; a guess that breaks when another message comes between, and the agents' side stays as it is.
- Owner: "A: tek sütunla gerçek grup".
- So, by a step of the open version with the idempotent helpers: `ws_messages.file_group` INT UNSIGNED NOT NULL DEFAULT 0, the id of the group's first message (that message carries its own id; 0 is a message alone), and the key `idx_file_group` (`file_group`) a group is read by.
- The key was not in the poll's text. The architect named it to him under the poll (message 15452) as part of the same change: no data lost, no stored row rewritten.
- His yes covers this column with its key and nothing else. A message still carries one file (`file_id`, `file_name`).
- Running sites: no stored message is rewritten and none is grouped afterwards; a message from before the column reads 0 and is drawn as today.
- The architect's, not the owner's words: the column's name and meaning, the bound of 10 files a group, how a group is drawn, sent and deleted (`plans/channel-file-groups.md`).
