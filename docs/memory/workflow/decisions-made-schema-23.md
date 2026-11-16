---
name: decisions-made-schema-23
description: Decisions list, schema part 23 (2026-11-16 on): the ws_highlights table for a reader's highlights; the columns of PU4 on auto_dialogs as confirmed
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which table do a reader's highlights get? (owner, 2026-11-16, agent channel message 5547)
- "bunun için yeni tablo onaylıyorum": one new table, `ws_highlights`, added by HL1 (`plans/message-highlights.md`) as a migration step of the open version. No backfill.
- The columns are the architect's, not his words: `id` INT UNSIGNED AUTO_INCREMENT, primary key; `message_id`, `channel_id`, `user_id` INT UNSIGNED NOT NULL DEFAULT 0; `scope` VARCHAR(10) NOT NULL DEFAULT 'me' (`me`, `all`; a reader treats an unknown value as `me`); `quote` VARCHAR(1000) NOT NULL DEFAULT ''; `occurrence` SMALLINT UNSIGNED NOT NULL DEFAULT 0; `note` VARCHAR(255) NOT NULL DEFAULT ''; `created_at` of `ws_messages.created_at`'s type.
- Keys: `idx_message (message_id)`, `idx_user (user_id, id)`, `idx_channel (channel_id)`. No unique key: the quote is too long for one, so the same place twice is caught in code.
- Rows leave with their message, thread, channel or person ("silinen sohbetlerden ve kanallardan vurgulamalar temizlensin db yükü olmasın").

## Which columns does PU4 add to `auto_dialogs`? (architect, 2026-11-16, confirmed in PU4's draft, #9851)
- Under the owner's yes to new columns on `auto_dialogs` (`decisions-made-schema-21.md`, message 5139): `open_on` VARCHAR(10) NOT NULL DEFAULT 'delay'; `open_scroll` TINYINT UNSIGNED NOT NULL DEFAULT 50; `audience` VARCHAR(10) NOT NULL DEFAULT 'all'; `place` VARCHAR(10) NOT NULL DEFAULT 'centre'.
- Choices are VARCHAR checked against a list in code, never ENUM; a reader treats an unknown value as the default.
